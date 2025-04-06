<?php
require "neuron.php";

class NeuralNet{
    private $inputsSize = 0;
    private $numOfLayers = 0;
    private $inputs = [];
    private $neurons = [];  // [warstwa, neuron]
    private $expected = [];
    private $cost = 0;
    private $neuronsDerivatives = [];
    private $gradient = [];
    private $gradientRate = 0;
    private $weightsAndBiasesCount = 0;  // Łączna liczba wag i biasów, do sprawdzania poprawności gradientu

    public const MAX_RATE = 100;            // Maksymalna wartość $this->gradientRate
    public const LAYERS_SEPARATOR = ' ';    //
    public const NEURONS_SEPARATOR = '/';   // JEDEN ZNAK!
    public const VALUES_SEPARATOR = ';';    //

    public function __construct($layers, $neurons, $rate){
        if(!is_numeric($layers) || $layers < 0) throw new Exception("Incorrect number of layers.");
        if(count($neurons) < $layers) throw new Exception("Not enough neurons.");
        $this->numOfLayers = $layers;
        $this->inputsSize = $neurons[0];
        $this->gradientRate = $rate;
    }

    public function save($filename){
        $txt = (string)count($this->inputs) . self::LAYERS_SEPARATOR;  // Pierwsza "warstwa" to ilość wejść
        for($i = 0; $i < $this->numOfLayers; $i++){
            for($j = 0; $j < count($this->neurons[$i]); $j++){
                for($k = 0; $k < $this->neurons[$i][$j]->getWeightsCount(); $k++){
                    if(!$this->neurons[$i][$j]->getWeight($k)){
                        fclose($file);
                        return false;
                    }
                    $txt .= $this->neurons[$i][$j]->getWeight($k);
                    $txt .= self::VALUES_SEPARATOR;
                }

                $txt .= $this->neurons[$i][$j]->getBias();
                $txt .= self::NEURONS_SEPARATOR;
            }
            $txt .= self::LAYERS_SEPARATOR;
        }

        $txt = substr($txt, 0, -2);  // Usuwamy dwa separatory
        $file = fopen($filename . ".txt", "w");
        if($file === false) return false;
        fwrite($file, $txt);
        fclose($file);
        return true;
    }

    public function read($filename){
        $file = fopen($filename . ".txt", "r");
        if($file === false) return false;
        $txt = fread($file, filesize($filename . ".txt"));
        fclose($file);

        $layers = explode(self::LAYERS_SEPARATOR, $txt);
        if(count($layers) < 2) return false;  // Poprawny rozmiar to: ilość warstw + 1 (inputs)
        if(count($layers[0]) < 1 || count($layers[1]) < 1) return false;
        if(!is_numeric($layers[0])) return false;

        $oldNeurons = $this->neurons;
        $oldInputsSize = count($this->inputsSize);

        $this->inputsSize = (int)$layers[0];
        $this->inputs = [];   // Zmienilismy rozmiar, zatem czyścimy tablice
        $this->numOfLayers = count($layers) - 1;
        $this->neurons = [];
        $previousLayerSize = $this->inputsSize;

        for($i = 0; $i < $this->numOfLayers; $i++){
            array_push($this->neurons, array());
            $neurons = explode(self::NEURONS_SEPARATOR, $layers[$i + 1]);

            for($j = 0; $j < count($neurons) - 1; $j++){
                try{
                    $values = explode(self::VALUES_SEPARATOR, $neurons[$j]);
                    if(count($values) !== $previousLayerSize + 1) throw("Incorrect weights and/or bias.");
                    $neuron = new Neuron(array_slice($values, 0, -1), array_slice($values, -1));
                }
                catch(Exception $e) {
                    $this->inputsSize = $oldInputsSize;  // Przywracamy stan z przed odczytu
                    $this->neurons = $oldNeurons;
                    return false;
                }
                array_push($this->neurons[$i], $neuron);
            }

            $previousLayerSize = count($this->neurons[$i]);
            if($previousLayerSize < 1){
                $this->inputsSize = $oldInputsSize;  // Przywracamy stan z przed odczytu
                $this->neurons = $oldNeurons;
                return false;
            }
        }
        return true;
    }

    public function setRate($new){
        if($new < 0 || $new > self::MAX_RATE) return false;
        $this->gradientRate = $new;
        return true;
    }

    public function calc($inps){
        if(count($inps) < $this->inputsSize) return false;
        $this->inputs = array_slice($inps, 0, $this->inputsSize);
        $currInps = [];
        $nextInps = $this->inputs;
        
        for($i = 0; $i < $this->numOfLayers; $i++){
            $currInps = $nextInps;
            $nextInps = [];

            for($j = 0; $j < count($this->neurons[$i]); $j++){
                $this->neurons[$i][$j]->calc($currInps);
                if($i < $inputsSize - 1) array_push($nextInps, $this->neurons[$i][$j]->getValue());
            }
        }
    }

    public function getResult(){
        $arr = [];
        for($i = 0; $i < count($this->neurons[$this->numOfLayers - 1]); $i++)
            array_push($arr, $this->neurons[$this->numOfLayers - 1][$i]);
        return $arr;
    }

    public function getGradient(){ return $this->gradient; }

    public function setExpected($new){
        if(count($new) < count($this->neurons[$this->numOfLayers - 1])) return false;
        for($i = 0; $i < count($new); $i++) if(!is_numeric($new[$i])) return false;
        $this->expected = array_slice($new, 0, count($this->neurons[$this->numOfLayers - 1]));
        return true;
    }

    public function calcDerivatives($exp){
        if(!setExpected($exp)) return false;

        for($i = 0; $i < count($this->neurons[$this->numOfLayers - 1]); $i++)
            $this->neuronsDerivatives[$numOfLayers - 1][$i] = 2*abs($this->neurons[$numOfLayers - 1][$i]->getValue() - $this->expected[$i]);

        for($i = $this->numOfLayers - 2; $i >= 0; $i--)
            for($j = 0; $j < count($this->neurons[$i]); $j++){
                $this->neuronsDerivatives[$i][$j] = 0;
                for($k = 0; $k < count($this->neurons[$i + 1]); $k++)
                    $this->neuronsDerivatives[$i][$j] += ($this->neurons[$i + 1][$k]->getWeight($j)
                                                        * $this->neuronsDerivatives[$i + 1][$k]
                                                        * sigmoidDerivative($this->neurons[$i + 1][$k]->getPreSigmoid()));
            }
        return true;
    }

    public function calcGradient($inps, $exp){  // Właściwie to wektor przeciwny do gradientu
        if(!$this->calc($inps)) return false;
        if(!$this->calcDerivatives($exp)) return false;
        $this->gradient = [];
        $sigmDeriv = 0;

        for($i = 0; $i < $this->numOfLayers; $i++)
            for($j = 0; $j < count($this->neurons[$i]); $j++){
                $sigmDeriv = sigmoidDerivative($this->neurons[$i][$j]->getPreSigmoid());

                for($k = 0; $k < count($this->neurons[$i][$j]->getWeights()); $k++)
                    array_push($this->gradient, (-1) * ($i === 0 ? $inps[$k] : $this->neurons[$i - 1][$k]->getValue()) * $sigmDeriv);
                array_push($this->gradient, (-1) * $sigmDeriv);
            }
        return $this->gradient;
    }

    public function applyGradient($gradient){
        if(empty($gradient) || count($gradient) < $this->weightsAndBiasesCount) return false;
        $gradientCounter = 0;
        $oldGradient = [];

        for($i = 0; $i < $this->numOfLayers; $i++)
            for($j = 0; $j < count($this->neurons[$i]); $j++){
                for($k = 0; $k < $this->neurons[$i][$j]->getWeightCount(); $k++){
                    array_push($oldGradient, $gradient[$gradientCounter]);
                    if(!$this->neurons[$i][$j]->setWeight($k, $gradient[$gradientCounter])){
                        $this->restoreGradient($oldGradient);
                        return false;
                    }
                    $gradientCounter++;
                }

                array_push($oldGradient, $gradient[$gradientCounter]);
                if(!$this->neurons[$i][$j]->setBias($gradient[$gradientCounter])){
                    $this->restoreGradient($oldGradient);
                    return false;
                }
                $gradientCounter++;
            }
        return true;
    }

    private function restoreGradient($gradient){  // Jeśli applyGradient zwróci false, to przywracamy stan poprzedni
        if(empty($gradient)) return false;
        $gradientCounter = 0;

        for($i = 0; $i < $this->numOfLayers; $i++)
            for($j = 0; $j < count($this->neurons[$i]); $j++){
                for($k = 0; $k < $this->neurons[$i][$j]->getWeightCount(); $k++){
                    if(!$this->neurons[$i][$j]->setWeight($k, $gradient[$gradientCounter])) return false;
                    $gradientCounter++;
                    if($gradientCounter >= count($gradient)) return false;
                }

                if(!$this->neurons[$i][$j]->setBias($gradient[$gradientCounter])) return false;
                $gradientCounter++;
                if($gradientCounter >= count($gradient)) return false;
            }
        return true;
    }

}
?>