<?php
/**
 * Iterator for view.
 *
 * @package   Tsukiyo
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 * @copyright Copyright (c) 2012 Satoshi Nishimura
 */

/**
 * Iterator for view.
 *
 * @package Tsukiyo
 * @author  Satoshi Nishimura <nishim314@gmail.com>
 * @copyright Copyright (c) 2012 Satoshi Nishimura
 */
class Tsukiyo_Iterator implements Iterator
{
    private $orm;
    private $vo;
    private $isContinue = false;
    private $isRoot = false;
    private $stmtIndex;
    private $key;

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

        return $this->orm->cloneVo($this->vo);
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

    public function limit($limit){
        $this->orm->limit($limit);
        return $this;
    }
    public function offset($offset){
        $this->orm->offset($offset);
        return $this;
    }
}
