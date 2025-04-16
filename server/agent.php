<?php
require "neuralNet/neuralNet.php";

class Agent{
    private $ifLearn = false;
    private $gradientRate = 1;
    private $net;
    private $boardSeparator = ',';
    private $filename = "";
    private $result = [];
    private $gradients = [];

    public const PAWNS = ['O', 'X', 'P'];
    public const PAWNS_COUNT = 3;
    public const boardX = 4;
    public const boardY = 4;
    public const boardZ = 4;
    public const BOARD_SIZE = self::boardX * self::boardY * self::boardZ;
    public const INPUT_SIZE = self::PAWNS_COUNT * self::BOARD_SIZE + 2; // +2 bo ilość graczy i target

    public function __construct($separator, $layers, $neurons, $learn = false, $rate = 1){
        if(count($separator) !== 1) throw new Exception("Separator must be exaclty one char long.");
        if(!is_numeric($layers) || $layers < 1) throw new Exception("Number of layers must be greater or equal 1.");
        if(empty($neurons) || count($neurons) < 1) throw new Exception("Incorrect neuron sizes.");
        if(!is_numeric($rate) || $rate == 0) throw new Exception("Rate must be a number other than 0.");

        $this->boardSeparator = $separator;
        $this->ifLearn = boolval($learn);
        $this->gradientRate = (int)$rate;
        $this->net = new NeuralNet($layers, $neurons, $this->gradientRate);
    }

    public function loadFile($filename){
        if(empty($filename)) return false;
        $this->filename = $filename;
        return $this->net->read($filename);
    }

    public function calc($txt, $target){
        if(empty($txt) || !is_numeric($target) || $target <= 0 ) return false;
        $board = explode($this->getBoardSeparator, strtoupper($txt));
        if(count($board) !== self::BOARD_SIZE) return false;

        $input = [];
        for($i = 0; $i < self::PAWNS_COUNT; $i++)
            for($j = 0; $j < count($board); $j++)
                array_push($input, ($board[$j] === self::PAWNS[$i] ? 1 : 0));
        array_push($input, self::PAWNS_COUNT);
        array_push($input, (int)$target);

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

    public function getAvgGradient($count){
        if($count <= 0 || empty($this->gradients) || !is_numeric($count)) return false;
        $count = intval($count);
        if($count > count($this->gradients)) $count = count($this->gradients);

        $gradCount = count($this->gradients[0]);
        $avg = array_fill(0, $gradCount, 0);

        for($i = 0; $i < $count; $i++)
            for($j = 0; $j < $gradCount; $j++)
                if(count($this->gradients[$i]) !== $gradCount) return false;
                else $avg[$j] += $this->gradients[$i][$j];
                
        for($i = 0; $i < $gradCount; $i++)
            $avg[$i] /= $count;

        return $avg;
    }

    public function getIfLearn(){ return $this->ifLearn; }
    public function getGradientRate(){ return $this->gradientRate; }
    public function getNet(){ return $this->net; }
    public function getBoardSeparator(){ return $this->boardSeparator; }
    public function getFilename(){ return $this->filename; }
    public function getResultt(){ return $this->result; }
    public function getGradients(){ return $this->gradients; }
}
?>