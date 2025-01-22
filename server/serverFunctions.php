<?php
const delimeter = " ";  // Separator do explode() i implode()
const commandTypes = [
    "join", "create", "ping", "reping"
];

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
    foreach($sockets as $socket) 
        if(!in_array($socket, $skip)) 
            socket_write($socket, $message);
}
?>