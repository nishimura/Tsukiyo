<?php

interface Tsukiyo_Where {
    public function add(Tsukiyo_Where $where);
    public function getString();
    public function getParams();
    public function isSingle();
}
class Tsukiyo_WhereNode implements Tsukiyo_Where{
    private $op;
    private $name;
    private $value;
    private $single;
    public function __construct($op, $voName, $value, $isSingle = false){
        $this->op = $op;
        $this->name = Tsukiyo_Util::toDbName($voName);
        $this->value = $value;
        $this->single = $isSingle;
    }
    public function add(Tsukiyo_Where $where){
        $ret = new Tsukiyo_WhereTree('and');
        $ret->add($this);
        $ret->add($where);
        return $ret;
    }
    public function getString(){
        $ret = " $this->name $this->op ";
        if ($this->single)
            return $ret;

        return $ret . ' ? ';
    }
    public function isSingle(){
        return $this->single;
    }
    public function getParams(){
        return $this->value;
    }
}
class Tsukiyo_WhereTree implements Tsukiyo_Where{
    private $children = array();
    private $andOr;
    public function __construct($andOr){
        $this->andOr = $andOr;
    }
    public function add(Tsukiyo_Where $where){
        $this->children[] = $where;
        return $this;
    }

    public function getString(){
        if (count($this->children) === 0)
            return null;
        $strings = array();
        foreach ($this->children as $child){
            $strings[] = $child->getString();
        }
        return '(' . implode(' '.$this->andOr.' ', $strings) . ')';
    }
    public function isSingle(){
        return false;
    }
    public function getParams(){
        $ret = array();
        foreach ($this->children as $child){
            if ($child->isSingle())
                continue;
            $params = $child->getParams();
            if (is_array($params)){
                foreach($params as $param)
                    $ret[] = $param;
            }else{
                $ret[] = $params;
            }
        }
        return $ret;
    }
}
