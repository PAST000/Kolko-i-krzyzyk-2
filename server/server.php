<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\Factory;
use Symfony\Component\Process\Process;

require 'vendor/autoload.php';

$address = "0.0.0.0";
$port = "3310";

const argsDelimeter = ' ';
const commandTypes = [ "create", "ping", "reping", "new" ];

class Server implements MessageComponentInterface {
    protected $clients;
    protected $games = [];  // port => proces
    protected $loop;
    protected $port = 3310;

    protected $gamePort = 49152;
    protected $freePorts = [];   // Porty które były używane, ale nie są obecnie

    public function __construct($loop) {
        $this->clients = new \SplObjectStorage;
        $this->loop = $loop;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "Nowe połączenie: {$conn->resourceId}\n";
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Połączenie zamknięte: {$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Błąd: {$e->getMessage()}\n";
        $conn->close();
    }

    public function onMessage(ConnectionInterface $socket, $msg) {
        $args = explode(argsDelimeter, $msg);
        if(empty($args) || empty($args[0])) return false;

        switch(strtolower($args[0])){
            case commandTypes[0]:  // Create
                $this->createGame($socket, $args);
                break;
            case commandTypes[1]:  // Ping
                $socket->send("Reping");
                break;
            case commandTypes[2]:  // Reping
                break;
        }
    }

    private function createGame($socket, $args){
        if(empty($args) || count($args) < 4){
            $socket->send("Error 11");
            return false;
        }
        if($this->gamePort > 65535 && empty($this->freePorts)){
            $socket->send("Error 41" . $port);
            return false;
        }

        $prt = $this->gamePort;
        if(!empty($this->freePorts)) $prt = array_shift($this->freePorts);
        else $this->gamePort++;

       /* while(!$this->checkPort($prt)){
            if($prt > 65535){
                $socket->send("Error 42");
                return;
            }

            array_push($this->freePorts, $prt);  // Zwracamy port, być może później się zwolni
            $prt = $this->gamePort++;
        }*/

        $process = new Process([PHP_BINARY, __DIR__ . "/game.php", $this->port , $prt, $args[1], (string)$args[2], $args[3]]);
        $process->start();
        $this->games[$prt] = $process;
            
        $timer = $this->loop->addPeriodicTimer(5, function () use ($process, $prt, &$games, &$timer) {
            if (!$process->isRunning()) {
                echo "Proces na porcie $prt zakończony.\n";
                unset($games[$prt]); 
                $this->freePorts[] = $prt;
                $this->loop->cancelTimer($timer);
            }
        });

        $socket->send("Port " . $prt);
        echo "Uruchomiono instancję gry na porcie: $prt\n";
    }

    private function checkPort($port, $host = '127.0.0.1') {
        $connection = @fsockopen($host, $port, $errno, $errstr, 0.2);
        if (is_resource($connection)) {
            fclose($connection);
            return false;
        }
        return true;
    }
}

$loop = Factory::create();
$server = new Ratchet\Server\IoServer(
    new Ratchet\Http\HttpServer(new Ratchet\WebSocket\WsServer(new Server($loop))),
    new React\Socket\SocketServer($address . ":" . $port),
    $loop
);

$loop->run();
?>