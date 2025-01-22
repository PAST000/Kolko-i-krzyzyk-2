<?php 
class Direction{
    private $arr = [];
    private $size;

    function __construct($n){
        $this->size = (int)$n;
        for($i = 0; $i < $this->size; $i++)
            $this->arr[$i] = -1;
    }

    function increment(){
        for($i = $this->size - 1; $i >= 0; $i--){
            if($this->arr[$i] === 1) $this->arr[$i] = -1;
            else{
                $this->arr[$i] += 1;
                break;
            }
        }

        for($i = 0; $i < $this->size; $i++){  //Sprawdzenie czy same 0
            if($this->arr[$i] !== 0) break;
            else    
                if($i === $this->size)
                    $this->arr[$i] += 1;
        }
    }
}
?>