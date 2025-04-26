<?php
// argumenty: portSerwera, portGry, liczbaGraczy, rozmiary, cel
// rozmiary to ciąg znaków, wartości są rozdzielone przecinkami

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\Factory;

require "vendor/autoload.php";
require "board.php";
require "agent.php";

class GameServer implements MessageComponentInterface {
    protected $clients;
    protected $players = [];     // nick => socket, jeśli [nick] === null to nie ma takiego gracza
    protected $playersIDs = [];  // nick => id
    protected $bots = [];        // nick => sieć
    protected $botsIDs = [];     // nick => id

    protected $freeIDs = [0,1,2];
    protected $board;
    protected $mainServerPort;
    protected $port;
    protected $numOfPlayers = 0;
    protected $admin = null;
 
    protected $started = false;
    protected $turn = 0;
    protected $paused = false;
    protected $stopRefresh = false;  // Jeśli funkcja interpretująca ma nie zwracać false (np przy ping), ale chcemy zatrzymać refresh
    protected $resetTime = 7;        // Czas od zakończenia gry do automatycznego ponownego startu (w senkundach)

    protected $loop;
    protected $shutdownTimer = null;
    protected $startTime;

    const IF_LEARN = true;  // Do sieci neuronowych
    const LEARNING_RATE = 0.1;

    const MSG_DELIMETER = ' ';
    const SIZES_DELIMETER = ',';  // Ogółem przy większości implode/explode
    const MAX_NUM_OF_PLAYERS = 3;

    public function __construct($mainPort, $prt, $maxPlayers, $sizes, $target, $lp) {
        if($mainPort < 0 || $mainPort > 65535 || $mainPort === $prt) die("Podano nie poprawny port głównego serwera.");
        if($prt < 49152 || $prt > 65535) die("Podano nie poprawny port gry.");
        if($maxPlayers < 2 || $maxPlayers > self::MAX_NUM_OF_PLAYERS) die("Podano nie poprawną ilość graczy.");
        if(empty(explode(",", $sizes))) die("Podano nie poprawne rozmiary planszy.");
        if($target < 0) die("Podano nie poprawny cel.");

        $this->clients = new \SplObjectStorage;
        $this->mainServerPort = $mainPort;
        $this->port = $prt;
        $this->numOfPlayers = (int)$maxPlayers;
        $this->board = new Board(explode(",", $sizes), $target);
        $this->loop = $lp;
        $this->startTime = date("Y-m-d h:i:s");

        $this->freeIDs = [];
        for($i = 0; $i < $this->numOfPlayers; $i++) $this->freeIDs[] = $i;
    }

    public function __destruct(){
        $this->shutdown();
    }  

    public function shutdown(){
        foreach($this->clients as $client){
            $client->send("Closed");
            $this->clients->detach($client);
        }
        $this->loop->stop();
    }

    public function onOpen(ConnectionInterface $socket) {
        $this->clients->attach($socket);
        if(!$this->admin){
            $this->admin = $socket;
            $socket->send("YouAreAdmin");
        }

        if ($this->shutdownTimer !== null) {
            $this->loop->cancelTimer($this->shutdownTimer);
            $this->shutdownTimer = null;
        }
    }

    public function onClose(ConnectionInterface $socket) { 
        $nick = array_search($socket, $this->players);
        $this->clients->detach($socket);
        if($socket === $this->admin) $this->admin = null;
        if($nick === false) return false;  // Jeśli klient nie jest graczem

        $this->freeIDs[] = $this->playersIDs[$nick];
        unset($this->playersIDs[$nick]);
        unset($this->players[$nick]);

        $this->pause();

        if(count($this->players) < 1)
            $this->shutdownTimer = $this->loop->addTimer(10, function () { 
                if (count($this->players) < 1) die();  // Po odliczeniu czasu sprawdzamy czy ktoś nie dołączył
            });

        $this->refresh();
    }

    public function onError(ConnectionInterface $socket, \Exception $e) { $socket->close(); }  // TODO

    public function onMessage(ConnectionInterface $socket, $msg) {
        $args = explode(self::MSG_DELIMETER, $msg);
        if(!$this->checkArgs($socket, $args)) return;
        if($this->paused && strtolower($args[0]) !== "setnick"){
            $socket->send("Error 12");
            return;
        }

        if($socket === $this->admin){
            if(!$this->admin($socket, $args)) return;
        }
        else if(!$this->player($socket, $args)) return;

        if(!$this->stopRefresh) $this->refresh();
        else $this->stopRefresh = true;
    }

    public function checkWin(){
        $result = $this->board->checkWin();  // [idPola, idKierunku]
        if($result === null || empty($result) || count($result) < 2) return false;

        $playerID = $this->board->getPlayerByField($result[0]);
        $nick = (string)array_search($playerID, $this->playersIDs);

        if($playerID === false || $nick === false) return false;

        foreach ($this->clients as $client) 
            $client->send("Won " . $playerID . ' ' . $nick . ' ' . $result[0] . ' ' . $result[1]);
        $this->pause();
        $this->started = false;
        $this->writeToDB($nick);

        $this->loop->addTimer($this->resetTime, function () {
            if(!$this->started){
                $this->resetGame($this->numOfPlayers, $this->board->serializeSizes(), $this->board->getTarget(), $this->admin);
                $this->unpause();
            }
        });
        return true;
    }

    public function checkTie(){
        if($this->board->checkTie()){
            foreach($this->clients as $client)
                $client->send("Tie");
            $this->pause();
            $this->started = false;
            $this->writeToDB(null);

            $this->loop->addTimer($this->resetTime, function () {
                if(!$this->started){
                    $this->resetGame($this->numOfPlayers, $this->board->serializeSizes(), $this->board->getTarget());
                    $this->unpause();
                }
            });
        }
        return true;
    }

    public function resetGame($numOfPl, $sizes, $target, $socket = null){
        if(empty($numOfPl) || empty($sizes) || empty($target) || $target < 0){
            if($socket !== null) $socket->send("Error 11");
            return false;
        }
        if($numOfPl < 2 || $numOfPl > self::MAX_NUM_OF_PLAYERS){
            if($socket !== null) $socket->send("Error 43");
            return false;
        }
        if(empty(explode(self::SIZES_DELIMETER, $sizes))){
            if($socket !== null) $socket->send("Error 44");
            return false;
        }
        if($target < 0){
            if($socket !== null) $socket->send("Error 45");
            return false;
        }

        $this->numOfPlayers = (int)$numOfPl;
        $this->board = new Board(explode(self::SIZES_DELIMETER, $sizes), $target);
        $this->freeIDs = [];
        for($i = 0; $i < $this->numOfPlayers; $i++) $this->freeIDs[] = $i;
        $this->started = false;

        if($this->numOfPlayers > count($this->players)){  
            foreach($this->playersIDs as $nick => $id)
                if($id >= $this->numOfPlayers){
                    $this->players[$nick]->send("Kicked");
                    $this->clients->detach($this->players[$toKick]);
                    unset($this->players[$nick]);
                    unset($this->playersIDs[$nick]);
                }
            $this->started = true;
        }
        $this->refresh();
        return true;
    }

    public function getIDBySocket($socket){
        $nick = array_search($socket, $this->players);
        return $nick !== false ? $this->playersIDs[$nick] : false;
    }

    public function getSocketByID($id){
        $nick = array_search($id, $this->playersIDs);
        return $nick !== false ? $this->players[$nick] : false;
    }


    private function checkArgs($socket, $args){
        if(empty($args)){
            $socket->send("Error 11");
            return false;
        }
        if(!isset($args[0]) || empty($args[0])){
            $socket->send("Error 10");
            return false;
        }

        switch(strtolower($args[0])){
            case "create":
                if($socket !== $this->admin){
                    $socket->send("Error 01");
                    return false;
                }
                if(count($args) < 4){
                    $socket->send("Error 11");
                    return false;
                }
                if(!is_numeric($args[1]) || (int)$args[1] <= 0 || (int)$args[1] > self::MAX_NUM_OF_PLAYERS){
                    $socket->send("Error 43");
                    return false;
                }
                if(empty(explode(self::SIZES_DELIMETER, $args[2]))){
                    if($socket !== null) $socket->send("Error 44");
                    return false;
                }
                if(!is_numeric($args[3]) || (int)$args[3] <= 0){
                    $socket->send("Error 45");
                    return false;
                }
                break;

            case "clear":
                if($socket !== $this->admin){
                    $socket->send("Error 01");
                    return false;
                }
                if(count($args) < 2){
                    $socket->send("Error 11");
                    return false;
                }
                if(empty(explode(self::SIZES_DELIMETER, $args[1])) || count(explode(self::SIZES_DELIMETER, $args[1])) < 2){
                    $socket->send("Error 91");
                    return false;
                }
                break;

            case "clearplayer":  // Łączymy case'y
            case "setturn":
            case "kick":
                if($socket !== $this->admin){
                    $socket->send("Error 01");
                    return false;
                }
                if(count($args) < 2){
                    $socket->send("Error 11");
                    return false;
                }
                if(!is_numeric($args[1]) || (int)$args[1] < 0 || (int)$args[1] >= count($this->players)){
                    $socket->send("Error 91");
                    return false;
                }
                break;
            case "put":
                if(count($args) < 2){
                    $socket->send("Error 11");
                    return false;
                }
                if(array_search($socket, $this->players) === false){
                    $socket->send("Error 00");
                    return false;
                }
                if($this->turn !== $this->getIDBySocket($socket)){
                    $socket->send("Error 22");
                    return false;
                }
                if(count(explode(self::SIZES_DELIMETER, $args[1])) < 2){  // TODO
                    $socket->send("Error 21");
                    return false;
                }
                break;
    
            case "setnick":
                if(count($args) < 2){
                    $socket->send("Error 11");
                    return false;
                }
                if(count($args) === 3){
                    if($socket !== $this->admin){
                        $socket->send("Error 01");
                        return false;
                    }
                    if(!is_numeric($args[2]) || (int)$args[2] < 0 || (int)$args[2] >= count($this->players)){
                        $socket->send("Error 81");
                        return false;
                    }
                }
                if(empty($args[1])){
                    $socket->send("Error 31");
                    return false;
                }
                if($this->players[$args[1]] !== null){
                    $socket->send("Error 33");
                    return false;
                }
                break;
            case "join": 
                if(count($this->freeIDs) < 1){
                    $socket->send("Error 30");
                    return false;
                }
                if(count($args) < 2){
                    $socket->send("Error 11");
                    return false;
                }
                if(empty($args[1]) || preg_match("/\s/", $args[1])){  // Pusty lub zawiera dowolne białe znaki
                    $socket->send("Error 31");
                    return false;
                }
                if($this->players[$args[1]] !== null){
                    $socket->send("Error 33");
                    return false;
                }
                if(in_array($socket, $this->players)){
                    $socket->send("Error 34");
                    return false;
                }
                break; 
            case "addbot": 
                if($socket !== $this->admin){
                    $socket->send("Error 01");
                    return false;
                }
                if(count($this->freeIDs) < 1){
                    $socket->send("Error 30");
                    return false;
                }
                if(count($args) < 2){
                    $socket->send("Error 11");
                    return false;
                }
                if(empty($args[1])){
                    $socket->send("Error 31");
                    return false;
                }
                if($this->players[$args[1]] !== null || $this->bots[$args[1]] !== null){
                    $socket->send("Error 33");
                    return false;
                }
                break; 

            case "drop": break;
            case "pause": break;
            case "unpause": break;
            case "ping": break;
            case "reping": break;
            default: 
                $socket->send("Error 10");
                return false;
                break;
        }
        return true;
    }

    // Z racji na istnienie checkArgs zakładamy, że argumenty są poprawne, poniżej również
    private function admin($socket, $args){
        if(!isset($args[0]) || empty($args[0])) return false;

        switch(strtolower($args[0])){
            case "create":
                $this->resetGame((int)$args[1], $args[2], (int)$args[3], $socket);
                break;

            case "drop":
                $this->__destruct();
                break;

            case "clear":
                if(!$this->board->clear($this->board->arrToId(explode(self::SIZES_DELIMETER, $args[1])))){
                    $socket->send("Error 91");
                    return false;
                }
                break;

            case "clearplayer":
                if(!$this->board->clearPlayer($args[1])){
                    $socket->send("Error 91");
                    return false;
                }
                break;

            case "kick":
                $toKick = array_search($args[1], $this->playersIDs);
                if($toKick === false){
                    $socket->send("Error 93");
                    return false;
                }

                $this->players[$toKick]->send("Kicked");
                $this->freeIDs[] = $this->playersIDs[$toKick];
                
                $this->clients->detach($this->players[$toKick]);
                unset($this->players[$toKick]); 
                unset($this->playersIDs[$toKick]);
                break;

            case "put":
                return $this->put($socket, $args);
                break;

            case "setturn":
                $this->turn = (int)$args[1] < 0 ? 0 : (int)$args[1];
                break;

            case "setnick":
                $oldNick = "";
                foreach($this->players as $nick => $sockID)
                    if($sockID[0] == $socket){
                        $oldNick = $nick;
                        break;
                    }
                if(empty($oldNick)){
                    $socket->send("Error 80");
                    return false;
                }

                $this->players[$args[1]] = $this->players[$oldNick];
                $this->playersIDs[$args[1]] = $this->playersIDs[$oldNick];
                unset($this->players[$oldNick]);
                unset($this->playersIDs[$oldNick]);
                break;

        

            case "join": 
                return $this->join($socket, $args);
                break; 

            case "addbot": 
                return $this->addBot($socket, $args);
                break; 

            case "pause": 
                $this->pause();
                break;

            case "unpause":
                $this->unpause();
                break;

            case "ping":
                $socket->send("Reping");
                $this->stopRefresh = true;
                break;

            case "reping": 
                $this->stopRefresh = true; 
                break;

            default: 
                $socket->send("Error 10");
                break;
        }
        return true;
    }

    private function player($socket, $args){
        if(!isset($args[0]) || empty($args[0])) return false;
        
        switch(strtolower($args[0])){
            case "put":
                if(!$this->put($socket, $args)) return false;
                break;

            case "join":
                if(!$this->join($socket, $args)) return false;
                break;

            case "setnick":
                $oldNick = "";
                foreach($this->players as $nick => $sockID)
                    if($sockID[0] == $socket){
                        $oldNick = $nick;
                        break;
                    }
                if(empty($oldNick)){
                    $socket->send("Error 80");
                    return false;
                }

                $this->players[$args[1]] = $this->players[$oldNick];
                $this->playersIDs[$args[1]] = $this->playersIDs[$oldNick];
                unset($this->players[$oldNick]);
                unset($this->playersIDs[$oldNick]);
                break;

            case "ping":
                $socket->send("Reping");
                break;

            case "reping": break;
            default: 
                $socket->send("Error 10");
                break;
        }
        return true;
    }

    private function refresh(){
        $txt = "Refresh " . json_encode($this->playersIDs) . ' ' . implode(self::SIZES_DELIMETER, $this->board->getSizes()) . ' ' 
               . $this->board->implode() . ' ' . $this->board->getTarget() . ' ' . $this->turn;
        foreach ($this->clients as $client) 
            $client->send($txt . ' ' . $this->getIDBySocket($client));
    }

    private function join($socket, $args){
        $this->players[$args[1]] = $socket;
        $this->playersIDs[$args[1]] = array_shift($this->freeIDs);
        $socket->send("Joined");

        if((count($this->players) + count($this->bots)) === $this->numOfPlayers){
            foreach($this->clients as $client) $client->send("Started");
            $this->started = true;
            $this->startTime = date("Y-m-d h:i:s");
        }
        return true;
    }

    private function pause(){
        $this->paused = true;  
        foreach ($this->clients as $client) $client->send("Paused");
    }

    private function unpause(){
        $this->paused = false;
        foreach ($this->clients as $client) $client->send("Unpaused");
    }

    private function put($socket, $args){
        $id = $this->getIDBySocket($socket);
        if($this->turn !== $id){
            $socket->send("Error 22");
            return false;
        }
        if(!$this->board->put(explode(self::SIZES_DELIMETER, $args[1]), $id)){
            $socket->send("Error 22");
            return false;
        }

        $this->turn++;
        $this->turn %= count($this->players);
        $this->checkWin();
        $this->checkTie();
        return true;
    }

    private function addBot($socket, $args, $filename = null){
        try{
            $agent = new Agent(self::SIZES_DELIMETER, 
                               $this->board->count(), 
                               self::IF_LEARN);
            if(!empty($filename)) $agent->loadFromFile($filename);

            $this->bots[] = $agent;
            $this->botsIDs[] = array_shift($this->freeIDs);
            $agent->saveToFile("logsKiK2/netSave.txt");
        }
        catch(Exception $e){ 
            return false; 
        }
        return true;
    }

    private function botTurn(){  // Razem ze sprawdzeniem czy tura bota
        $nick = array_search($this->turn, $this->botsIDs);
        if($nick === false) return false;
        $move = $this->bots[$nick]->makeMove($this->board->implode($this->bots[$nick]->getBoardSeparator()), 
                                             $this->board->getTarget(), 
                                             $this->board::PAWNS[$this->turn]);
        if($move === false) return false;
        if(!$this->board->putByID($move, $this->board::PAWNS[$this->turn])) return false;
        
        $this->turn++;
        $this->turn %= count($this->players);
        $this->checkWin();
        $this->checkTie();
        return true;
    }

    private function writeToDB($winner){
        $conn = new mysqli("localhost", "root", "", "tictactoe2");
        if($conn->connect_error) return false;

        $nick0 = array_search(0, $this->playersIDs);
        $nick1 = array_search(1, $this->playersIDs);
        $nick2 = array_search(2, $this->playersIDs);
        $query = "INSERT INTO results (nick0, nick1, nick2, winner, startDate, endDate, port) VALUES (" 
            . ($nick0 === false ? "NULL" : "'" . $nick0 . "'") . ", " 
            . ($nick1 === false ? "NULL" : "'" . $nick1 . "'") . ", " 
            . ($nick2 === false ? "NULL" : "'" . $nick2 . "'") . ", "
            . ($winner === null ? "NULL" : "'" . $winner . "'") . ", '" 
            . $this->startTime . "', '" 
            . date("Y-m-d h:i:s") . "', '" 
            . $this->port . "');";

        try{
            $conn->query($query);
            $conn->close();
        }
        catch(Exception $e){ return false; }
        return true;
    }
}

$loop = Factory::create();
$server = new Ratchet\Server\IoServer(
    new Ratchet\Http\HttpServer(new Ratchet\WebSocket\WsServer(new GameServer($argv[1], $argv[2], $argv[3], (string)$argv[4], $argv[5], $loop))),
    new React\Socket\SocketServer("0.0.0.0:" . $argv[2]),
    $loop
);
$loop->run();
?>