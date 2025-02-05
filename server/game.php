<?php
require "board.php";
require "serverFunctions.php";
require "ObjectsArr.php";

class Game{
    private $address;
    private $port;
    private $numOfPlayers = 2;  // Ustawiona liczba graczy
    private $board;  
    private $turn = 0;          // Tura
    private $paused = false;

    private $clients;       // Tablica przechowująca wszystkie gniazda (serwer + klienci), klucz -> socket, wartość -> [id gracza, nick]
    private $players = [];  // Dla czytelności 
    private $server;
    private $admin;
    private $read;          // Kopia tablicy klientów do odczytu
    private $write = null;
    private $except = null;

    const maxPlayers = 3;          // Maksymalna możliwa do ustawienia liczba graczy
    const availableIDs = [0,1,2];  // Kula, krzyżyk, piramida

    private const commandTypes = [
        "put", "leave", "ping", "reping"
    ];
    
    private const adminCommands = [
        "create", "drop", "kick", "pause", "unpause", "ping", "reping"
    ];

    function __construct($addr, $prt, $adm, $nOfPlayers, $sizes = "4,4,4", $target = 3){
        if(!filter_var($addr, FILTER_VALIDATE_IP)) throw new Exception("Nie poprawny adres");
        if(empty($prt) || $prt < 0 || $prt > 65535) throw new Exception("Nie poprawny port");
        if(empty($nOfPlayers) || $nOfPlayers < 0 || $nOfPlayers > Game::maxPlayers) throw new Exception("Nie poprawna ilość graczy, maksymalna to: " . (string)Game::maxPlayers);
        if(empty($adm) || !$adm) throw new Exception("Administrator jest wymagany");

        $this->address = $addr;           
        $this->port = $prt;                
        $this->numOfPlayers = $nOfPlayers; 
        $this->admin = $adm;
        $this->board = new Board(explode(',', $sizes), $target);  

        $this->server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->server, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->server, $addr, $prt);
        socket_listen($this->server); 
        socket_set_nonblock($this->server);

        $this->clients = new ObjectsArr();
        $this->clients->add($this->server, [-1, ""]);
        $this->read = $this->clients;

        $this->monitor();
    }
       
    function __destruct(){
        $this->board->__destruct();
        $sockets = $this->clients->getFirstArr();
        foreach($sockets as $client){ 
            send("Closed", [$client], [$this->server]);
            if($client !== $this->server) socket_close($client);
        }
        socket_close($server);
        exit();
    }

    private function monitor(){
        while (true) {
            $this->read = $this->clients;
            $readArr = $this->read->getFirstArr();

            socket_select($readArr, $write, $except, 0);
            file_put_contents("game.txt", "GAME", FILE_APPEND);

            for ($i = 0; $i < $this->read->count(); $i++) {
                $socket = $this->read->firstByID($i);
                $idNick = $this->read->secondByID($i);
                if ($socket === $this->server) continue;

                $args = readMessage($socket);
                if($args === false || empty($args) || $args === "") continue;
                    
                if($socket === $admin){  //Admin
                    if(!$this->admin($args, $socket)) continue;
                }
                else{
                    if($idNick[0] < 0){
                        send("Error 00", [$socket]);
                        continue;
                    }
                    if($paused && $args[0] != $commandTypes[2]) continue; //Wyjątek - opuszczenie gry
                    if(!$this->player($args, $idNick[0], $socket)) continue;
                }
        
                $players = array_filter($this->clients->getFirstArr(), function($idN){   
                    return isset($idN[0]) && $idN[0] >=0 && $idN[0] <= maxPlayers - 1;
                });
        
                //Refresh
                for($i = 0; $i < $this->read->count(); $i++){
                    $sckt = $this->read->firstByID($i);
                    $idN = $second->firstByID($i);
                    send("Refresh" . implode(',', $this->players) . $this->board->implode() . (string)$target . (string)$turn . (string)$idN[0], [$sckt], [$this->server]); 
                }   
            }
        }
    }

    private function admin($args, $socket){
        switch(strtolower($args[0])){
            case $adminCommands[0]:    //Create
                if(count($args < 4)){
                    send("Error 11", [$socket]);
                    return false;
                }
                if($args[1] < 2 || $args[2] > maxPlayers){
                    send("Error 21", [$socket]);
                    return false;
                }
                try{
                    $board = new Board($args[2], $args[3]);
                }
                catch(Exception $e){
                    send("Error 20 " . $e, [$socket]);
                    return false;
                }
                break;

            case $adminCommands[1]:  //Drop
                while($this->read->count() > 0){
                    $sckt = $this->read->firstByID($i);
                    send("Closed", [$sckt], [$this->server]);
                    socket_close($sckt);
                    $this->read->delByID(0);
                }
                exit();
                break;

            case $adminCommands[2]:  //Kick
                if(count($args) < 2){
                    send("Error 11", [$socket]);
                    return false;
                }
                if($args[1] < 0 || $args[1] > 3 || in_array($args[1], $availableIDs)){
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
                    if(in_array($arr[$i], $toKick)){
                        send("Kicked", [$this->read->firstByID($i)]);
                        $this->read->delByID($i);
                        break;  
                    }
                array_push($availableIDs, $args[1]);
                break;

            case $adminCommands[3]:  //Pause
                $paused = true;
                foreach($this->read->getFirstArr() as $client) send("Paused", [$client], [$socket]);
                break;

            case $adminCommands[4]:  //Unpause
                $paused = false;
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
    }

    private function player($args, $id, $socket){
        switch(strtolower($args[0])){
            case $commandTypes[0]:  //Put
                    if(count($args) < 4){
                        send("Error 11", [$socket]);
                        return false;
                    }
                    if($id !== turn){
                        send("Error 23", [$socket]);
                        return false;
                    }

                    if(!$board->put($args[1], $id)){
                        send("Error 22", [$socket]);
                        return false;
                    }
                    $this->incrementTurn();
                break;

            case $commandTypes[1]:  //Leave
                $this->clients->delByFirst($socket);
                if($id >= 0) $availableIDs[] = $id;
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

    function join($socket, $nick){
        if(count(array_filter($this->clients->getSecondArr(), function($idN){   //Gra pełna
            return isset($idN[0]) && $idN[0] >=0 && $idN[0] <= maxPlayers - 1;
        })) >= $numOfPlayers){
            return false;
        }
        if(empty($nick) || $nick === false) return false;

        $this->clients->add($socket, [$availableIDs[0], $nick]);
        array_slice($availableIDs, 0, 1); 
    }

    private function incrementTurn(){
        $this->turn = $this->turn > $numOfPlayers - 1 ? 0 : $this->turn + 1;
    }

    function getPort(){ return $this->port;}
    function getNumOFPlayers(){ return $this->numOfPlayers;}
    function getBoard(){ return $this->board;}
    function getTurn(){ return $this->turn;}
    function getPaused(){ return $this->paused;}
    function getSocket(){ return $this->$server;}
}
?>