<?php
const $commandTypes = [
    "join", "put", "leave"
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
?>