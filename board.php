<?php 
require "direction.php";

class Board{
    private $board = [];    // Tablica z postawionymi pionkami, puste pole - null
    private $dims = 3;      // Liczba wymiarów
    private $sizes = [];    // Tablica z rozmiarami
    private $sizesMult = 1; // Przemnożone rozmiary
    private $target = 3;    // Zwycięzka ilość w jednej linii
    private $dirs = [];

    public const PAWNS = ['O', 'X', 'P']; // Kula, krzyżyk, piramida

    function __construct($sizesArr, $trg){
        if(count($sizesArr) < 2) throw new Exception("Liczba wymiarów musi być większa od 1");
        if($trg < 3) throw new Exception("Zwycięzka ilosć w jednej linii musi być większa od 2");
        for($i = 0; $i < count($sizesArr); $i++)
            if($sizesArr[$i] <= 0)
                throw new Exception("Każdy rozmiar musi być dodatni");

        $this->dims = count($sizesArr);
        $this->sizes = $sizesArr;
        $this->target = $trg;
        
        for($i = 0; $i < $this->dims; $i++)
            $this->sizesMult *= $this->sizes[$i];
        $this->board = array_fill(0, $this->sizesMult, null);
        $this->initDirs();
    }

    function __destruct(){}

    function initDirs(){
        $dir = new Direction($this->dims);
        for($i = 0; $i < pow(3, $this->dims) - 1; $i++){
            $this->dirs[$i] = clone $dir;
            $dir->increment();
        }
    }

    function put($v, $pawn){ 
        $id = $this->arrToId($v);
        if($id === false) return false; 
        if($id < 0 || $id >= count($this->board)) return false;
        if($pawn < 0 || $pawn >= count(self::PAWNS)) return false;
        if($this->board[$id] !== null) return false;
        $this->board[$id] = self::PAWNS[$pawn];
        return true;
    }
    function putByID($id, $pawn){
        return $this->put($this->idToArr($id), $pawn);
    }

    function clear($v){ 
        return $this->clearByID($this->idToArr($v)); 
    }
    function clearByID($id){
        if($id < 0 || $id >= count($this->board)) return false;
        $this->board[$id] = null;
        return true;
    }

    function clearPlayer($id){
        if($id < 0 || $id >= count(self::PAWNS)) return false;
        for($i = 0; $i < count($this->board); $i++){
            if($this->board[$i] == self::PAWNS[$id]){
                $this->board[$i] = null;
            }
        }
        return true;
    }

    function checkWin(){  //Zwraca id pola i id kierunku
        if(empty($this->board)) return false;
        $dirsCount = count($this->dirs);

        for($i = 0; $i < count($this->board); $i++){
            if($this->board[$i] === null) continue;

            for($j = 0; $j < $dirsCount; $j++){
                if(!$this->isInRange($i, $j)) continue;

                for($k = 1; $k < $this->target; $k++){
                    $translated = $this->arrToId($this->translate($i, $this->dirs[$j], $k));
                    if($translated === false) break;
                    if($this->board[$i] !== $this->board[$translated]) break;
                    if($k === $this->target - 1) return [$i, $j];  //Jeśli sprawdziliśmy już wystarczającą do wygranej ilość pól
                }
            }
        }
        return null;
    }

    function checkTie(){ 
        return $this->countEmpty() > 0 ? false : true;
    }

    function isInRange($id, $n){ //n - indeks kierunków
        if($id < 0 || $id >= count($this->board)) return false;
        $v = $this->idToArr($id);

        for($i = 0; $i < $this->dims; $i++){
            $v[$i] += ($this->target - 1)*($this->dirs[$n]->getDirection()[$i]);
            if($v[$i] < 0 || $v[$i] >= $this->sizes[$i])
                return false;
        }
        return true;
    }

    function translate($id, $v, $k = 1){   // id pola, wektor przesunięcia, współczynnik wektora
        if($id < 0 || $id >= count($this->board)) return false;
        if($v->getSize() < $this->dims) return false;

        $translated = $this->idToArr($id);
        for($i = 0; $i < count($translated); $i++)
            $translated[$i] += ($v->getDirection()[$i] * $k);
        return $translated;
    }

    function idToArr($id){
        if($id < 0 || $id >= count($this->board)) return false;
        $arr = [];
        $m = $this->sizesMult;

        for($i = $this->dims - 1; $i >= 0; $i--){
            $m /= $this->sizes[$i];
            $arr[$i] = floor($id / $m);
            $id -= $arr[$i] * $m;
        }
        return $arr;
    }

    function arrToId($v){
        if(count($v) < $this->dims) return false;
        $id = 0;
        $m = 1;
        for($i = 0; $i < $this->dims; $i++){
            if($v[$i] < 0 || $v[$i] >= $this->sizes[$i]) return false;
            $id += $v[$i] * $m;
            $m *= $this->sizes[$i];
        }
        return $id;
    }

    function getPlayerByField($fieldId){
        if($this->board[$fieldId] === null) return false;
        return array_search($this->board[$fieldId], self::PAWNS);
    }

    function countEmpty(){
        $count = 0;
        for($i = 0; $i < count($this->board); $i++)
            if($this->board[$i] === null) 
                $count++;
        return $count;
    }

    function getTarget(){ return $this->target; }
    function getDims(){ return $this->dims; }
    function getDirections(){ return $this->dirs; }
    function getSizes(){ return $this->sizes; }
    function count(){ return $this->sizesMult; }
    function serializeSizes($separator = ','){ return implode($separator, $this->sizes); }
    function implode($separator = ','){ return implode($separator, $this->board); }
}
?>