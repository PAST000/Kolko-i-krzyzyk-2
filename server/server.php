<?php
require "serverFunctions.php";
$address = '0.0.0.0';
$port = 3310;

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

            // Handshake z nowym klientem
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
        else {
            // Odczyt wiadomości od istniejącego klienta
            $data = socket_read($socket, 2048);
            if ($data === false || $data === '') {
                continue;
            }

            // Dekodowanie i przetwarzanie wiadomości
            $args = decodeMessage($data);
            if(!$args){
                continue;
            }

            switch(strtolower($args[0])){
                case $commandTypes[0]:  //join
                    if(count($args) >= 2){
                        if(count($players) >= 3){}
                        else{
                            $players[count($players)] = $args[1];
                        }
                    }
                    else{}
                    break;
                case $commandTypes[1]:  //put

                    break;
                case $commandTypes[2]:  //leave
                    break;
                default:
                    break;
            }

            // Odpowiedź do wszystkich klientów
            $response = encodeMessage("Serwer mówi: $message");
            foreach ($clients as $client) {
                if ($client !== $server && $client !== $socket) {
                    socket_write($client, $response);
                }
            }
        }
    }
}
