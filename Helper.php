<?php

require_once __DIR__ . '/Util.php';
require_once __DIR__ . '/Where.php';

class Tsukiyo_Helper
{
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
        $ret = new Tsukiyo_WhereTree($andOr);
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
        return self::noParamWhere('is null', $where);
    }
    public static function isNotNull($where){
        return self::noParamWhere('is not null', $where);
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
