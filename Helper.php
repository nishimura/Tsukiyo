<?php

require_once __DIR__ . '/Util.php';
require_once __DIR__ . '/Where.php';


function _Tsukiyo_Helper_and(){
    return new Tsukiyo_WhereTree('and');
}
function _Tsukiyo_Helper_or(){
    return new Tsukiyo_WhereTree('or');
}

class Tsukiyo_Helper
{
    const _AND = '_Tsukiyo_Helper_and';
    const _OR  = '_Tsukiyo_Helper_or';
    // shortcut: extract(Tsukiyo_Helper::$all);
    public static $and = self::_AND;
    public static $or = self::_OR;
    public static $all = array('and' => self::_AND,
                               'or'  => self::_OR);

    public static function like($where, $left, $right, $not = false){
        $where = self::prepareLike($where, $left, $right);
        if ($not)
            $op = 'not like';
        else
            $op = 'like';
        return self::where($op, $where);
    }
    public static function where($op, $where){
        $ret = null;
        foreach ($where as $k => $v){
            $w = new Tsukiyo_WhereNode($op, $k, $v);
            if ($ret)
                $ret = $ret->add($w);
            else
                $ret = $w;
        }
        return $ret;
    }
    public static function noParamWhere($op, $where){
        $ret = null;
        $where = (array)$where;
        foreach ($where as $k){
            $w = new Tsukiyo_WhereNode($op, $k, null, true);
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
