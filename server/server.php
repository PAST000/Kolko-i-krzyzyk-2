<?php
require "serverFunctions.php";
require "game.php";

$address = '0.0.0.0';
$port = 3310;

$gamePort = 33100;
$freePorts = [];   //Porty które były używane, ale nie są obecnie

const $commandTypes = [
    "join", "create", "ping", "reping"
];

$server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($server, $address, $port);
socket_listen($server);

$clients = [$server];
$games = [];          // Port => Gra
$gamesSockets = [];   // Port => Socket, nie łączymy z tablicą powyżej w celu przeszukiwania

while (true) {
    $read = $clients;
    $write = null;
    $except = null;

    socket_select($read, $write, $except, 0);

    foreach ($read as $socket => $idNick) {
        if ($socket === $server) {            //Nowe połączenie
            $newClient = socket_accept($server);
            $clients[] = $newClient;
            handshake($newClient);
            $read = $clients;
            continue;
        } 

        $args = readMessage($socket);
        if($args === false || empty($args) || $args === "" || !isset($args[0])) continue;

        if(in_array($socket, $gamesSockets)){  //Wiadomość od gry
            switch(strtolower($args[0])){
                case "drop":
                    $prt = array_search($socket, $gamesSockets);
                    unset($games[$prt]);
                    unset($gamesSocket[$prt]);
                    $freePorts[] = $prt;
                    break;
            }
            continue;
        }

        switch(strtolower($args[0])){
            case $commandTypes[0]:  //Join
                if(!isset($args[1]) || !isset($games[$args[1]])){
                    send("Error 32", [$socket]);
                    break;
                }
                if(!$games[$args[1]]->join($socket, $args[2])){
                    send("Error 31", [$socket]);
                }

                send("Joined", [$socket]);
                $clients = array_diff($clients, [$socket]);  // Usuwamy socket, klient łączy się od teraz z grą, nie z serwerem
                $read = $clients;
                break;
                
            case $commandTypes[1]:  //Create
                if($gamePort > 65535 && empty($freePorts)){
                    send("Error 41 " . $e, [$socket]);
                    break;
                }
                try{
                    $prt = $gamePort;
                    if(empty($freePorts)) $gamePort++;
                    else $prt = array_shift($freePorts);

                    $games[$prt] = new Game($address, $prt, $socket, $args[1], $args[2], $args[3]);
                    $gamesSockets[$prt] = $games[$prt]->getSocket();
                }
                catch(Exception $e){
                    send("Error 40 " . $e, [$socket]);
                    break;
                }

                send("Port " . (string)$prt, [$socket]);
                $clients = array_diff($clients, [$socket]);  // Usuwamy socket, klient łączy się od teraz z grą, nie z serwerem
                $read = $clients;
                break;

            case $commandTypes[2]:
                send("Reping", [$socket]);
                break;

            case $commandTypes[3]:
                break;

            default:
                send("Error 10", $socket);
                break;
        }
    }
}
?>