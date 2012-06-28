<?php

class Tsukiyo_Iterator implements Iterator
{
    private $builder;
    private $stmt;
    private $vo;
    private $isContinue;
    public function __construct(Tsukiyo_SqlBuilder $builder, $vo)
    {
        $this->builder = $builder;
        $this->vo = $vo;
    }
    public function rewind(){
        $this->stmt = $this->builder->iteratorStmt();
        $this->key = 0;
        $this->isContinue = $this->stmt->fetch(PDO::FETCH_BOUND);
    }
    public function current(){
        return $this->vo;
    }
    public function key(){
        return $this->key;
    }
    public function next(){
        $this->isContinue = $this->stmt->fetch(PDO::FETCH_BOUND);
        $this->key++;
    }
    public function valid(){
        return $this->isContinue;
    }
    public function count(){
        return $this->builder->count();
    }
}
