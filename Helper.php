<?php

require_once __DIR__ . '/Util.php';
class Tsukiyo_Helper
{
    public static $single;

    /**
     * PHP 5.3
     *
     * shortcut: extract(Tsukiyo_Helper::all());
     */
    public static function all($prefix = '', $suffix = ''){
        $or = function(){
            return Tsukiyo_Helper::orWhere(func_get_args());
        };
        $and = function(){
            return Tsukiyo_Helper::andWhere(func_get_args());
        };
        $eq = function($w){ return Tsukiyo_Helper::eq($w); };
        $ne = function($w){ return Tsukiyo_Helper::ne($w); };
        $lt = function($w){ return Tsukiyo_Helper::l($w); };
        $le = function($w){ return Tsukiyo_Helper::le($w); };
        $gt = function($w){ return Tsukiyo_Helper::gt($w); };
        $ge = function($w){ return Tsukiyo_Helper::ge($w); };

        $like = function($w){ return Tsukiyo_Helper::like($w); };
        $notLike = function($w){ return Tsukiyo_Helper::notLike($w); };

        $isNull = function($w){ return Tsukiyo_Helper::isNull($w); };
        $isNotNull = function($w){ return Tsukiyo_Helper::isNotNull($w); };

        return array($prefix . 'and' . $suffix => $and
                     ,$prefix . 'or'  . $suffix => $or

                     ,$prefix . 'eq'  . $suffix => $eq
                     ,$prefix . 'ne'  . $suffix => $ne

                     ,$prefix . 'lt'  . $suffix => $lt
                     ,$prefix . 'le'  . $suffix => $le
                     ,$prefix . 'gt'  . $suffix => $gt
                     ,$prefix . 'ge'  . $suffix => $ge

                     ,$prefix . 'like'. $suffix => $like
                     ,$prefix . 'notLike'. $suffix => $notLike

                     ,$prefix . 'isNull'. $suffix => $isNull
                     ,$prefix . 'isNotNull'. $suffix => $isNotNull

                     );
    }

    public static function orWhere($where){
        return self::block('or', $where);
    }
    public static function andWhere($where){
        return self::block('and', $where);
    }
    private static function block($andOr, $where){
        $ret = new Tsukiyo_Helper_WhereTree($andOr);
        foreach ($where as $child)
            $ret->add($child);
        return $ret;
    }
    public static function eq($where){
        return self::where('=', $where);
    }
    public static function ne($where){
        return self::where('<>', $where);
    }
    public static function lt($where){
        return self::where('<', $where);
    }
    public static function le($where){
        return self::where('<=', $where);
    }
    public static function gt($where){
        return self::where('>', $where);
    }
    public static function ge($where){
        return self::where('>=', $where);
    }


    public static function like($where){
        $where = self::prepareLike($where, true, true);
        return self::where('like', $where);
    }
    public static function notLike($where){
        $where = self::prepareLike($where, true, true);
        return self::where('not like', $where);
    }
    public static function starts($where){
        $where = self::prepareLike($where, false, true);
        return self::where('like', $where);
    }
    public static function notStarts($where){
        $where = self::prepareLike($where, false, true);
        return self::where('not like', $where);
    }
    public static function ends($where){
        $where = self::prepareLike($where, true, false);
        return self::where('like', $where);
    }
    public static function notEnds($where){
        $where = self::prepareLike($where, true, false);
        return self::where('not like', $where);
    }

    public static function isNull($where){
        return self::singleWhere('is null', $where);
    }
    public static function isNotNull($where){
        return self::singleWhere('is not null', $where);
    }
    public static function where($op, $where){
        $ret = null;
        foreach ($where as $k => $v){
            $w = new Tsukiyo_Helper_WhereNode($op, $k, $v);
            if ($ret)
                $ret = $ret->add($w);
            else
                $ret = $w;
        }
        return $ret;
    }
    public static function singleWhere($op, $where){
        $ret = null;
        $where = (array)$where;
        foreach ($where as $k){
            $w = new Tsukiyo_Helper_WhereNode($op, $k, null, true);
            if ($ret)
                $ret = $ret->add($w);
            else
                $ret = $w;
        }
        return $ret;
    }
    public static function prepareLike($where, $left, $right){
        foreach ($where as $k => $v){
            $v = str_replace('\\', '\\\\', $v);
            $v = str_replace('%', '\\%', $v);
            $v = str_replace('_', '\\_', $v);
            if ($left)
                $v = '%' . $v;
            if ($right)
                $v = $v . '%';
            $where[$k] = $v;
        }
        return $where;
    }
}

class Tsukiyo_Helper_Single {
    public static $instance;
}
Tsukiyo_Helper::$single = new Tsukiyo_Helper_Single();

interface Tsukiyo_Helper_Where{
    public function add(Tsukiyo_Helper_Where $where);
    public function getString();
    public function getParams();
    public function isSingle();
}
class Tsukiyo_Helper_WhereNode implements Tsukiyo_Helper_Where{
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
    public function add(Tsukiyo_Helper_Where $where){
        $ret = new Tsukiyo_Helper_WhereTree('and');
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
class Tsukiyo_Helper_WhereTree implements Tsukiyo_Helper_Where{
    private $children = array();
    private $andOr;
    public function __construct($andOr){
        $this->andOr = $andOr;
    }
    public function add(Tsukiyo_Helper_Where $where){
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
