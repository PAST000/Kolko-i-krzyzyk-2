<?php
const $commandTypes = [
    "join", "put", "leave"
];

const $adminCommands = [
    "create", "drop", "pause", "kick"
];

function encodeMessage($message) {
    $length = strlen($message);

    if ($length <= 125) {
        return chr(129) . chr($length) . $message;
    } elseif ($length <= 65535) {
        return chr(129) . chr(126) . pack('n', $length) . $message;
    } else {
        return chr(129) . chr(127) . pack('J', $length) . $message;
    }
}

function decodeMessage($data) {
    $length = ord($data[1]) & 127;

    if ($length === 126) {
        $masks = substr($data, 4, 4);
        $payloadOffset = 8;
    } elseif ($length === 127) {
        $masks = substr($data, 10, 4);
        $payloadOffset = 14;
    } else {
        $masks = substr($data, 2, 4);
        $payloadOffset = 6;
    }

    $payload = substr($data, $payloadOffset);
    $decoded = '';

    for ($i = 0; $i < strlen($payload); $i++) {
        $decoded .= $payload[$i] ^ $masks[$i % 4];
    }

    if(empty($decoded) || $decoded = ""){
        return false;
    }

    $this->args = explode($decoded);
    if(count($this->args) < 1 || !in_array(strtolower($this->args[0]), $this->commandTypes)){
        return false;
    }
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


function createMessage($commandType, $args){
    if(strlne($commandType) < 3) return false;
    $commandType = strtolower($commandType);
    $commandType[0] = strtoupper($commandType[0]);

    $message = $commandType;
    foreach($args as $arg){
        $message .= $arg;
        $message .= ' ';
    }
    return message;
}

function send($txt, $sockets, $skip = []){ 
    $message = encodeMessage($txt);
    foreach($sockets as $socket){
        if($socket !== $server && !in_array($socket, $skip))
            socket_write($socket, $message);
    }
}
?>