<?php
/**
 * Sql Conditions generator.
 *
 * @package   Tsukiyo
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 * @copyright Copyright (c) 2012 Satoshi Nishimura
 */

/**
 * Sql Conditions generator interface.
 *
 * @package Tsukiyo
 * @author  Satoshi Nishimura <nishim314@gmail.com>
 * @copyright Copyright (c) 2012 Satoshi Nishimura
 */
interface Tsukiyo_Where {
    public function add(Tsukiyo_Where $where);
    public function getString();
    public function getParams();
    public function isNoParam();
}

/**
 * Single Condition
 * @package Tsukiyo
 */
class Tsukiyo_WhereNode implements Tsukiyo_Where{
    private $op;
    private $name;
    private $value;
    private $noParam;
    public function __construct($op, $voName, $value, $isNoParam = false){
        $this->op = $op;
        $this->name = Tsukiyo_Util::toDbName($voName);
        $this->value = $value;
        $this->noParam = $isNoParam;
    }
    public function add(Tsukiyo_Where $where){
        $ret = new Tsukiyo_WhereTree('and');
        $ret->add($this);
        $ret->add($where);
        return $ret;
    }
    public function getString(){
        $ret = " $this->name $this->op ";
        if ($this->noParam)
            return $ret;

        return $ret . ' ? ';
    }
    public function isNoParam(){
        return $this->noParam;
    }
    public function getParams(){
        return $this->value;
    }
}

/**
 * Conditions Tree
 * @package Tsukiyo
 */
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
    public function isNoParam(){
        return false;
    }
    public function getParams(){
        $ret = array();
        foreach ($this->children as $child){
            if ($child->isNoParam())
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

    public function eq($where){
        return $this->add(Tsukiyo_Helper::where('=', $where));
    }
    public function ne($where){
        return $this->add(Tsukiyo_Helper::where('<>', $where));
    }
    public function lt($where){
        return $this->add(Tsukiyo_Helper::where('<', $where));
    }
    public function le($where){
        return $this->add(Tsukiyo_Helper::where('<=', $where));
    }
    public function gt($where){
        return $this->add(Tsukiyo_Helper::where('>', $where));
    }
    public function ge($where){
        return $this->add(Tsukiyo_Helper::where('>=', $where));
    }


    public function like($where){
        return $this->add(Tsukiyo_Helper::like($where, true, true));
    }
    public function notLike($where){
        return $this->add(Tsukiyo_Helper::like($where, true, true, true));
    }
    public function starts($where){
        return $this->add(Tsukiyo_Helper::like($where, false, true));
    }
    public function notStarts($where){
        return $this->add(Tsukiyo_Helper::like($where, false, true, true));
    }
    public function ends($where){
        return $this->add(Tsukiyo_Helper::like($where, true, false));
    }
    public function notEnds($where){
        return $this->add(Tsukiyo_Helper::like($where, true, false, true));
    }

    public function isNull($where){
        return $this->add(Tsukiyo_Helper::noParamWhere('is null', $where));
    }
    public function isNotNull($where){
        return $this->add(Tsukiyo_Helper::noParamWhere('is not null', $where));
    }
}
