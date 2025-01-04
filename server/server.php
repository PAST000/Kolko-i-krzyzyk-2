<?php
require "serverFunctions.php";
$address = '0.0.0.0';
$port = 3310;
$paused = false;
const $maxPlayers = 3;

$board = new Board([4,4,4],3);
$numOfPlayers = 2;
$availableIDs = [1,2,3];

$server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($server, $address, $port);
socket_listen($server);

$clients = [$server]; // Tablica przechowująca wszystkie gniazda (serwer + klienci)
$players = [];        // Gracze  [id, nick]

while (true) {
    $read = $clients; // Kopia tablicy klientów do odczytu
    $write = null;
    $except = null;

    // Monitorowanie aktywnych gniazd
    socket_select($read, $write, $except, 0);

    foreach ($read as $socket) {
        // Nowe połączenie od klienta
        if ($socket === $server) {
            $newClient = socket_accept($server);
            $clients[] = $newClient;
            handshake($newClient);
        } 
        else {
            $data = socket_read($socket, 2048);
            if ($data === false || $data === '') continue;
            $args = decodeMessage($data);
            if(!$args) continue;
            
            if($socket === $clients[0]){  //Admin
                switch(strtolower($args[0])){
                    case $adminCommands[0]:    //Create
                        if(count($args >= 4)){
                            try{
                                $board = new Board($args[2], $args[3]);
                                if($args[1] < 2 || $args[2] > $maxPlayers)
                                    send("Error 21", [$socket]);
                            }
                            catch(Exception $e){
                                send("Error 20 " . $e, [$socket]);
                            }
                        }
                        else send("Error 11", [$socket]);
                        break;

                    case $adminCommands[1]:  //Drop
                        foreach($read as $client)
                            send("Closed", $client);
                        exit();
                        break;

                    case $adminCommands[2]:  //Pause
                        $paused = true;
                        break;

                    case $adminCommands[3]:  //Kick
                        if(count($args) < 2) send("Error 11", [$socket]);
                        if($args[1] < 0 || $args[1] > 3 || in_array($args[1], $availableIDs)) send("Error 90", [$socket]);
                        unset($players[$args[1]]);
                        array_push($args[1]);
                        break;

                    default:
                        send("Error 10", $socket);
                        break;
                }
            }
            else{
                if($paused && $args[0] != $commandTypes[2]) continue; //Wyjątek - opuszczenie gry
                switch(strtolower($args[0])){
                    case $commandTypes[0]:  //join
                        if(count($args) >= 2){
                            if(count($players) >= 3){
                                send("Error 30", [$socket]);
                            }
                            else{
                                $players[$availableIDs[0]] = $args[1];   //TODO
                                array_aplice($availableIDs, 0, 1);
                            }
                        }
                        else send("Error 11", [$socket]);
                        break;
                    case $commandTypes[1]:  //put
                            if(count($args) >= 4){

                            }
                            else send("Error 11", [$socket]);   
                        break;
                    case $commandTypes[2]:  //leave
                        break;
                    default:
                        send("Error 10", $socket);
                        break;
                }
            }

            // Odpowiedź do wszystkich klientów
            send("Serwer mówi: " . implode($args), $clients, [$server, $socket]);
        }
    }
}