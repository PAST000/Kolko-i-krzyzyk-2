<?php
require "game.php";
// argumenty: portSerwera, portGry, liczbaGraczy, rozmiary, cel
// rozmiary to ciąg znaków, wartości są rozdzielone przecinkami

if ($argc < 5) { die("Zbyt mało argumentów"); }

$address = '127.0.0.1';
$mainPort = (int)$argv[1];
$port = (int)$argv[2]; 
$numOfPlayers = (int)$argv[3];
$sizes = $argv[4];
$target = (int) $argv[5];
$game = new Game($address, $mainPort, $port, $numOfPlayers, $sizes, $target);

register_shutdown_function(function() use (&$game) {
    if (isset($game)) {
        $game->__destruct();
    }
});

try { $game->monitor(); } 
catch (Exception $e) { file_put_contents("create_error.txt", "Błąd gry: " . $e->getMessage(), FILE_APPEND); }
file_put_contents("logsKiK2/createGame.txt", "b", FILE_APPEND);     // FILE LOG
?>