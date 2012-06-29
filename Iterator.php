<?php

class Tsukiyo_Iterator implements Iterator
{
    private $orm;
    private $vo;
    private $isContinue = false;
    private $isRoot = false;
    private $stmtIndex;

    private $parentVo;
    private $parentPkeys;
    private $childIterator;

    private $previousPkeys;
    private $cloneVo = false;
    public function __construct($orm, $vo)
    {
        $this->orm = $orm;
        $this->vo = $vo;
    }
    public function rewind(){
        $this->key = 0;
        if ($this->isRoot){
            $ret = $this->orm->fetchIfNotMove($this->stmtIndex);
            if ($ret === false){
                $this->isContinue = false;
            }else{
                $this->stmtIndex = $ret;
                $this->isContinue = true;
            }
        }else{
            $this->stmtIndex = $this->orm->getStmtIndex();
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
        if (!$this->cloneVo)
            return $this->vo;

        $clone = clone $this->vo;
        foreach ($this->vo as $k => $v){
            unset($clone->$k);
            $clone->$k = $v;
        }
        return $clone;
    }
    public function key(){
        return $this->key;
    }
    public function next(){
        if ($this->parentVo){
            $this->previousPkeys = $this->getPkeyValues();
        }

        $ret = $this->orm->fetchIfNotMove($this->stmtIndex);
        if ($ret === false){
            $this->isContinue = false;
        }else{
            $this->stmtIndex = $ret;
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

    /**
     * Need if using parent value after execute foreach child iterator.
     */
    public function setCloneVo($cloneVo){
        $this->cloneVo = $cloneVo;
        return $this;
    }
}
