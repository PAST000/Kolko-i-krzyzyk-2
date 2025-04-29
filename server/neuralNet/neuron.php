<?php
require "sigmoid.php"; 

class Neuron{
    private $value = 0;
    private $preSigmoid = 0;
    private $weights = [];
    private $weightsCount = 0;
    private $bias = 0;

    public const MAX_RAND_WEIGHT = 10;      // Największa (również w. bezwględna z najmniejszej) wartość wagi
    public const MAX_RAND_BIAS = 10;
    public const VALUE_PRECISION = 5;       // Ilość cyfr (razem z cyfrą jedności) wartości (wag i bias'ów) 

    public function __construct($arr, $b){  // Tablica wag, bias 
        if($arr === null)
            $this->randomize(1, self::MAX_RAND_WEIGHT, self::MAX_RAND_BIAS, self::VALUE_PRECISION);
        else if(is_numeric($arr)){
            if($this->randomize((int)$arr, self::MAX_RAND_WEIGHT, self::MAX_RAND_BIAS, self::VALUE_PRECISION) === false)
                throw new Exception("Incorrect weights count.");
        }
        else{
            if(empty($arr) || count($arr) < 1) 
                throw new Exception("The weights array is empty.");
            for($i = 0; $i < count($arr); $i++)
                if(!is_numeric($arr[$i])) 
                    throw new Exception("Weight nr. " . $i . " is not a number.");

            $this->weights = (int)$arr;
            $this->weightsCount = count($this->weights);
            $this->bias = $b;
        }
    }

    public function randomize($size, $maxRandWeigth, $maxRandBias, $precision){
        if(!is_numeric($size) || !is_numeric($maxRandWeigth) || !is_numeric($maxRandBias) || !is_numeric($precision)) return false;
        if($size < 1 || $maxRandWeigth < 0 || $maxRandBias < 0 || $precision <= 0) return false;

        $this->weightsCount = (int)$size;
        $ratio = pow(10, $precision - 1);
        $maxWeight = $maxRandWeigth * $ratio;
        $maxBias = $maxRandBias * $ratio;
        for($i = 0; $i < $this->weightsCount; $i++)
            $this->weights[$i] = rand(-$maxWeight, $maxWeight) / $ratio;
        $this->bias = rand(-$maxBias, $maxBias) / $ratio;
        return true;
    }

    public function calc($inputs){
        if(count($inputs) < $this->$weightsCount) return false;
        
        for($i = 0; $i < $this->$weightsCount; $i++){
            if(!is_numeric($inputs[$i])) return false;
            $this->preSigmoid += $this->weights[$i] * $inputs[$i];
        }
        $this->preSigmoid -= $this->bias;
        $this->value = sigmoid($this->preSigmoid);
        return $this->value;
    } 

    public function setWeight($i, $value){
        if($i < 0 || $i >= $this->$weightsCount || !is_numeric($value)) return false;
        $this->weights[$i] = $value;
        return true;
    }

    public function setWeights($values){
        if(count($values) < $this->$weightsCount) return false;
        for($i = 0; $i < $this->$weightsCount; $i++){
            if(!is_numeric($values[$i])) return false;
            $this->weights[$i] = $values[$i];
        }
        return true;
    }

    public function setBias($value){
        if(!is_numeric($value)) return false;
        $this->bias = $value;
        return true;
    }

    public function incrementWeight($i, $value){
        if($i < 0 || $i >= $this->$weightsCount || !is_numeric($value)) return false;
        $this->weights[$i] += $value;
        return true;
    } 

    public function incrementBias($value){
        if(!is_numeric($value)) return false;
        $this->bias += $value;
        return true;
    }

    public function getValue(){ return $this->value; }
    public function getPreSigmoid(){ return $this->preSigmoid; } 
    public function getBias(){ return $this->bias; }
    public function getWeightsCount(){ return $this->weightsCount; }
    public function getWeights(){ return $this->weights; }
    public function getWeight($i){
        if($i < 0 || $i >= $this->weightsCount) return false;
        return $this->weights[$i];
    }
}
?>