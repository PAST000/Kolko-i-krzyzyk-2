<?php 
require "direction.php";

class Board{
    private $board = [];    // Tablica z postawionymi pionkami, puste pole - null
    private $dims = 3;      // Liczba wymiarów
    private $sizes = [];    // Tablica z rozmiarami
    private $sizesMult = 1; // Przemnożone rozmiary
    private $target = 3;    // Zwycięzka ilość w jednej linii
    private $dirs = [];
    private const pawns = ['O', 'X', 'P']; // Kula, krzyżyk, piramida

    function initDirs(){
        $dir = new Direction($this->dims);
        for($i = 0; $i < pow(3, $this->dims) - 1; $i++){
            $this->dirs[$i] = $dir;
            $dir->increment();
        }
    }

    function __construct($sizesArr, $trg){
        if(count($sizesArr) < 2)
            throw new Exception("Liczba wymiarów musi być większa od 1");
        if($trg < 3)
            throw new Exception("Zwycięzka ilosć w jednej linii musi być większa od 2");
        for($i = 0; $i < count($sizesArr); $i++){
            if($sizesArr[$i] <= 0)
                throw new Exception("Każdy rozmiar musi być dodatni");
        }

        $this->dims = count($sizesArr);
        $this->sizes = $sizesArr;
        $this->targer = $trg;
        
        for($i = 0; $i < $this->dims; $i++)
            $this->sizesMult *= $this->sizes[$i];
        for ($i = 0; $i < $this->sizesMult; $i++) 
            $this->board[$i] = null;
        $this->initDirs();
    }

    function __destruct(){}

    function put($v, $pawn){ 
        $id = arrToId($v);
        if($id === false) return false; 
        if($id < 0 || $id >= count($this->board)) return false;
        if($pawn < 0 || $pawn > count($this->pawns)) return false;

        $this->board[$id] = $this->pawns[$pawn];
        return true;
    }

    function clear($id){
        if($id < 0 || $id >= count($this->board)) return false;
        $this->board[$id] = null;
        return true;
    }

    function clearPlayer($id){
        if($id < 0 || $id >= count($this->board)) return false;
        for($i = 0; $i < count($this->board); $i++){
            if($this->board[$i] == $this->pawns[$id]){
                $this->board[$i] = null;
            }
        }
        return true;
    }

    function check(){  //Zwraca id pola i id kierunku
        for($i = 0; $i < count($this->board); $i++){
            if($this->board[$i] === null) continue;

            for($j = 0; $j < count($this->dirs); $j++){
                if(!isInRange($i, $j)) continue;

                for($k = 0; $k < $this->target; $k++){
                    if($this->board[$i] !== $this->board[arrToId(translate($i, $this->dirs[$j], $k))]) break;
                    if($k === $this->target - 1) return [$i, $j];  //Jeśli sprawdziliśmy już wystarczającą do wygranej ilość pól
                }
            }
        }
        return null;
    }

    function isInRange($id, $n){ //n - indeks kierunków
        if($id < 0 || $id >= count($this->board)) return false;
        $v = idToArr($id);
        for($i = 0; $i < $this->dims; $i++){
            $v[$i] += $this->target*($this->dirs[$n][$i]);
            if($v[$i] < 0 || $v[$i] >= $this->sizes[$i])
                return false;
        }
        return true;
    }

    function translate($id, $v, $k = 1){
        if($id < 0 || $id >= count($this->board)) return false;
        if(count($v) < $this->dims) return false;

        $translated = $this->board[$id];
        for($i = 0; $i < count($translated); $i++){
            $translated[$i] *= ($v[$i] * $k);
        }
        return $translated;
    }

    function idToArr($id){
        if($id < 0 || $id >= count($this->board)) return false;
        $arr = [];
        $m = $this->sizesMult;
        for($i = $this->dims - 1; $i >= 0; $i--){
            $m /= $this->sizes[$i];
            $arr[$i] = floor($id / $m);
            $id -= arr[$i] * $m;
        }
    }

    function arrToId($v){
        if(count($v) < $this->dims) return false;
        $id = 0;
        $m = 1;
        for($i = 0; $i < $this->dims; $i++){
            $id += $v[$i] * $m;
            $m *= $this->sizes[$i];
        }
        return $id;
    }

    function getTarget(){
        return $this->target;
    }

    function getDims(){
        return $this->dims;
    }

    function getSizes(){
        return $this->sizes;
    }

    function implode($separator = ','){
        return implode($separator, $this->board);
    }
}
?>