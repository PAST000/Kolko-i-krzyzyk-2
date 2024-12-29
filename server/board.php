<?php 
class Board{
    private $board = [];
    private $dims = 3;  //Liczba wymiarów
    private $sizeX;
    private $sizeY;
    private $sizeZ;
    private $sizes = [];

    private $target; //Zwycięzka ilość w jednej linii
    private $dirs = [];
    private const $pawns = ['O', 'K', 'P']; //Kula, krzyżyk, piramida

    function __construct($sizesArr, $trg){
        $this->dims = count($sizesArr);
        $this->sizes = $sizesArr;
        $this->sizeX = $sizesArr[0];
        $this->sizeY = $sizesArr[1];
        $this->sizeZ = $sizesArr[2];
        $this->targer = $trg;

        for ($i = 0; $i < $this->sizes[0]; $i++) {
            $array[$i] = []; 
            for ($j = 0; $j < $this->sizes[1]; $j++) {
                $array[$i][$j] = []; 
                for ($k = 0; $k < $this->sizes[2]; $k++) {
                    $array[$i][$j][$k] = null; 
                }
            }
        }
        initDirs();
    }

    function initDirs(){
        $dir = new Direction($this->dims);
        for($i = 0; $i < pow(3, $this->dims) - 1; $i++){
            $this->dirs[$i] = $dir;
            $dir->increment();
        }
    }

    function put($x, $y, $z, $id){  //$id - numer "pionka"
        if($x >= $this->sizes[0] || $y >= $this->sizes[1] || $z >= $this->sizes[2]){
            return false;
        }
        if($this->board[$x][$y][$z] !== null){
            return false;
        }
        if($id >= count($this->pawns) || $id < 0){
            return false;
        }

        $this->board[$x][$y][$z] = $this->pawns[$id];
        return true;
    }

    function clear($x, $y, $z){
        if($x >= $this->sizes[0] || $y >= $this->sizes[1] || $z >= $this->sizes[2]){
            return false;
        }
        if($this->board[$x][$y][$z] === null){
            return false;
        }
        
        $this->board[$x][$y][$z] = null;
        return true;
    }

    function clearPlayer($id){}

    function check(){
        for($i = 0; $i < $this->sizes[0]; $i++){
            for($j = 0; $j < $this->sizes[1]; $j++){
                for($k = 0; $k < $this->sizes[2]; $k++){
                    if(board[$i][$j][$k] === null) continue;

                    for($n = 0; $n < count($this->dirs); $n++){
                        if(!isInRange($i, $j, $k, $n)) continue;

                        for($m = 0; $m < $this->target; $m++){
                            if($this->board[$i + $m * $this->dirs[$n][0]][$j + $m * $this->dirs[$n][1]][$k + $m * $this->dirs[$n][2]] != $this->board[$i][$j][$k]) break;
                            if($m == $this->target - 1) return $this->board[$i][$j][$k];
                        }
                    }
                }
            }
        }
        return null;
    }

    function isInRange($x, $y, $z, $n){ //n - indeks kierunków
        $x += $this->target*($this->dirs[$n][0]); 
        $y += $this->target*($this->dirs[$n][1]);
        $z += $this->target*($this->dirs[$n][2]);

        if($x < 0 || $x >= $this->sizes[0]){
            return false;
        }
        if($y < 0 || $y >= $this->sizes[1]){
            return false;
        }
        if($z < 0 || $z >= $this->sizes[2]){
            return false;
        }
        return true;
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
}

class Direction{
    private $arr = [];
    private $size;

    function __construct($n){
        for($i = 0; $i < $n; $i++){
            $this->arr[$i] = -1;
        }
        $this->size = $n;
    }

    function increment(){
        for($i = $this->size; $i >=0; $i--){
            if($this->arr[$i] === 1){
                $this->arr[$i] = -1;
            }
            else{
                $this->arr[$i] += 1;
                break;
            }
        }

        for($i = 0; $i <= $this->size; $i++){  //Sprawdzenie czy same 0
            if($this->arr[$i] !== 0) 
                break;
            else    
                if($i === $this->size)
                    $this->arr[$i] += 1;
        }
    }
}
?>