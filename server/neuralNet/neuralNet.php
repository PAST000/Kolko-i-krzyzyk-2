<?php
require "neuron.php";

class NeuralNet{
    private $inputsSize = 0;
    private $numOfLayers = 0;  // Bez warstwy wejść
    private $inputs = [];
    private $neurons = [];  // [warstwa, neuron]
    private $expected = [];
    private $cost = 0;
    private $neuronsDerivatives = [];
    private $gradient = [];
    private $gradientRate = 0;
    private $weightsAndBiasesCount = 0;  // Łączna liczba wag i biasów, do sprawdzania poprawności gradientu

    public const FILE_EXTENSION = ".txt";
    public const MAX_RAND_WEIGHT = 10;      // Największa (również w. bezwględna z najmniejszej) wartość wagi
    public const MAX_RAND_BIAS = 10;
    public const MAX_RATE = 100;            // Maksymalna wartość $this->gradientRate
    public const VALUE_PRECISION = 5;       // Ilość cyfr (razem z cyfrą jedności) wartości (wag i bias'ów) 
    public const LAYERS_SEPARATOR = ' ';    //
    public const NEURONS_SEPARATOR = '/';   // JEDEN ZNAK!
    public const VALUES_SEPARATOR = ';';    //

    public function __construct($neuronsSizes, $rate){
        if(count($neuronsSizes) < 2) throw new Exception("Not enough layers.");
        if((int)$neuronsSizes[0] < 1) throw new Exception("Incorrect number of inputs.");
        if(!is_numeric($rate)) throw new Exception("Rate must be a number");
        if($rate == 0) throw new Exception("Rate must not be 0.");
        file_put_contents("logsKiK2/net.txt", "2", FILE_APPEND);

        $this->numOfLayers = count($neuronsSizes) - 1;
        $this->inputsSize = (int)$neuronsSizes[0];
        $this->gradientRate = (float)$rate;
        file_put_contents("logsKiK2/sizes.txt", print_r($neuronsSizes, true), FILE_APPEND);

        for($i = 0; $i < $this->numOfLayers; $i++){
            file_put_contents("logsKiK2/net.txt", (int)(!is_numeric($neuronsSizes[$i]) || (int)$neuronsSizes[$i] < 1), FILE_APPEND);
            array_push($this->neurons, array());
            if(!is_numeric($neuronsSizes[$i]) || (int)$neuronsSizes[$i] < 1){
                throw new Exception("All neurons sizes must be numeric and greater than 0.");
                return;
            }

            file_put_contents("logsKiK2/weight.txt", $neuronsSizes[$i] . PHP_EOL . (int)$neuronsSizes[$i], FILE_APPEND);
            for($j = 0; $j < (int)$neuronsSizes[$i]; $j++){
                file_put_contents("logsKiK2/creatingNeurons.txt", $i . ' ' . $j . PHP_EOL, FILE_APPEND);
                try{
                    array_push($this->neurons[$i], new Neuron((int)$neuronsSizes[$i], null));
                }
                catch(Exception $e) {
                    file_put_contents("logsKiK2/error.txt", $e, FILE_APPEND);
                    throw new Exception("Something went wrong while creating neurons.");
                }
            }
            file_put_contents("logsKiK2/net.txt", "5", FILE_APPEND);
        }
        file_put_contents("logsKiK2/net.txt", "CONSTRUCTED", FILE_APPEND);
    }

    public function save($filename){
        if(empty($filename)) return false;
        if(!preg_match("/.+" . self::FILE_EXTENSION . "/", $filename)) $filename .= self::FILE_EXTENSION;

        $txt = count($this->inputs) . self::LAYERS_SEPARATOR;  // Pierwsza "warstwa" to ilość wejść
        for($i = 0; $i < $this->numOfLayers; $i++){
            for($j = 0; $j < count($this->neurons[$i]); $j++){
                for($k = 0; $k < $this->neurons[$i][$j]->getWeightsCount(); $k++){
                    file_put_contents("logsKiK2/save.txt", $i . ' ' . $j . ' ' . $k . PHP_EOL, FILE_APPEND);
                    if($this->neurons[$i][$j]->getWeight($k) === false){
                        fclose($file);
                        return false;
                    }
                    $txt .= $this->neurons[$i][$j]->getWeight($k);
                    $txt .= self::VALUES_SEPARATOR;
                }

                $txt .= $this->neurons[$i][$j]->getBias();
                $txt .= self::NEURONS_SEPARATOR;
            }
            file_put_contents("logsKiK2/save.txt", PHP_EOL . "AAA", FILE_APPEND);
            $txt .= self::LAYERS_SEPARATOR;
        }

        file_put_contents("logsKiK2/save.txt", PHP_EOL . "7", FILE_APPEND);
        $txt = substr($txt, 0, -1);  // Usuwam separator warstwy (Neuronów zostaje!)
        $file = fopen($filename, "w");
        file_put_contents("logsKiK2/save.txt", PHP_EOL . "8", FILE_APPEND);
        if($file === false) return false;
        fwrite($file, $txt);
        fclose($file);
        return true;
    }

    public function read($filename){
        if(empty($filename)) return false;
        if(!preg_match("/.+" . self::FILE_EXTENSION . "/", $filename)) $filename .= self::FILE_EXTENSION;
        
        $file = fopen($filename, "r");
        if($file === false) return false;
        $txt = fread($file, filesize($filename));
        fclose($file);

        $layers = explode(self::LAYERS_SEPARATOR, $txt);
        if(count($layers) < 2) return false;  // Poprawny rozmiar to: ilość warstw + 1 (inputs)
        if(empty($layers[0]) || empty($layers[1])) return false;
        if(!is_numeric($layers[0]) || (int)$layers[0] < 1) return false;

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
        return $this->getResult();
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

    public function applyGradient($gradient, $additionalRate = 1){
        if(empty($gradient) || count($gradient) < $this->weightsAndBiasesCount || !is_numeric($additionalRate)) return false;
        $gradientCounter = 0;
        $actualRate = $additionalRate * $this->gradientRate;
        $oldNeurons = $this->neurons;

        for($i = 0; $i < $this->numOfLayers; $i++)
            for($j = 0; $j < count($this->neurons[$i]); $j++){
                for($k = 0; $k < $this->neurons[$i][$j]->getWeightsCount(); $k++){
                    if(!$this->neurons[$i][$j]->incrementWeight($k, $gradient[$gradientCounter] * $actualRate)){
                        $this->neurons = $oldNeurons;
                        return false;
                    }
                    $gradientCounter++;
                }
                if(!$this->neurons[$i][$j]->incrementBias($gradient[$gradientCounter] * $actualRate)){
                    $this->neurons = $oldNeurons;
                    return false;
                }
                $gradientCounter++;
            }
        return true;
    }

    public function setExpected($new){
        if(count($new) < count($this->neurons[$this->numOfLayers - 1])) return false;
        for($i = 0; $i < count($new); $i++) if(!is_numeric($new[$i])) return false;
        $this->expected = array_slice($new, 0, count($this->neurons[$this->numOfLayers - 1]));
        return true;
    }

    public function setRate($new){
        if($new < 0 || $new > self::MAX_RATE) return false;
        $this->gradientRate = $new;
        return true;
    }

    public function getResult(){
        $arr = [];
        for($i = 0; $i < count($this->neurons[$this->numOfLayers - 1]); $i++)
            array_push($arr, $this->neurons[$this->numOfLayers - 1][$i]);
        return $arr;
    }
    public function getGradientRare(){ return $this->gradientRate; }
    public function getGradient(){ return $this->gradient; }
}
?>