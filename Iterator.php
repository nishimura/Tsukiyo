<?php

class Tsukiyo_Iterator implements Iterator
{
    private $orm;
    private $vo;
    private $isContinue = false;
    private $isRoot = false;

    private $parentVo;
    private $parentPkeys;
    private $childIterator;

    private $previousPkeys;
    public function __construct($orm, $vo)
    {
        $this->orm = $orm;
        $this->vo = $vo;
    }
    public function rewind(){
        $this->key = 0;
        $this->stmt = $this->orm->getStmt();
        //echo 'rewind: ' . get_class($this->vo) . "<br>\n";
        if ($this->isRoot){
            $this->isContinue = $this->stmt->fetch(PDO::FETCH_BOUND);
        }else{
            $this->isContinue = true;
        }
    }
    private function getPkeyValues(){
        $currentPkeys = array();
        foreach ($this->parentPkeys as $pkey){
            $currentPkeys[] = $this->parentVo->$pkey;
        }
        return $currentPkeys;
    }
    public function current(){
        return $this->vo;
    }
    public function key(){
        return $this->key;
    }
    public function next(){
        //echo 'next: ' . get_class($this->vo) . "<br>\n";
        $this->stopper++;
        if ($this->stopper > 100){
            // debug
            $this->isContinue = false;
            return;
        }

        if ($this->parentVo){
            $this->previousPkeys = $this->getPkeyValues();
            //var_dump($this->previousPkeys);
        }
        if (!$this->stmt){
            $this->isContinue = false;
        }else if (!$this->childIterator){
            // leaf
            $this->isContinue = $this->stmt->fetch(PDO::FETCH_BOUND);
            if (!$this->isContinue)
                $this->orm->removeStmt();
        }

        if ($this->parentVo){
            $currentPkeys = $this->getPkeyValues();
            if ($this->previousPkeys !== $currentPkeys)
                $this->isContinue = false;
        }else if ($this->isRoot){
            $stmt = $this->orm->getStmt();
            if (!$stmt)
                $this->isContinue = false;
        }

        $this->key++;
    }
    public function valid(){
        return $this->isContinue;
    }
    public function setChild($name, $child){
        $this->vo->$name = $child;
        return $this;
    }
    public function getVo(){
        return $this->vo;
    }
    public function count(){
        return $this->orm->count();
    }
    public function setParent($vo, $pkeys){
        $this->parentVo = $vo;
        $this->parentPkeys = $pkeys;
    }
    public function setChildIterator($child){
        $this->childIterator = $child;
    }
    public function setRoot(){
        $this->isRoot = true;
    }
}
