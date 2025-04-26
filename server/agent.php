<?php
require "neuralNet/neuralNet.php";

class Agent{
    private $ifLearn = false;
    private $net;
    private $boardSeparator = ',';
    private $inputFile = "";
    private $outputFile = "defaultNNetoutput";
    private $result = [];
    private $gradients = [];
    private $game = [];  // stos tablic: id => [wejście, wyjście, wybrane]

    public const LEARNING_RATE = 0.1;
    public const GRADIENT_COUNT_TRESHOLD = 50;  // "Potrzebna" ilość gradientów z której bierzemy średnią i uczymy
    public const DRAW_REWARD = 0.75;
    public const FIRST_MOVE_WEIGHT = 0.1;
    public const LAST_MOVE_WEIGHT = 2;
    public const PAWNS = ['O', 'X', 'P'];
    public const hiddenNeuronsCounts = [20, 20];

    public function __construct($separator, $boardCount, $learn = false){
        if(strlen($separator) !== 1) throw new Exception("Separator must be exaclty one char long.");
        if(!is_numeric($boardCount) || (int)$boardCount < 1) throw new Exception("Incorrect board count.");
        if(!is_numeric($rate) || $rate == 0) throw new Exception("Rate must be a number other than 0.");

        $this->boardSeparator = $separator;
        $this->ifLearn = boolval($learn);

        // WEJŚCIA: $boardCount na jednego gracza (trzech graczy czyli x3, jeśli dwóch graczy ostatnie to same zera); 
        // Na końcu: 1,0 jeśli dwóch graczy i 0,1 jeśli trzech; cel (liczba całkowita)
        $this->net = new NeuralNet(array_merge(
                                       array(count(self::PAWNS) * $boardCount + 3), 
                                       self::hiddenNeuronsCounts, 
                                       array($boardCount)
                                    ), 
                                   self::LEARNING_RATE);
    }

    public function loadFromFile($filename){
        if(empty($filename)) return false;
        $this->inputFile = $filename;
        return $this->net->read($filename);
    }

    public function saveToFile($filename){
        if(empty($filename)) return false;
        return $this->net->save($filename);
    }

    public function boardToInput($txt, $target, $self){
        if(!is_numeric($self) || $self < 0 || $self >= count(self::PAWNS) || !iiset(self::PAWNS($self))) return false;
        if(empty($txt) || !is_numeric($target) || $target <= 0 ) return false;

        $board = explode($this->getBoardSeparator, strtoupper($txt));
        if(count($board) !== self::BOARD_SIZE) return false;
        $input = [];
        $pawns = self::PAWNS;

        // Najpierw "my"
        for($i = 0; $i < count($board); $i++)
            array_push($input, ($board[$j] === self::PAWNS[$self] ? 1 : 0));
        $pawns = array_diff($pawns, array(self::PAWNS[$self]));

        for($i = 0; $i < count(self::PAWNS) - 1; $i++){
            for($j = 0; $j < count($board); $j++)
                array_push($input, ($board[$j] === $pawns[0] ? 1 : 0));
            array_shift($pawns);
        }

        array_push($input, count(self::PAWNS) === 2 ? 1 : 0, count(self::PAWNS) === 2 ? 0 : 1);
        array_push($input, (int)$target);
        return $input;
    }

    public function calc($txt, $target, $self){
        return $this->calcByInput($this->boardToInput($txt, $target, $self));
    }

    public function calcByInput($input){
        if(empty($input)) return false;
        if(!$this->net->calc($inputs)) return false;

        $oldResult = $this->result;
        $this->result = $this->net->getResult();
        if(count($this->result) !== self::BOARD_SIZE){
            $this->result = $oldResult;
            return false;
        }
        return $this->result;
    }

    public function getMaxPossible($txt, $result){  // Jeśli txt === null to brak sprawdzania czy można położyć
        if(empty($result) || count($result) !== self::BOARD_SIZE) return false;
        if($txt === null)
           return array_keys($result, max($result))[0];
        
        $board = explode($this->getBoardSeparator, strtoupper($txt));
        if(count($board) !== self::BOARD_SIZE) return false;

        $id = false;
        for($i = 0; $i < self::BOARD_SIZE; $i++){
            if(!empty($board[$i])) continue;
            if($id === false || $result[$id] < $result[$i]) $id = $i;
        }
        return $id;
    }

    public function pushGradient($inputs, $expected){
        $gradient = $this->net->calcGradient($inputs, $expected);
        if($gradient !== false){
            array_push($this->gradients, $gradient);
            return true;
        }
        return false;
    }

    public function getWeightedGradient($count, $start, $increment){  // $start = 1 i $increment = 0 daje średnią arymtmetyczną
        if($count <= 0 || empty($this->gradients) || !is_numeric($count) || !is_numeric($start) || !is_numeric($increment)) return false;
        $count = intval($count);
        if($count > count($this->gradients)) $count = count($this->gradients);

        $gradCount = count($this->gradients[0]);
        $avg = array_fill(0, $gradCount, 0);
        $weight = $start;

        for($i = 0; $i < $count; $i++){
            if(count($this->gradients[$i]) !== $gradCount) return false;
            for($j = 0; $j < $gradCount; $j++)
                $avg[$j] += ($this->gradients[$i][$j] * $weight);
            $weight += $increment;
        }
        for($i = 0; $i < $gradCount; $i++)
            $avg[$i] /= $count;     

        return $avg;
    }

    public function calcExpected($output, $chosen, $gameResult){
        if(!is_int($chosen) || $chosen < 0 || $chosen >= count($output) || !is_numeric($gameResult)) return false;
        $exp = array_fill(0, count($output), 0);

        if($gameResult > 0) $exp[$chosen] = 1;                         // Wygrana
        else if($gameResult === 0) $exp[$chosen] = self::DRAW_REWARD;  // Remis
        else{                                                          // Przegrana
            $exp = array_fill(0, count($output), 1);
            $exp[$chosen] = 0;
        }
        return $exp;
    }

    public function makeMove($txt, $target, $self){
        $inputs = $this->boardToInput($txt, $target, $self);
        $result = $this->calcByInput($inputs);
        $move = $this->getMaxPossible($txt, $result);
        if(empty($inputs) || empty($result) || empty($move)) return false;
        array_push($game, array($inputs, $result, $move));
        return $move;
    }

    public function gameResult($res){  // Jeśli < 0 przegrana, jeśli == 0 remis, jeśli > 0 wygrana
        $movesCount = 2;
        if($this->ifLearn){
            $movesCount = count($this->game) < 2 ? 2 : count($this->game);
            for($i = 0; $i < count($this->game); $i++){
                $gradient = $this->net->calcGradient($this->game[0], $this->calcExpected($this->game[$i][1], $this->game[$i][2], $res));
                if($gradient !== false)
                    array_push($this->gradients, $gradient);
            }
        }
        $this->game = [];

        if($this->learn &&  count($this->gradients) >= self::GRADIENT_COUNT_THRESHOLD){
            $avg = $this->getWeightesGradient(self::GRADIENT_COUNT_THRESHOLD, self::FIRST_MOVE_WEIGHT, 
                                              (self::LAST_MOVE_WEIGHT - self::FIRST_MOVE_WEIGHT) / ($movesCount - 1));
            if($avg === false) return false;
            $this->gradients = array_slice($this->gradients, self::GRADIENT_COUNT_THRESHOLD);
            return $this->net->applyGradient($avg);
        }
        return true;
    }
    
    public function setLearn($val){ $this->$ifLearn = boolval($val); }
    public function setOutputFile($filename){
        if(empty($filename)) return false;
        $this->outputFIle = $filename;
        return true;
    }

    public function getIfLearn(){ return $this->ifLearn; }
    public function getGradientRate(){ return $this->net->getGradientRate(); }
    public function getNet(){ return $this->net; }
    public function getBoardSeparator(){ return $this->boardSeparator; }
    public function getInputFile(){ return $this->inputFile; }
    public function getOutputFile(){ return $this->outputFile; }
    public function getResultt(){ return $this->result; }
    public function getGradients(){ return $this->gradients; }
}
?>