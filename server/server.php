<?php
require "serverFunctions.php";
require "game.php";

$address = '0.0.0.0';
$port = 3310;
$paused = false;
const $maxPlayers = 3;     // Maksymalna możliwa do ustawienia liczba graczy

$board = new Board([4,4,4],3);
$numOfPlayers = 2;       // Ustawiona liczba graczy
$availableIDs = [0,1,2]; // Kula, krzyżyk, piramida
$turn = 0;               // Tura

$server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($server, $address, $port);
socket_listen($server);

$clients = [$server => [-1, ""]]; // Tablica przechowująca wszystkie gniazda (serwer + klienci), klucz - socket, wartość - [id gracza, nick]
$players = [];                    // Dla czytelności          

while (true) {
    $read = $clients; // Kopia tablicy klientów do odczytu
    $write = null;
    $except = null;

    // Monitorowanie aktywnych gniazd
    socket_select($read, $write, $except, 0);

    foreach ($read as $socket => $idNick) {
        // Nowe połączenie od klienta
        if ($socket === $server) {
            $newClient = socket_accept($server);
            $clients[$newClient] = [-1, ""];
            handshake($newClient);
            $read = $clients;
        } 
        else {
            $data = socket_read($socket, 2048);
            if ($data === false || $data === '') continue;
            $args = decodeMessage($data);
            if(!$args) continue;
            
            if($socket === array_key_first($clients)){  //Admin
                switch(strtolower($args[0])){
                    case $adminCommands[0]:    //Create
                        if(count($args < 4)){
                            send("Error 11", [$socket]);
                            continue;
                        }
                        if($args[1] < 2 || $args[2] > $maxPlayers){
                            send("Error 21", [$socket]);
                            continue;
                        }
                        try{
                            $board = new Board($args[2], $args[3]);
                        }
                        catch(Exception $e){
                            send("Error 20 " . $e, [$socket]);
                        }
                        break;

                    case $adminCommands[1]:  //Drop
                        foreach($read as $client => $idNick) send("Closed", $client, $socket);
                        exit();
                        break;

                    case $adminCommands[2]:  //Kick
                        if(count($args) < 2){
                            send("Error 11", [$socket]);
                            continue;
                        }
                        if($args[1] < 0 || $args[1] > 3 || in_array($args[1], $availableIDs)){
                            send("Error 90", [$socket]);
                            continue;
                        }
     
                        $toKick = array_filter($clients, function($client) use($args){
                            return isset($client[0]) && $client[0] === $args[1]
                        });
                        if(empty($toKick)){
                            send("Error 90", [$socket]);
                            continue;
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
                        continue;
                        break;
                }
            }
            else{
                if($idNick[0] < 0){
                    send("Error 00", [$socket]);
                    continue;
                }
                if($paused && $args[0] != $commandTypes[2]) continue; //Wyjątek - opuszczenie gry
                switch(strtolower($args[0])){
                    case $commandTypes[0]:  //Join
                        if(count($args) < 2){
                            send("Error 11", [$socket]);
                            continue;
                        }
                        if(count(array_filter($clients, function($client){   //Gra pełna
                            return isset($client[0]) && $client[0] >=0 && $clients[0] <= $maxPlayers - 1
                        })) >= $numOfPlayers){
                            send("Error 30", [$socket]);
                            continue;
                        }
                        if(empty($args[1])){
                            send("Error 31", [$socket]);
                            continue;
                        }

                        $clients[$socket] = [$availableIDs[0], $args[1]];  
                        array_slice($availableIDs, 0, 1); 
                        $read = $clients;
                        break;

                    case $commandTypes[1]:  //Put
                            if(count($args) < 4){
                                send("Error 11", [$socket]);
                                continue;
                            }
                            if(!$board->put($args[1], $idNick[0])) send("Error 22", [$socket]);
                        break;

                    case $commandTypes[2]:  //Leave
                        unset($clients[$socket]);
                        if($idNick[0] >= 0) $availableIDs[] = $idNick[0];
                        $read = $clients;
                        break;

                    case $commandTypes[3]:  //Ping
                        send("Reping", [$socket]);
                        break;

                    default:
                        send("Error 10", $socket);
                        continue;
                        break;
                }
            }

            $players = array_filter($clients, function($client){   
                return isset($client[0]) && $client[0] >=0 && $clients[0] <= $maxPlayers - 1
            });

            // Refresh
            foreach($read as $client => $idNick){
                send("Refresh" . implode(',', $players) . $board->implode() . (string)$target . (string)$turn . (string)$idNick[0], [$client], [$server]);
            }
        }
    }
}
?>