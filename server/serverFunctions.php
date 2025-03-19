<?php
const delimeter = " ";  // Separator do explode() i implode()
const commandTypes = [ "create", "ping", "reping", "new" ];

function readMessage($socket){
    try{ 
        $data = socket_read($socket, 2);  // Pobieramy samą długość
        if($data === false || $data === "") return false;
        $payloadLength = ord($data[1]) & 127;  // Długość SAMEGO payload'u
        $offset = 6;
        $masks;

        if($payloadLength === 126){   // Jeśli 126 to kolejne 2 bajty to faktyczna długość payload'u
            $data .= socket_read($socket, 2); 
            $payloadLength = ord($data[0])*256 + ord($data[1]);
            $offset = 8;
        }       
        else if($payloadLength === 127){   // Jeśli 127 to kolejne 8 bajtów to faktyczna długość payload'u
            $data .= socket_read($socket, 8);
            $payloadLength = (ord($data[0]) << 56) | (ord($data[1]) << 48) | (ord($data[2]) << 40) | (ord($data[3]) << 32) |
                            (ord($data[4]) << 24) | (ord($data[5]) << 16) | (ord($data[6]) << 8)  |  ord($data[7]);
            $offset = 14;
        }  

        $data .= socket_read($socket, $payloadLength + 4);  // 4 bajty na maskę
        $masks = substr($data, $offset - 4, 4);

        $payload = substr($data, $offset);
        $decoded = '';

        for ($i = 0; $i < strlen($payload); $i++) 
            $decoded .= $payload[$i] ^ $masks[$i % 4];

        if(empty($decoded) || $decoded === "") return false;
        $decoded = trim($decoded);   // Usuwamy puste znaki na początku

        $args = explode(delimeter, $decoded);
        if(count($args) < 1 || !in_array(strtolower($args[0]), commandTypes)) return false;
        return $args;
    }
    catch(Exception $e){ return false; }
    return false;  // Jeśli dotarliśmy do tego momentu to coś jest nie tak
}

function encodeMessage($message) {
    $message = trim($message);  // Usuwamy spacje na początku
    $length = strlen($message);

    if ($length <= 125) {
        return chr(129) . chr($length) . $message;
    } 
    elseif ($length <= 65535) {
        return chr(129) . chr(126) . pack('n', $length) . $message;
    } 
    else return chr(129) . chr(127) . pack('J', $length) . $message;
}

function send($txt, $sockets, $skip = []){ 
    $message = encodeMessage($txt);
    file_put_contents("logsKiK2/send.txt", $txt, FILE_APPEND);     // FILE LOG
    foreach($sockets as $socket) 
        if(!in_array($socket, $skip) && $socket) {
            file_put_contents("logsKiK2/actuallySend.txt", $txt, FILE_APPEND);     // FILE LOG
            socket_write($socket, $message);
        }
}

function handshake(&$server, &$clients){
    $newClient = socket_accept($server);
    if(!$newClient) return;
    socket_set_nonblock($newClient); 
    $clients[] = $newClient;

    $request = socket_read($newClient, 5000);
    preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $request, $matches);
    file_put_contents("logsKiK2/serverHandshakes.txt", $request, FILE_APPEND);             // FILE LOG

    if(!isset($matches[1]) || empty($matches[1])){
        file_put_contents("error.txt", "mathces Error(server)", FILE_APPEND);
        unsetSocket($newClient, $clients, $server);
        return;
    }
    $matches[1]= rtrim($matches[1], "\r\n");

    $key = base64_encode(pack('H*', sha1($matches[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
    $headers = "HTTP/1.1 101 Switching Protocols\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Version: 13\r\n" .
                "Sec-WebSocket-Accept: $key\r\n\r\n";
    socket_write($newClient, $headers, strlen($headers));
}

function unsetSocket(&$socket, &$clients, &$server){
    try{
        send("Closed", [$socket], [$server]);
        if($socket !== $server){
            socket_shutdown($socket);
            socket_close($socket);
            $clients = array_filter($clients, function ($client) use ($socket) {
                return $client !== $socket;
            });
            $clients = array_values($clients); 
        }
    }
    catch(Exception $e) {}
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