<?php
function readMessage($socket){
    $data = socket_read($socket, 6);
    if(is_numeric($data)) $data = socket_read($socket, (int)$data);
    else $data .= socket_read($socket, 4090); //Domyślnie odczytujemy 4096 bajtów, z czego 6 już odczytaliśmy
    
    if($data === false || $data === "") return false;
    return decodeMessage($data);
}

function encodeMessage($message) {
    $length = strlen($message);
    $message = ltrim($message);  //WAŻNE! usuwamy spacje na początku
    $message = rtrim($message);

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
    $data = ltrim($data);   //WAŻNE! usuwamy spacje na początku
    $data = rtrim($data);

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
    $len = encodeLength((string)strlen($message));

    foreach($sockets as $socket) 
        if($socket !== $server && !in_array($socket, $skip)) 
            socket_write($len . "  " . $socket, $message);
}

function encodeLength($txt){
    $len = strlen($txt);
    $encoded = "";

    for($i = 1; $i <= $maxMessLen; $i++){
        if($i > $len) $encoded = "0" . $encoded;
        else $encoded = $len[$len - $i] . $encoded;
    }
    return $encoded;
}
?>