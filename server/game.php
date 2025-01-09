<?php
require "board.php";

class Game{
    private $address;
    private $port;
    private $numOfPlayers = 2;  // Ustawiona liczba graczy
    private $board = new Board([4,4,4],3);  
    private $turn = 0;          // Tura
    private $paused = false;

    private $clients = [$server => [-1, ""]]; // Tablica przechowująca wszystkie gniazda (serwer + klienci), klucz - socket, wartość - [id gracza, nick]
    private $players = [];                    // Dla czytelności 
    private $server;
    private $admin;
    private $read = $clients; // Kopia tablicy klientów do odczytu
    private $write = null;
    private $except = null;

    private const $maxPlayers = 3;          // Maksymalna możliwa do ustawienia liczba graczy
    private const $availableIDs = [0,1,2];  // Kula, krzyżyk, piramida

    private const $commandTypes = [
        "join", "put", "leave", "ping", "reping"
    ];
    
    private const $adminCommands = [
        "create", "drop", "kick", "pause", "unpause", "ping", "reping"
    ];

    function __construct($addr, $prt, $adm, $nOfPlayers, $sizes = [4,4,4], $target = 3){
        if(!filter_var($addr, FILTER_VALIDATE_IP)){}
        if(empty($prt) || $prt < 0 || $prt > 65535){}
        if(empty($nOfPlayers) || $nOfPlayers < 0 || $nOfPlayers > $maxPlayers){}
        if(empty($adm) || !$adm){}

        $this->address = $address;           
        $this->port = $prt;                
        $this->numOfPlayers = $nOfPlayers; 
        $this->admin = $adm;
        $this->board = new Board($sizes, $target);  

        $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($server, $address, $port);
        socket_listen($server); 
    }
       
    private function monitor(){
        while (true) {
            socket_select($read, $write, $except, 0);
            foreach ($read as $socket => $idNick) {
                // Nowe połączenie od klienta
                if ($socket === $server) {
                    $newClient = socket_accept($server);
                    $clients[$newClient] = [-1, ""];
                    handshake($newClient);
                    $read = $clients;
                    continue
                } 
                $data = socket_read($socket, 2048);
                if ($data === false || $data === '') continue;
                $args = decodeMessage($data);
                if(!$args) continue;
                    
                if($socket === $this->admin){  //Admin
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
        
                $players = array_filter($clients, function($client){   
                    return isset($client[0]) && $client[0] >=0 && $clients[0] <= $maxPlayers - 1
                });
        
                // Refresh
                foreach($read as $client => $idNick)
                    send("Refresh" . implode(',', $players) . $board->implode() . (string)$target . (string)$turn . (string)$idNick[0], [$client], [$server]);
                }
            }
        }
    }

    private function admin($args$, $socket){
        switch(strtolower($args[0])){
            case $adminCommands[0]:    //Create
                if(count($args < 4)){
                    send("Error 11", [$socket]);
                    return false;
                }
                if($args[1] < 2 || $args[2] > $maxPlayers){
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
                foreach($read as $client => $idNick) send("Closed", $client, $socket);
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

                $toKick = array_filter($clients, function($client) use($args){
                    return isset($client[0]) && $client[0] === $args[1]
                });
                if(empty($toKick)){
                    send("Error 90", [$socket]);
                    return false;
                }
                foreach($read as $client => $idNick)
                    if(in_array($client, $toKick)){ 
                        send("Kicked", [$client]);
                        unset($clients[$client]);
                    }

                array_push($availableIDs, $args[1]);
                $read = $clients;
                break;

            case $adminCommands[3]:  //Pause
                $paused = true;
                foreach($read as $client => $idNick) send("Paused", [$client], $socket);
                break;

            case $adminCommands[4]:  //Unpause
                $paused = false;
                foreach($read as $client => $idNick) send("Unpaused", [$client], $socket);
                break;

            case $adminCommands[5]:  //Ping
                send("Ping", [$socket]);
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
            case $commandTypes[0]:  //Join
                if(count($args) < 2){
                    send("Error 11", [$socket]);
                    return false;
                }
                if(count(array_filter($clients, function($client){   //Gra pełna
                    return isset($client[0]) && $client[0] >=0 && $clients[0] <= $maxPlayers - 1
                })) >= $numOfPlayers){
                    send("Error 30", [$socket]);
                    return false;
                }
                if(empty($args[1])){
                    send("Error 31", [$socket]);
                    return false;
                }

                $clients[$socket] = [$availableIDs[0], $args[1]];  
                array_slice($availableIDs, 0, 1); 
                $read = $clients;
                break;

            case $commandTypes[1]:  //Put
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

            case $commandTypes[2]:  //Leave
                unset($clients[$socket]);
                if($id >= 0) $availableIDs[] = $id;
                $read = $clients;
                break;

            case $commandTypes[3]:  //Ping
                send("Reping", [$socket]);
                break;

            default:
                send("Error 10", $socket);
                return false;
                break;
        }
    }

    private function incrementTurn(){
        $this->turn = $this->turn > $numOfPlayers - 1 ? 0 : $this->turn + 1;
    }


    function encodeMessage($message) {
        $length = strlen($message);
    
        if ($length <= 125) {
            return chr(129) . chr($length) . $message;
        } 
        elseif ($length <= 65535) {
            return chr(129) . chr(126) . pack('n', $length) . $message;
        } 
        else return chr(129) . chr(127) . pack('J', $length) . $message;
    }

    function decodeMessage($data) {
        $length = ord($data[1]) & 127;
    
        if ($length === 126) {
            $masks = substr($data, 4, 4);
            $payloadOffset = 8;
        } 
        elseif ($length === 127) {
            $masks = substr($data, 10, 4);
            $payloadOffset = 14;
        } 
        else {
            $masks = substr($data, 2, 4);
            $payloadOffset = 6;
        }
    
        $payload = substr($data, $payloadOffset);
        $decoded = '';
    
        for ($i = 0; $i < strlen($payload); $i++) 
            $decoded .= $payload[$i] ^ $masks[$i % 4];
    
        if(empty($decoded) || $decoded = "") return false;
        $this->args = explode($decoded);
        if(count($this->args) < 1 || !in_array(strtolower($this->args[0]), $this->commandTypes)) return false;
        return $args;
    }

    function handshake($newClient){
        $request = socket_read($newClient, 5000);
        preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $request, $matches);
        $key = base64_encode(pack('H*', sha1($matches[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $headers = "HTTP/1.1 101 Switching Protocols\r\n";
        $headers .= "Upgrade: websocket\r\n";
        $headers .= "Connection: Upgrade\r\n";
        $headers .= "Sec-WebSocket-Version: 13\r\n";
        $headers .= "Sec-WebSocket-Accept: $key\r\n\r\n";
        socket_write($newClient, $headers, strlen($headers));
    }
    
    function send($txt, $sockets, $skip = []){ 
        $message = encodeMessage($txt);
        foreach($sockets as $socket){
            if($socket !== $server && !in_array($socket, $skip))
                socket_write($socket, $message);
        }
    }

    function getPort(){ return $this->port;}
    function getNumOFPlayers(){ return $this->numOfPlayers;}
    function getBoard(){ return $this->board;}
    function getTurn(){ return $this->turn;}
    function getPaused(){ return $this->paused;}
    function getMaxPlayers(){ return $this->maxPlayers;}
}
?>