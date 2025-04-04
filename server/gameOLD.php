<?php
require "board.php";
require "serverFunctions.php";

class Game{
    private $address;
    private $port;
    private $mainServerPort;
    private $numOfPlayers = 2;  // Ustawiona liczba graczy
    private $board;  
    private $target;
    private $turn = 0;          // Tura
    private $paused = false;

    private $clients;       // [socket, idGracza, nick]
    private $server;
    private $mainServer;
    private $admin = null;  // admin to pierwszy połączony klient
    private $read;          // Kopia tablicy klientów do odczytu
    private $write = null;
    private $except = null;
    private $availableIDs = [0,1,2];  // Kula, krzyżyk, piramida
    private $numOfIDs = 3;

    const maxPlayers = 3;          // Maksymalna możliwa do ustawienia liczba graczy
    private const commandTypes = [ "put", "leave", "ping", "reping" ];
    private const adminCommands = ["create", "drop", "kick", "pause", "unpause", "ping", "reping" ];

    function __construct($addr, $mainPort, $prt, $nOfPlayers, $sizes = "4,4,4", $trg = 3){
        if(!filter_var($addr, FILTER_VALIDATE_IP)) throw new Exception("Nie poprawny adres");
        if(empty($prt) || $prt < 0 || $prt > 65535) throw new Exception("Nie poprawny port");
        if(empty($mainPort) || $mainPort < 0 || $mainPort > 65535) throw new Exception("Niepoprawny port głównego serwera");
        if(empty($nOfPlayers) || $nOfPlayers <= 1 || $nOfPlayers > Game::maxPlayers) throw new Exception("Nie poprawna ilość graczy, maksymalna to: " . (string)Game::maxPlayers);

        $this->address = $addr;           
        $this->port = $prt;   
        $this->mainServerPort = $mainPort;             
        $this->numOfPlayers = $nOfPlayers; 
        $this->target = $trg;
        $this->board = new Board(explode(',', $sizes), $this->target);  
        $this->numOfIDs = count($this->availableIDs);

        $this->server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$this->server) throw new Exception("Błąd tworzenia gniazda: " . socket_strerror(socket_last_error()));
        socket_set_option($this->server, SOL_SOCKET, SO_REUSEADDR, 1);
        if (!socket_bind($this->server, $addr, $prt)) throw new Exception("Błąd bindowania: " . socket_strerror(socket_last_error($this->server)));
        socket_listen($this->server); 
        socket_set_nonblock($this->server);

        $this->clients[] = array($this->server, -1, "");
        $this->read = $this->clients;
        file_put_contents("logsKiK2/gameDestruct.txt", "2", FILE_APPEND);     // FILE LOG
    }
       
    function __destruct(){
        $this->board->__destruct();
        file_put_contents("logsKiK2/gameDestruct.txt", "a", FILE_APPEND);     // FILE LOG
        for($i = 0; $i < count($this->clients); $i++)
            unsetSocket2($this->clients[$i][0], $this->server);
        
        unsetSocket2($this->mainServer, $this->server);
        if(is_resource($this->server)){
            socket_shutdown($this->server);
            socket_close($this->server);
        }
    }

    function monitor(){
        // Łączenie z głównym serwerem, w celu późniejszego zawiadomienia o końcu gry
        $this->serverHandshake();
        send("New " . $this->port, [$this->mainServer]);
        send("Test", [$this->server]);

        while (true) {
            $this->read = $this->clients;
            $readSockets = array_column($this->read, 0);
            socket_select($readSockets, $this->write, $this->except, 0);

            for ($i = 0; $i < count($this->read); $i++) {
                $socket = $this->read[$i][0];

                send("Ping", [$socket]);
                socket_write($socket, "Ping", 4);

                $errorCode = $socket ? socket_last_error($socket) : socket_last_error();
                socket_clear_error($socket);
                $errorMsg = socket_strerror($errorCode);
                file_put_contents("logsKiK2/socketStates.log", date("Y-m-d H:i:s",time()) . " - [$errorCode] $errorMsg\n", FILE_APPEND); 
                if($errorCode !== 0) continue;

                if ($socket === $this->server){  // Nowe połączenie
                    $newClient = socket_accept($this->server);
                    if(!$newClient) continue;
                    //socket_set_nonblock($newClient); 
                    file_put_contents("logsKiK2/gameMonitor.txt", "n0.2gga", FILE_APPEND);     // FILE LOG 

                    $request = socket_read($newClient, 5000);
                    preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $request, $matches);
                    file_put_contents("logsKiK2/gameHandshake.txt", "LOG\n" . $request, FILE_APPEND);     // FILE LOG
                    file_put_contents("logsKiK2/gameMatches.txt", "LOG\n" . $matches[1] . !isset($matches[1]) . empty($matches[1]), FILE_APPEND);     // FILE LOG

                    file_put_contents("logsKiK2/gameMonitor.txt", "n0.3gga", FILE_APPEND);     // FILE LOG 
                    if(!isset($matches[1]) || empty($matches[1])){
                        file_put_contents("logsKiK2/gameError.txt", "mathces Error(game)", FILE_APPEND);     // FILE LOG
                        send("Closed", [$newClient], [$this->server]);
                        socket_shutdown($newClient);
                        socket_close($newClient);
                        continue;
                    }
                    $matches[1]= rtrim($matches[1], "\r\n");

                    $key = base64_encode(pack('H*', sha1($matches[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
                    $headers = "HTTP/1.1 101 Switching Protocols\r\n" .
                                "Upgrade: websocket\r\n" .
                                "Connection: Upgrade\r\n" .
                                "Sec-WebSocket-Version: 13\r\n" .
                                "Sec-WebSocket-Accept: $key\r\n\r\n";
                    socket_write($newClient, $headers, strlen($headers));
                    file_put_contents("logsKiK2/gameMonitor.txt", "n0.4gga", FILE_APPEND);     // FILE LOG 

                    $this->clients[] = array($newClient, -1, "");
                    if($this->admin === null) $this->admin = $newClient;  // Pierwszy klient to admin
                    continue;
                }

                $args = readMessage($socket);
                if(empty($args) || $args === "" || empty($args[0]) || $args[0] === "") continue;
                file_put_contents("logsKiK2/gameMessages.txt", implode(" ", $args), FILE_APPEND);     // FILE LOG
                file_put_contents("logsKiK2/gameMonitor.txt", "n2gga", FILE_APPEND);     // FILE LOG

                if($socket === $this->admin){  //Admin
                    file_put_contents("logsKiK2/gameMonitor.txt", "n3gga", FILE_APPEND);     // FILE LOG
                    if(!$this->admin($args, $socket)) continue;
                }
                else{
                    file_put_contents("logsKiK2/gameMonitor.txt", "n4gga", FILE_APPEND);     // FILE LOG
                    if($this->paused && $args[0] != $commandTypes[2]) continue; //Wyjątek - opuszczenie gry
                    file_put_contents("logsKiK2/gameMonitor.txt", "n4.5gga", FILE_APPEND);     // FILE LOG
                    if(strtolower($args[0]) === "join"){
                        if($this->getCurrentNumOfPlayers() >= $this->numOfPlayers){  // Gra pełna
                            send("Error 30", [$socket]);
                            continue;
                        }
                        if(empty($args[1])){
                            send("Error 31", [$socket]);
                            continue;
                        }
                
                        $id = array_search($socket, array_column($this->clients, 0));
                        $this->clients[$id][1] = array_shift($this->availableIDs);
                        $this->clients[$id][2] = $args[1]; 
                    }
                    else{
                        if($this->read[$i][1] < 0){
                            send("Error 00", [$socket]);
                            continue;
                        }
                        if(!$this->player($args, $this->read[$i][1], $socket)) continue;
                    }
                }
                file_put_contents("logsKiK2/gameMonitor.txt", "n6gga", FILE_APPEND);     // FILE LOG 
                // Refresh
                $implodedBoard = $this->board->implode();
                for($i = 0; $i < count($this->read); $i++)
                    send("Refresh " . implode(',', array_column($this->clients, 2)) . " " . $implodedBoard . " " .
                         (string)$this->target . " " . (string)$this->turn . " " .(string)$this->read[$i][1], 
                         [$this->read[$i][0]], [$this->server]); 
                
                file_put_contents("logsKiK2/gameMonitor.txt", "n7gga", FILE_APPEND);     // FILE LOG 
            }
        }
    }

    private function admin($args, $socket){
        file_put_contents("logsKiK2/admin.txt", implode(" ", $args), FILE_APPEND);     // FILE LOG
        switch(strtolower($args[0])){
            case self::adminCommands[0]:    // Create
                if(count($args) < 4){
                    send("Error 11", [$socket]);
                    return false;
                }
                if($args[1] < 2 || $args[2] > Game::maxPlayers){
                    send("Error 21", [$socket]);
                    return false;
                }

                try{ $this->board = new Board(explode(',', $args[2]), $args[3]); }
                catch(Exception $e){
                    send("Error 20 " . $e, [$socket]);
                    return false;
                }
                break;

            case self::adminCommands[1]:  // Drop
                send("Drop", $this->mainServer);
                $this->__destruct();
                die();
                break;

            case self::adminCommands[2]:  // Kick
                if(count($args) < 2){
                    send("Error 11", [$socket]);
                    return false;
                }
                if($args[1] < 0 || $args[1] >= $this->numOfIDs || in_array($args[1], $this->availableIDs)){
                    send("Error 90", [$socket]);
                    return false;
                }

                $toKick = array_search($args[1], array_column($this->clients, 1));

                if(empty($toKick) || $toKick < 0 || $toKick >= count($this->clients)){
                    send("Error 90", [$socket]);
                    return false;
                }

                $this->clients = array_slice($this->clients, $toKick, 1);
                array_push($this->availableIDs, $args[1]);
                sort($this->availableIDs);
                break;

            case self::adminCommands[3]:  // Pause
                $this->paused = true;
                for($i = 0; $i < count($this->read); $i++) 
                    send("Paused", [$this->read[$i][0]], []);
                break;

            case self::adminCommands[4]:  // Unpause
                $this->paused = false;
                for($i = 0; $i < count($this->read); $i++) 
                    send("Unpaused", [$this->read[$i][0]], []);
                break;

            case self::adminCommands[5]:  // Ping
                send("Reping", [$socket]);
                break;
            break;

            case "put":
                if(count($args) < 2){
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

            default:
                send("Error 10", [$socket]);
                return false;
                break;
        }
        return true;
    }

    private function player($args, $id, $socket){
        file_put_contents("logsKiK2/player.txt", implode(" ", $args), FILE_APPEND);     // FILE LOG
        switch(strtolower($args[0])){
            case self::commandTypes[0]:  // Put
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

            case self::commandTypes[1]:  // Leave
                $this->clients = array_slice($clients, array_search($socket, array_column($clients, 0)), 1);
                if($id >= 0) $this->availableIDs[] = $id;
                break;

            case self::commandTypes[2]:  // Ping
                send("Reping", [$socket]);
                break;

            case self::commandTypes[3]:  // Reping
                break;

            default:
                send("Error 10", [$socket]);
                return false;
                break;
        }
        return true;
    }

    private function serverHandshake(){
        $this->mainServer = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_nonblock($this->mainServer);
        if (!$this->mainServer) throw new Exception("Nie udało się stworzyć gniazda dla serwera.");

        if (!socket_connect($this->mainServer, "127.0.0.1", $this->mainServerPort)) 
            throw new Exception("Nie udało się połączyć z serwerem: " . socket_strerror(socket_last_error($this->mainServer)));
    
        $key = base64_encode(random_bytes(16));
        $headers = "GET / HTTP/1.1\r\n" .
                   "Host: localhost:{$this->mainServerPort}\r\n" .
                   "Upgrade: websocket\r\n" .
                   "Connection: Upgrade\r\n" .
                   "Sec-WebSocket-Key: {$key}\r\n" .
                   "Sec-WebSocket-Version: 13\r\n\r\n".
                   "websocketTest";
        socket_write($this->mainServer, $headers, strlen($headers));

        // Oczekiwanie na odpowiedź serwera
        socket_set_option($this->mainServer, SOL_SOCKET, SO_RCVTIMEO, ["sec" => 10, "usec" => 0]);
        $response = socket_read($this->mainServer, 2048);
        if ($response === false || strpos($response, "101 Switching Protocols") === false || empty($response)){
            file_put_contents("logsKiK2/gameServerHandshake.txt", "a", FILE_APPEND);     // FILE LOG
            die("Handshake nie powiódł się.");
        }
    }

    private function incrementTurn(){ $this->turn = ($this->turn + 1) % $this->numOfPlayers; }

    function getCurrentNumOfPlayers(){ return $this->numOfIDs - count($this->availableIDs); }
    function getNumOfPlayers(){ return $this->numOfPlayers; }
    function getPort(){ return $this->port; }
    function getBoard(){ return $this->board; }
    function getTurn(){ return $this->turn; }
    function getPaused(){ return $this->paused; }
    function getSocket(){ return $this->server; }
    function getIDs(){ return $this->availableIDs; }
}
?>