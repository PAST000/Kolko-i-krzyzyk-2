<?php
class ObjectsArr{   
    private $first = [];
    private $second = [];

    function __construct(){}

    function getFirst($sec){
        $key = $this->getIdBySecond($sec);
        if(!$key) return false;
        return $this->first[$key];
    }

    function getSecond($frst){
        $key = $this->getIdByFirst($frst);
        if(!$key) return false;
        return $this->second[$key];
    }

    function add($frst, $sec){
        $this->first[] = $frst;
        $this->second[] = $sec;
    }

    function delByFirst($frst){
        $key = getIdByFirst($frst);
        return $this->delByID($key);
    }

    function delBySecond($sec){
        $key = getIdBySecond($sec);
        return $this->delByID($key);
    }

    function delByID($id){
        if(!isset($this->first[$id]) || !isset($this->second[$id])) return false;
        array_slice($this->first, $id, 1);
        array_slice($this->second, $id, 1);
        return true;
    }

    function pop(){
        array_pop($this->first);
        array_pop($this->second);
    }

    function setFirst($sec, $new){ $first[$this->idBySecond($sec)] = $new; }
    function setSecond($frst, $new){ $second[$this->idByFirst($frst)] = $new; }

    function idByFirst($frst){ return array_search($frst, $this->first); }
    function idBySecond($sec){ return array_search($sec, $this->second); }

    function firstByID($id){ return $this->first[$id]; }
    function secondByID($id){ return $this->second[$id]; }

    function getFirstArr(){ return $this->first; }
    function getSecondArr(){ return $this->second; }
    function count(){ return count($this->first); }
}
?>