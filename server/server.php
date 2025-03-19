<?php
require "game.php";
use parallel\Runtime;
use parallel\Channel;

register_shutdown_function('closeServer');

$address = '0.0.0.0';
$port = 3310;
$gamePort = 49152;
$freePorts = [];   // Porty które były używane, ale nie są obecnie

$server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if (!$server) die("Błąd tworzenia gniazda: " . socket_strerror(socket_last_error()));
socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
if (!socket_bind($server, $address, $port)) die("Błąd bindowania: " . socket_strerror(socket_last_error($server)));
socket_set_nonblock($server);
socket_listen($server);

$clients = [$server];
$read = [];
$games = [];         // Port => wątek
$gamesSockets = [];  // Port => socket
$channel = Channel::make("gamesChannel", Channel::Infinite);

while (true) {
    $read = $clients;
    $write = null;
    $except = null;
    $errorCode = null;
  
    try{ socket_select($read, $write, $except, 0); }
    catch(Exception $e){ continue; }

    foreach ($read as $socket) {
        $errorCode = $socket ? socket_last_error($socket) : socket_last_error();
        socket_clear_error($socket);
        if($errorCode !== 0){
            $errorMsg = socket_strerror($errorCode);
            $logMessage = date("Y-m-d H:i:s",time()) . " - [$errorCode] $errorMsg\n";
            file_put_contents("logsKiK2/socket_errors.log", $errorCode . " " . $logMessage, FILE_APPEND); 
        }
 
        if(!$socket && ($socket !== $server)){
            unsetSocket($socket, $clients, $server);
            continue;
        } 
        if ($socket === $server) {   // Nowe połączenie
            handshake($server, $clients);
            continue;
        } 

        $args = readMessage($socket);
        if($args === false || empty($args) || $args === "" || !isset($args[0])) continue;
        
        file_put_contents("logsKiK2/serverMessages.txt", implode(" ", $args), FILE_APPEND);               // FILE LOG

        if(in_array($socket, $gamesSockets)){  // Wiadomość od gry
            switch(strtolower($args[0])){
                case "drop":
                    $gamePort = array_search($socket, $gamesSockets);
                    $freePorts[] = $gamePort;
                    $games[$gamePort]->cancel();
                    unsetSocket($socket);
                    unset($games[$gamePort]);
                    unset($gamesSockets[$gamePort]);
                    break;
            }
            continue;
        }

        switch(strtolower($args[0])){  
            case commandTypes[0]:  // Create
                if($gamePort > 65535 && empty($freePorts)){
                    send("Error 41 ", [$socket]);
                    break;
                }
                try{
                    $gamePort = $gamePort + 1;
                    if(!empty($freePorts)) 
                        $gamePort = array_shift($freePorts); 
                    else
                        while(isPortInUse($address, $gamePort) && $gamePort <= 65535) 
                            $gamePort++;

                    if($gamePort > 65535){
                        send("Error 42 ", [$socket]);
                        return;
                    }
                    
                    $cmd = "php " . escapeshellarg(__DIR__ . DIRECTORY_SEPARATOR . "createGame.php") . " $port " . " $gamePort " . 
                           escapeshellarg($args[1]) .  " " . escapeshellarg($args[2]) . " " . escapeshellarg($args[3]);

                    if (PHP_OS_FAMILY === 'Windows') $cmd = "start /B " . $cmd;
                    else $cmd .= " > /dev/null 2>&1 &";

                    $task = new Runtime();
                    $games[$gamePort] = $task->run(function() use ($cmd) { exec($cmd);});
                }
                catch(Exception $e){
                    send("Error 40 " . $e, [$socket]);
                    break;
                }

                // Zwracamy port nowej gry i zamykamy połączenie
                send("Port " . (string)$gamePort, [$socket]);
                unsetSocket($socket, $clients, $server);  // Usuwamy socket, klient łączy się od teraz z grą, nie z serwerem
                break;

            case commandTypes[1]:  // Ping
                send("Reping", [$socket]);
                break;

            case commandTypes[2]:  // Reping
                break;
                
            case commandTypes[3]:  // New
                file_put_contents("logsKiK2/new.txt", "t1", FILE_APPEND);
                if(empty($args[1]) || !isset($args[2])){
                    send("Error 50", [$socket]);
                    break;
                }
                file_put_contents("logsKiK2/new2.txt", "t2", FILE_APPEND);
                $gamesSockets[$args[1]] = $socket;
                break;
            default:
                send("Error 10", [$socket]);
                break;
        }
    }
}

function closeServer(){
    global $gamesSockets, $clients, $server;
    foreach($gamesSockets as $socket) { unsetSocket($socket); }
    foreach($clients as $client) unsetSocket($client, $clients, $server);
    socket_close($server);
    die(date("Y-m-d H:i:s",time()) . " Zamknięto server.");
}
?>