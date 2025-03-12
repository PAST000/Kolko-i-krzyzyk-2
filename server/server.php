<?php
require "game.php";
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
$games = [];         // Port => Socket
$gamesSockets = [];  // Port => Socket, nie łączymy z tablicą powyżej w celu przeszukiwania

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
        $errorMsg = socket_strerror($errorCode);
        $logMessage = date("Y-m-d H:i:s",time()) . " - [$errorCode] $errorMsg\n";
        file_put_contents("socket_errors.log", $logMessage, FILE_APPEND);                 // FILE LOG
 
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
        
        file_put_contents("messages.txt", implode(" ", $args), FILE_APPEND);               // FILE LOG

        if(in_array($socket, $games)){  // Wiadomość od gry
            switch(strtolower($args[0])){
                case "drop":
                    $gamePort = array_search($socket, $games);
                    unset($games[$gamePort]);
                    $freePorts[] = $gamePort;
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
                        while(isPortInUse($address, $gamePort) && $gamePort <= 65535){ $gamePort++;}
                    if($gamePort > 65535){
                        send("Error 42 ", [$socket]);
                        return;
                    }

                    $cmd = "php " . escapeshellarg(__DIR__ . DIRECTORY_SEPARATOR . "createGame.php") . " $port " . " $gamePort " . 
                           escapeshellarg($args[1]) .  " " . escapeshellarg($args[2]) . " " . escapeshellarg($args[3]);

                    if (PHP_OS_FAMILY === 'Windows') $cmd = "start /B " . $cmd;
                    else $cmd .= " > /dev/null 2>&1 &";
                    exec($cmd);
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

            default:
                send("Error 10", [$socket]);
                break;
        }
    }
}

function closeServer(&$clients, &$server){
    foreach($games as $game) { $game->__destruct(); }
    foreach($clients as $client) unsetSocket($client, $clients, $server);
    socket_close($server);
    die(date("Y-m-d H:i:s",time()) . " Zamknięto server.");
}

function isPortInUse($host, $port) {
    $connection = @fsockopen($host, $port, $errno, $errstr, 0.5); 
    if ($connection) {
        fclose($connection);  
        return true; 
    }
    return false;  
}
?>