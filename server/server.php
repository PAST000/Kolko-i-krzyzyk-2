<?php
require "game.php";

$address = '0.0.0.0';
$port = 3310;
$gamePort = 33100;
$freePorts = [];   // Porty które były używane, ale nie są obecnie

$server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($server, $address, $port);
socket_set_nonblock($server);
socket_listen($server);

$clients = [$server];
$read = [];
$games = [];          // Port => Gra
$gamesSockets = [];   // Port => Socket, nie łączymy z tablicą powyżej w celu przeszukiwania

function close(){
    foreach($games as $game) { $game->__destruct(); }
    foreach($clients as $client) { 
        send("Closed", [$client], [$server]);
        if($client !== $server) socket_close($client);
    }
    socket_close($server);
    exit();
}
register_shutdown_function('close');

while (true) {
    $read = $clients;
    $write = null;
    $except = null;

    try{
        socket_select($read, $write, $except, 0);
    }
    catch(Exception $e){ continue; }

    foreach ($read as $socket) {
        if(!$socket && $socket !== $server){
            file_put_contents("close.txt", "closed", FILE_APPEND);
            array_slice($clients, array_search($socket, $clients), 1);
            continue;
        } 

        if ($socket === $server) {   // Nowe połączenie
            $newClient = socket_accept($server);
            if(!$newClient) continue;
            socket_set_nonblock($newClient);
            $clients[] = $newClient;

            $request = socket_read($newClient, 5000);
            file_put_contents("requests.txt", $request, FILE_APPEND);
            preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $request, $matches);

            if(!isset($matches[1])){
                file_put_contents("close.txt", "NOTFOUND", FILE_APPEND);
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
            continue;
        } 

        $args = readMessage($socket);
        if($args === false || empty($args) || $args === "" || !isset($args[0])) continue;
        
        file_put_contents("messages.txt", implode(" ", $args), FILE_APPEND);

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
            case commandTypes[0]:  //Join
                if(!isset($args[1]) || !isset($games[$args[1]])){
                    send("Error 32", [$socket]);
                    break;
                }
                if(!$games[$args[1]]->join($socket, $args[2])){
                    send("Error 31", [$socket]);
                }

                send("Joined", [$socket]);
                $clients = array_diff($clients, [$socket]);  // Usuwamy socket, klient łączy się od teraz z grą, nie z serwerem
                break;
                
            case commandTypes[1]:  //Create
                if($gamePort > 65535 && empty($freePorts)){
                    send("Error 41 " . $e, [$socket]);
                    break;
                }
                try{
                    $prt = $gamePort;
                    if(empty($freePorts)) $gamePort++;
                    else $prt = array_shift($freePorts);

                    $games[$prt] = new Game($address, $prt, $socket, (int)$args[1], $args[2], (int)$args[3]);
                    $gamesSockets[$prt] = $games[$prt]->getSocket();
                }
                catch(Exception $e){
                    send("Error 40 " . $e, [$socket]);
                    break;
                }

                send("Port " . (string)$prt, [$socket]);
                $clients = array_diff($clients, [$socket]);  // Usuwamy socket, klient łączy się od teraz z grą, nie z serwerem
                break;

            case commandTypes[2]:
                send("Reping", [$socket]);
                break;

            case commandTypes[3]:
                break;

            default:
                send("Error 10", [$socket]);
                break;
        }
    }
}
?>