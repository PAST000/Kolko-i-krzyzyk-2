<?php
class RandomBot{
    private $boardSize = 0;
    private $boardSeparator = ',';
    public const PAWNS = ['O', 'X', 'P'];

    public function __construct($boardSize){
        $this->boardSize = $boardSize;
    }

    public function makeMove($txt, $self){
        $selfPawn = $self;
        if(is_numeric($self)){
            if($self < 0 || $self >= count(self::PAWNS) || empty(self::PAWNS[(int)$self])) return false;
            $selfPawn = self::PAWNS[(int)$self];
        }
        else
            if(array_search($self, self::PAWNS) === false) return false;

        $board = explode($this->boardSeparator, $txt);
        if(count($board) !== $this->boardSize) return false;

        $availableFields = [];
        for($i = 0; $i < count($board); $i++)
            if(empty($board[$i])) 
                $availableFields[] = $i;

        if(count($availableFields) === 0) return false;
        return $availableFields[rand(0, count($availableFields) - 1)];
    }

    public function gameResult($res){}

    public function setBoardSeparator($separator){ 
        if(strlen($separator) !== 1) return false;
        $this->boardSeparator = $separator; 
        return true;
    }
    public function getBoardSeparator(){ return $this->boardSeparator; }
}
?>