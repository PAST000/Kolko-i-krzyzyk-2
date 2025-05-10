<?php
// argumenty: portSerwera, portGry, liczbaGraczy, rozmiary, cel
// rozmiary to ciąg znaków, wartości są rozdzielone przecinkami

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\Factory;

require "vendor/autoload.php";
require "board.php";
require "agent.php";
require "randomBot.php";

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
    protected $resetTime = 0.5;      // Czas od zakończenia gry do automatycznego ponownego startu (w senkundach)
    protected $randomReset = false;  // Czy losować GAME_MODE w resetGame()

    protected $loop;
    protected $shutdownTimer = null;
    protected $startTime;
    protected $conn;

    const IF_LEARN = true;  // Do sieci neuronowych
    const LEARNING_RATE = 0.1;

    const MSG_DELIMETER = ' ';
    const SIZES_DELIMETER = ',';  // Ogółem przy większości implode/explode
    const MAX_NUM_OF_PLAYERS = 3;
    const MIN_RESET_TIME = 0.5;
    const MAX_RESET_TIME = 30;
    const GAME_MODES = [[2,3], [2,4], [3,3], [3,4]]; // Ilość graczy, target

    public function __construct($mainPort, $prt, $maxPlayers, $sizes, $target, $lp, $ifRand = false) {
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
        for($i = 0; $i < $this->numOfPlayers; $i++) 
            $this->freeIDs[] = $i;
        $this->randomReset = $ifRand;

        $this->conn = new mysqli("localhost", "root", "", "tictactoe2");
        if ($this->conn->connect_error) 
            die("Connection failed: " . $this->conn->connect_error);
    }

    public function __destruct(){
        $this->shutdown();
    }  

    public function shutdown(){
        foreach($this->clients as $client){
            $client->send("Closed");
            $this->clients->detach($client);
        }
        $this->conn->close();
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
        if($nick === false) return;  // Jeśli klient nie jest graczem

        $this->kickPlayer($nick);
        $this->pause();

        if((count($this->players) + count($this->bots)) < 1)
            $this->shutdownTimer = $this->loop->addTimer(10, function () { 
                if (count($this->players) < 1) die();  // Po odliczeniu czasu sprawdzamy czy ktoś nie dołączył
            });
        $this->refresh();
    }

    public function onError(ConnectionInterface $socket, \Exception $e) { 
        $nick = array_search($socket, $this->players);
        if($socket === $this->admin) $this->admin = null;
        $this->clients->detach($socket);
        $socket->close(); 
        if($nick === false) return false;  // Jeśli klient nie jest graczem

        $this->kickPlayer($nick);
        $this->pause();

        if(count($this->players) < 1)
            $this->shutdownTimer = $this->loop->addTimer(10, function () { 
                if (count($this->players) < 1) die();  // Po odliczeniu czasu sprawdzamy czy ktoś nie dołączył
            });
        $this->refresh();
    }

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
        else $this->stopRefresh = false;

        while($this->botTurn() && !$this->paused)
            $this->refresh();
    }

    public function checkWin(){
        $result = $this->board->checkWin();  // [idPola, idKierunku]
        if($result === null || empty($result) || count($result) < 2) return false;

        $playerID = $this->board->getPlayerByField($result[0]);
        $winnerWick = (string)array_search($playerID, array_merge($this->playersIDs, $this->botsIDs));
        if($playerID === false || $winnerWick === false) return false;

        foreach ($this->clients as $client) 
            $client->send("Won " . $playerID . ' ' . $winnerWick . ' ' . $result[0] . ' ' . $result[1]);
        $this->pause();
        $this->started = false;
        $this->writeToDB($winnerWick);

        foreach($this->bots as $botnick => $bot)
            $bot->gameResult($nick === $winnerWick ? 1 : -1);

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

            foreach($this->bots as $botnick => $bot)
                $bot->gameResult(0);

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
        if(!self::RANDOM_RESET){
            if(empty($numOfPl) || empty($target) || $target < 0){
                if($socket !== null) $socket->send("Error 11");
                return false;
            }
            if($numOfPl < 2 || $numOfPl > self::MAX_NUM_OF_PLAYERS){
                if($socket !== null) $socket->send("Error 43");
                return false;
            }
            if($target < 0){
                if($socket !== null) $socket->send("Error 45");
                return false;
            }
        }
        if(empty($sizes) || empty(explode(self::SIZES_DELIMETER, $sizes))){
            if($socket !== null) $socket->send("Error 44");
            return false;
        }
        
        try{
            if(self::RANDOM_RESET){
                $mode = self::GAME_MODES[mt_rand(0, count(self::GAME_MODES) - 1)];
                $this->numOfPlayers = $mode[0];
                $this->board = new Board(explode(self::SIZES_DELIMETER, $sizes), $mode[1]);
            }
            else{
                $this->numOfPlayers = (int)$numOfPl;
                $this->board = new Board(explode(self::SIZES_DELIMETER, $sizes), $target);
            }

            $this->freeIDs = [];
            for($i = 0; $i < $this->numOfPlayers; $i++) 
                $this->freeIDs[] = $i; 
            $usedIDs = [];
            foreach(array_merge($this->playersIDs, $this->botsIDs) as $nick => $id)
                if($id < $this->numOfPlayers) 
                    $usedIDs[] = $id;
            array_diff($this->freeIDs, $usedIDs);

            if($this->numOfPlayers > (count($this->players) + count($this->bots))){  
                foreach($this->playersIDs as $nick => $id)
                    if($id >= $this->numOfPlayers)
                        $this->kickPlayer($nick, false);

                foreach($this->botsIDs as $nick => $id)
                    if($id >= $this->numOfPlayers)
                        $this->kickBot($nick, false);
            }
            if($this->numOfPlayers === (count($this->players) + count($this->bots)))
                $this->started = true;
            else 
                $this->started = false;

            $this->refresh();
        }
        catch(Exception $e){ 
            return false; 
        }
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
                if(count(explode(self::SIZES_DELIMETER, $args[1])) !== $this->board->getDims()){  
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
            case "addrand":
            case "addrandom": 
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
            case "setdelay":
                if($socket !== $this->admin){
                    $socket->send("Error 01");
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
                if(!is_numeric($args[1]) || $args[1] < self::MIN_RESET_TIME || $args[1] > self::MAX_RESET_TIME){
                    $socket->send("Error 82");
                    return false;
                }
                break;
            case "drop": break;
            case "pause": break;
            case "unpause": break;
            case "ping": break;
            case "pong": break;
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
                $playerNick = array_search($args[1], $this->playersIDs);
                $botNick = array_search($args[1], $this->botsIDs);
                if($playerNick === false && $botNick === false){
                    $socket->send("Error 93");
                    return false;
                }
                $this->kickPlayer($playerNick);
                $this->kickBot($botNick);
                break;

            case "put":
                return $this->put($socket, $args);
                break;

            case "setturn":
                $this->turn = (int)$args[1] < 0 ? 0 : (int)$args[1];
                break;

            case "setnick":
                if(!isset($args[2])){
                    $oldNick = array_search($this->players, $socket); 
                    if($oldNick === false){
                        $socket->send("Error 80");
                        return false;
                    }

                    $this->players[$args[1]] = $this->players[$oldNick];
                    $this->playersIDs[$args[1]] = $this->playersIDs[$oldNick];
                    unset($this->players[$oldNick]);
                    unset($this->playersIDs[$oldNick]);
                }
                else{
                    $playerNick = array_search($this->playersIDs, $args[2]);
                    $botNick = array_search($this->botsIDs, $args[2]);

                    if($playerNick !== false){
                        $this->players[$args[1]] = $this->players[$playerNick];
                        $this->playersIDs[$args[1]] = $this->playersIDs[$playerNick];
                        unset($this->players[$playerNick]);
                        unset($this->playersIDs[$playerNick]);
                    }
                    else if($botNick !== false){
                        $this->bots[$args[1]] = $this->bots[$botNick];
                        $this->botsIDs[$args[1]] = $this->botsIDs[$botNick];
                        unset($this->bots[$botNick]);
                        unset($this->botsIDs[$botNick]);
                    }
                    else{
                        $socket->send("Error 81");
                        return false;
                    }
                }
                break;
            case "join": 
                return $this->join($socket, $args);
                break; 

            case "addbot": 
                return $this->addBot($socket, $args[1], "bot1.txt");
                break; 
            case "addrand":
            case "addrandom":
                return $this->addRandom($socket, $args[1]);
                break; 
            case "pause": 
                $this->pause();
                break;

            case "unpause":
                $this->unpause();
                break;

            case "ping":
                $socket->send("pong");
                $this->stopRefresh = true;
                break;

            case "pong": 
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
                $oldNick = array_search($this->players, $socket); 
                if($oldNick === false){
                    $socket->send("Error 80");
                    return false;
                }

                $this->players[$args[1]] = $this->players[$oldNick];
                $this->playersIDs[$args[1]] = $this->playersIDs[$oldNick];
                unset($this->players[$oldNick]);
                unset($this->playersIDs[$oldNick]);
                break;

            case "ping":
                $socket->send("pong");
                break;

            case "reping": break;
            default: 
                $socket->send("Error 10");
                break;
        }
        return true;
    }

    private function refresh(){
        $txt = "Refresh " . 
                json_encode(array_merge($this->playersIDs, $this->botsIDs)) . ' ' . 
                implode(self::SIZES_DELIMETER, $this->board->getSizes()) . ' ' .
                $this->board->implode() . ' ' . 
                $this->board->getTarget() . ' ' . 
                $this->turn;
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
        foreach ($this->clients as $client) 
            $client->send("Unpaused");
        
        while($this->botTurn() && !$this->paused)
            $this->refresh();
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
        $this->turn %= (count($this->players) + count($this->bots));
        $this->checkWin();
        $this->checkTie();
        return true;
    }

    private function kickPlayer($nick, $appendID = true, $sendMessage = true){
        if(!isset($this->players[$nick])) return false;
        if($sendMessage) $this->players[$playerKick]->send("Kicked");
        if($appendID) $this->freeIDs[] = $this->playersIDs[$playerKick];
        $this->clients->detach($this->players[$playerKick]);
        unset($this->players[$playerKick]); 
        unset($this->playersIDs[$playerKick]);
        return true;
    }

    private function kickBot($nick, $appendID){
        if(!isset($this->bots[$nick])) return false;
        if($appendID) $this->freeIDs[] = $this->botsIDs[$nick];
        unset($this->bots[$nick]); 
        unset($this->botsIDs[$nick]);
        return true;
    }

    private function addBot($socket, $nick, $filename = null){
        try{
            $agent = new Agent(self::SIZES_DELIMETER, 
                               $this->board->count(), 
                               self::IF_LEARN);
            if(!empty($filename)) 
                $agent->loadFromFile($filename);

            $this->bots[$nick] = $agent;
            $this->botsIDs[$nick] = array_shift($this->freeIDs);
            $agent->save("logsKiK2/netSave.txt");

            if((count($this->players) + count($this->bots)) === $this->numOfPlayers){
                foreach($this->clients as $client) $client->send("Started");
                $this->started = true;
                $this->startTime = date("Y-m-d h:i:s");
            }
        }
        catch(Exception $e){ 
            return false; 
        }
        return true;
    }

    private function addRandom($socket, $nick){
        try{
            $this->bots[$nick] = new RandomBot($this->board->count());
            $this->botsIDs[$nick] = array_shift($this->freeIDs);

            if((count($this->players) + count($this->bots)) === $this->numOfPlayers){
                foreach($this->clients as $client) $client->send("Started");
                $this->started = true;
                $this->startTime = date("Y-m-d h:i:s");
            }
        }
        catch(Exception $e){ 
            return false; 
        }
        return true;
    }

    private function botTurn(){  // Razem ze sprawdzeniem czy tura bota
        $nick = array_search($this->turn, $this->botsIDs);
        if($nick === false || !$this->started) return false;

        if(gettype($this->bots[$nick]) === "RandomBot")
            $move = $this->bots[$nick]->makeMove($this->board->implode($this->bots[$nick]->getBoardSeparator()), 
                                                 $this->board::PAWNS[$this->turn]);
        else
            $move = $this->bots[$nick]->makeMove($this->board->implode($this->bots[$nick]->getBoardSeparator()), 
                                                 $this->board::PAWNS[$this->turn],
                                                 $this->board->getTarget(), 
                                                 count($this->players)
                                                );
        if($move === false) return false;
        if($this->board->putByID($move, $this->turn) === false) return false;

        $this->turn++;
        $this->turn %= (count($this->players) + count($this->bots));
        $this->checkWin();
        $this->checkTie();
        return true;
    }

    private function writeToDB($winner){
        $merged = array_merge($this->playersIDs, $this->botsIDs);
        if(count($merged) < 2) return false;
        asort($merged);
        $nicks = [];
        foreach($merged as $nick => $id)
            $nicks[] = $nick;

        $query = "INSERT INTO results (nick0, nick1, nick2, winner, startDate, endDate, port) VALUES (" 
            . (empty($nicks[0]) ? "NULL" : "'" . $nicks[0] . "'") . ", " 
            . (empty($nicks[1]) ? "NULL" : "'" . $nicks[1] . "'") . ", " 
            . (empty($nicks[2]) ? "NULL" : "'" . $nicks[2] . "'") . ", "
            . ($winner === null ? "NULL" : "'" . $winner . "'") . ", '" 
            . $this->startTime . "', '" 
            . date("Y-m-d h:i:s") . "', '" 
            . $this->port . "');";
        try{
            $this->conn->query($query);
        }
        catch(Exception $e){ return false; }
        return true;
    }
}

$loop = Factory::create();
$server = new Ratchet\Server\IoServer(
    new Ratchet\Http\HttpServer(new Ratchet\WebSocket\WsServer(new GameServer($argv[1], $argv[2], $argv[3], (string)$argv[4], $argv[5], $loop))),
    new React\Socket\SocketServer("0.0.0.0:" . $argv[2]),
    $loop,
    isset($argv[6]) && !empty($argv[6])
);
$loop->run();
?>