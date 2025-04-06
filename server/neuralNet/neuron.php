<?php
require "sigmoid.php"; 

class Neuron{
    private $value = 0;
    private $preSigmoid = 0;
    private $weights = [];
    private $weightsCount = 0;
    private $bias = 0;

    public function __construct($arr, $b){  // Tablica wag, bias
        if(empty($arr) || count($arr) < 1) throw new Exception("The weights array is empty.");
        for($i = 0; $i < count($arr); $i++)
            if(!is_numeric($arr[$i])) 
                throw new Exception("Weight nr. " . $i . " is not a number.");

        $this->weights = (int)$arr;
        $this->weightsCount = count($this->weights);
        $this->bias = $b;
    }

    public function getValue(){ return $this->value; }
    public function getPreSigmoid(){ return $this->preSigmoid; } 
    public function getBias(){ return $this->bias; }
    public function getWeightsCount(){ return $this->weightsCount; }
    public function getWeights(){ return $this->weights; }
    public function getWeight($i){
        if($i < 0 || $i >= $this->weightsCount) return false;
        return $this->getWeights[$i];
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
}
?>