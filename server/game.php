<?php
require "board.php";
require "serverFunctions.php";
require "ObjectsArr.php";

class Game{
    private $address;
    private $port;
    private $mainServerPort;
    private $numOfPlayers = 2;  // Ustawiona liczba graczy
    private $board;  
    private $target;
    private $turn = 0;          // Tura
    private $paused = false;

    private $clients;       // Tablica przechowująca wszystkie gniazda (serwer + klienci), klucz -> socket, wartość -> [id gracza, nick]
    private $players = [];  // Dla czytelności 
    private $server;
    private $mainServer;
    private $admin = null;  // admin to pierwszy połączony klient
    private $read;          // Kopia tablicy klientów do odczytu
    private $write = null;
    private $except = null;
    private $availableIDs = [0,1,2];  // Kula, krzyżyk, piramida

    const maxPlayers = 3;          // Maksymalna możliwa do ustawienia liczba graczy
    private const commandTypes = [ "put", "leave", "ping", "reping" ];
    private const adminCommands = ["create", "drop", "kick", "pause", "unpause", "ping", "reping" ];

    function __construct($mainPort, $addr, $prt, $nOfPlayers, $sizes = "4,4,4", $trg = 3){
        if(!filter_var($addr, FILTER_VALIDATE_IP)) throw new Exception("Nie poprawny adres");
        if(empty($prt) || $prt < 0 || $prt > 65535) throw new Exception("Nie poprawny port");
        if(empty($nOfPlayers) || $nOfPlayers < 0 || $nOfPlayers > Game::maxPlayers) throw new Exception("Nie poprawna ilość graczy, maksymalna to: " . (string)Game::maxPlayers);

        $this->address = $addr;           
        $this->port = $prt;   
        $this->mainServerPort = $mainPort;             
        $this->numOfPlayers = $nOfPlayers; 
        $this->target = $trg;
        $this->board = new Board(explode(',', $sizes), $this->target);  

        $this->server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$this->server) throw new Exception("Błąd tworzenia gniazda: " . socket_strerror(socket_last_error()));
        socket_set_option($this->server, SOL_SOCKET, SO_REUSEADDR, 1);
        if (!socket_bind($this->server, $addr, $prt)) throw new Exception("Błąd bindowania: " . socket_strerror(socket_last_error($this->server)));
        socket_listen($this->server); 
        socket_set_nonblock($this->server);

        // Łączenie z głównym serwerem, w celu późniejszego zawiadomienia o końcu gry
        $this->serverHandshake();
        send("New " . $this->port, [$this->mainServer]);

        $this->clients = new ObjectsArr();
        $this->clients->add($this->server, [-1, ""]);
        $this->read = $this->clients;
    }
       
    function __destruct(){
        $this->board->__destruct();
        $sockets = $this->clients->getFirstArr();
        foreach($sockets as $socket){ 
            //send("Closed", [$client], [$this->server]);
            //if($client !== $this->server) socket_close($client);
            unsetSocket($socket, $sockets, $server);
        }
        unsetSocket($this->mainServer, $sockets, $server);
        socket_close($this->server);
        die();
    }

    function monitor(){
        while (true) {
            $this->read = $this->clients;
            $readArr = $this->read->getFirstArr();

            socket_select($readArr, $this->write, $this->except, 0);
            file_put_contents("game.txt", "GAME". $this->port, FILE_APPEND);
            usleep(10000);  // 10ms

            for ($i = 0; $i < $this->read->count(); $i++) {
                $socket = $this->read->firstByID($i);
                $idNick = $this->read->secondByID($i);

                if ($socket === $this->server){  // Nowe połączenie
                    $newClient = socket_accept($this->server);
                    if(!$newClient) continue;
                    socket_set_nonblock($newClient);
                    $this->clients->add($newClient, [-1, ""]);

                    $request = socket_read($newClient, 5000);
                    preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $request, $matches);

                    if(!isset($matches[1]) || empty($matches[1])){
                        file_put_contents("error.txt", "mathces Error", FILE_APPEND);
                        $this->clients->pop();
                        continue;
                    }
                    $matches[1]= rtrim($matches[1], "\r\n");

                    $key = base64_encode(pack('H*', sha1($matches[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
                    $headers = "HTTP/1.1 101 Switching Protocols\r\n";
                    $headers .= "Upgrade: websocket\r\n";
                    $headers .= "Connection: Upgrade\r\n";
                    $headers .= "Sec-WebSocket-Version: 13\r\n";
                    $headers .= "Sec-WebSocket-Accept: $key\r\n\r\n";
                    socket_write($newClient, $headers, strlen($headers));

                    if($this->admin === null) $this->admin = $newClient;  // Pierwszy klient to admin
                    continue;
                }

                $args = readMessage($socket);
                if($args === false || empty($args) || $args === "") continue;
                    
                if($socket === $this->admin){  //Admin
                    if(!$this->admin($args, $socket)) continue;
                }
                else{
                    if($this->paused && $args[0] != $commandTypes[2]) continue; //Wyjątek - opuszczenie gry
                    if(strtolower($args[0]) === "join"){
                        if($this->getCurrentNumOfPlayers() >= $this->numOfPlayers){  // Gra pełna
                            send("Error 30", [$socket]);
                            continue;
                        }
                        if(empty($args[1]) || $args[1] === false){
                            send("Error 31", [$socket]);
                            continue;
                        }
                
                        $this->clients->setSecond($socket, [$this->availableIDs[0], $args[1]]);
                        array_shift($this->availableIDs); 
                    }
                    else{
                        if($idNick[0] < 0){
                            send("Error 00", [$socket]);
                            continue;
                        }
                        if(!$this->player($args, $idNick[0], $socket)) continue;
                    }
                }
        
                $players = array_filter($this->clients->getFirstArr(), function($idN){   
                    return isset($idN[0]) && $idN[0] >=0 && $idN[0] <= maxPlayers - 1;
                });
        
                // Refresh
                for($i = 0; $i < $this->read->count(); $i++){
                    $sckt = $this->read->firstByID($i);
                    $idN = $this->read->secondByID($i);
                    send("Refresh" . implode(',', $this->players) . $this->board->implode() . (string)$this->target . (string)$this->turn . (string)$idN[0], 
                                             [$sckt], [$this->server]); 
                }   
            }
        }
    }

    private function admin($args, $socket){
        switch(strtolower($args[0])){
            case $adminCommands[0]:    //Create
                if(count($args) < 4){
                    send("Error 11", [$socket]);
                    return false;
                }
                if($args[1] < 2 || $args[2] > maxPlayers){
                    send("Error 21", [$socket]);
                    return false;
                }

                try{ $this->board = new Board(explode(',', $args[2]), $args[3]); }
                catch(Exception $e){
                    send("Error 20 " . $e, [$socket]);
                    return false;
                }
                break;

            case $adminCommands[1]:  //Drop
                send("Drop", $this->mainServer);
                $this->__destruct();
                break;

            case $adminCommands[2]:  //Kick
                if(count($args) < 2){
                    send("Error 11", [$socket]);
                    return false;
                }
                if($args[1] < 0 || $args[1] > 3 || in_array($args[1], $this->availableIDs)){
                    send("Error 90", [$socket]);
                    return false;
                }

                $toKick = array_filter($this->clients->getSecondArr(), function($idN) use($args){ return isset($idN[0]) && $idN[0] === $args[1]; });

                if(empty($toKick)){
                    send("Error 90", [$socket]);
                    return false;
                }

                $arr = $this->read->getSecondArr();
                for($i = 0; $i < count($arr); $i++)
                    if($arr[$i] === $toKick[0]){
                        send("Kicked", [$this->read->firstByID($i)]);
                        $this->read->delByID($i);
                        break;  
                    }
                array_push($this->availableIDs, $args[1]);
                sort($this->availableIDs);
                break;

            case $adminCommands[3]:  //Pause
                $this->paused = true;
                foreach($this->read->getFirstArr() as $client) send("Paused", [$client], [$socket]);
                break;

            case $adminCommands[4]:  //Unpause
                $this->paused = false;
                foreach($this->read->getFirstArr() as $client) send("Unpaused", [$client], [$socket]);
                break;

            case $adminCommands[5]:  //Ping
                send("Reping", [$socket]);
                break;
            break;

            default:
                send("Error 10", [$socket]);
                return false;
                break;
        }
        return true;
    }

    private function player($args, $id, $socket){
        switch(strtolower($args[0])){
            case $commandTypes[0]:  //Put
                    if(count($args) < 4){
                        send("Error 11", [$socket]);
                        return false;
                    }
                    if($id !== $this->turn){
                        send("Error 23", [$socket]);
                        return false;
                    }

                    if(!$this->board->put($args[1], $id)){
                        send("Error 22", [$socket]);
                        return false;
                    }
                    $this->incrementTurn();
                break;

            case $commandTypes[1]:  //Leave
                $this->clients->delByFirst($socket);
                if($id >= 0) $this->availableIDs[] = $id;
                break;

            case $commandTypes[2]:  //Ping
                send("Reping", [$socket]);
                break;

            case $commandTypes[3]:  //Reping
                break;

            default:
                send("Error 10", [$socket]);
                return false;
                break;
        }
    }

    private function serverHandshake(){
        $this->mainServer = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$this->mainServer) throw new Exception("Nie udało się stworzyć gniazda dla serwera.");

        if (!socket_connect($this->mainServer, $this->address, $this->mainServerPort)) 
            throw new Exception("Nie udało się połączyć z serwerem: " . socket_strerror(socket_last_error($this->mainServer)));
    
        $key = base64_encode(random_bytes(16));
        $headers = "GET / HTTP/1.1\r\n" .
                   "Host: {$this->address}:{$this->mainServerPort}\r\n" .
                   "Upgrade: websocket\r\n" .
                   "Connection: Upgrade\r\n" .
                   "Sec-WebSocket-Key: {$key}\r\n" .
                   "Sec-WebSocket-Version: 13\r\n\r\n";
        socket_write($this->mainServer, $headers, strlen($headers));

        // Oczekiwanie na odpowiedź serwera
        socket_set_option($this->mainServer, SOL_SOCKET, SO_RCVTIMEO, ["sec" => 10, "usec" => 0]);
        $response = socket_read($this->mainServer, 2048);
        if ($response === false || strpos($response, "101 Switching Protocols") === false) 
            die("Handshake nie powiódł się.");
    }

    private function incrementTurn(){ $this->turn = (($this->turn >= $this->numOfPlayers - 1) ? 0 : $this->turn + 1); }

    function getCurrentNumOfPlayers(){ 
        return count(array_filter($this->clients->getSecondArr(), 
                                  function($idN){ 
                                      return isset($idN[0]) && $idN[0] >=0 && $idN[0] <= maxPlayers - 1; 
                                  }
        ));
    }

    function getNumOfPlayers(){ return $this->numOfPlayers; }
    function getPort(){ return $this->port; }
    function getBoard(){ return $this->board; }
    function getTurn(){ return $this->turn; }
    function getPaused(){ return $this->paused; }
    function getSocket(){ return $this->server; }
}
?>