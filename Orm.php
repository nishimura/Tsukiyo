<?php
/**
 * Simple O/R Mapping Library
 *
 * @package   Tsukiyo
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 * @copyright Copyright (c) 2012 Satoshi Nishimura
 */

require_once(dirname(__FILE__) . '/Parser.php');
require_once(dirname(__FILE__) . '/Util.php');
require_once(dirname(__FILE__) . '/Exception.php');

/**
 * Simple O/R Mapping Library
 *
 * @package Tsukiyo
 * @author  Satoshi Nishimura <nishim314@gmail.com>
 * @copyright Copyright (c) 2012 Satoshi Nishimura
 */
class Tsukiyo_Orm
{
    // Config
    private $driver;
    private $voPrefix;
    private $dbName;
    private $voName;
    private $config;
    private static $tables;
    private static $fkeys;

    // Sql
    private $where = array('eq' => array());
    private $orders = array();

    private $joins = array();
    private $bindNames = array();
    private $bindData = array();
    private $voDatum;

    // resource
    private $stmt;

    // result
    private $vo;

    public function __construct($driver, $file, $name, $prefix)
    {
        $this->driver = $driver;
        $this->voName = $name;
        $this->dbName = Tsukiyo_Util::toDbName($name);
        $this->voPrefix = $prefix;

        $this->voDatum = new Tsukiyo_Orm_VoDatum();

        $this->initTableConfig($file);

        if (!isset(self::$tables[$this->dbName])){
            trigger_error("Table $tabneName is not exists.", E_USER_WARNING);
            return;
        }
        $this->config = self::$tables[$this->dbName];
        $this->vo = $this->emptyVo();
        $this->setBindNames($this->vo);
    }

    public function emptyVo($name = null){
        if ($name === null)
            $name = $this->voName;

        $className = $this->voPrefix . $name;

        if (!class_exists($className, false)){
            require_once dirname(__FILE__) . '/Vo.php';
            eval("class $className implements Tsukiyo_Vo{}");
        }
        $vo = new $className();

        $dbName = Tsukiyo_Util::toDbName($name);
        if (!isset(self::$tables[$dbName])){
            trigger_error("Table $tabneName is not exists.", E_USER_WARNING);
            return;
        }
        foreach (self::$tables[$dbName]['cols'] as $col => $typ){
            $propName = Tsukiyo_Util::toVoName($col);
            $vo->$propName = null;
        }

        return $vo;
    }

    public function eq($where){
        foreach ($where as $k => $v){
            $dbProp = Tsukiyo_Util::toDbName($k);
            $this->where['eq'][$dbProp] = $v;
        }
        return $this;
    }
    public function order($order){
        $order = (array)$order;
        foreach ($order as $k => $v){
            if (is_numeric($k)){
                $this->orders[] = Tsukiyo_Util::toDbName($v);
            }else{
                $colName = Tsukiyo_Util::toDbName($k);
                $this->orders[] = "$colName $v";
            }
        }
        return $this;
    }


    public function join($name){
        if (!$this->internalJoin($this->vo, $name))
            throw new Tsukiyo_Exception("Unknown join table $name.");
        $this->joins[] = $name;
        return $this;
    }

    public function result(){
        $sql = $this->getSql();

        $this->stmt = $this->driver->query($sql[0], $sql[1]);
        $this->bindColumns($this->vo, $this->stmt);
        $ret = $this->stmt->fetch(PDO::FETCH_BOUND);
        if (!$ret){
            return null;
        }

        return $this->vo;
    }

    /** ============================ */
    public function builderCount($sql, $params){
        $stmt = $this->driver->query($sql, $params);
        $ret = $stmt->fetch(PDO::FETCH_NUM);
        $stmt = null;
        if (!$ret || !isset($ret[0])){
            return null;
        }
        return $ret[0];
    }
    public function query($sql, $params){
        return $this->driver->query($sql, $params);
    }

    public function bindColumns($vo, $stmt){
        $this->voDatum->bind($stmt);
    }

    /** ============ Iterator ===================*/
    public function iterator(){
        $sql = $this->getSql();
        $this->stmt = $this->query($sql[0], $sql[1]);
        $this->bindColumns($this->vo, $this->stmt);
        $ret = new Tsukiyo_Iterator($this, $this->vo);
        $this->setupIteratorRelations($ret);
        $ret->setRoot();
        return $ret;
    }
    public function count(){
        $sql = $this->getSql(true);
        return $this->builderCount($sql[0], $sql[1]);
    }

    public function getStmt(){
        return $this->stmt;
    }
    public function removeStmt(){
        $this->stmt = null;
        return $this;
    }
    /** ============ private method =============*/
    private function getSql($count = false){
        if ($count){
            $select = 'count(*)';
        }else{
            $select = $this->voDatum->getSelect();
        }
        $where = $this->getWhere();
        $sql = "select $select from $this->dbName ";
        $left = array($this->dbName);
        foreach ($this->joins as $join){
            $joinTable = Tsukiyo_Util::toDbName($join);
            $using = $this->getJoinUsing($left, $joinTable);
            $sql .= " join $joinTable using ($using) ";
            $left[] = $joinTable;
        }
        $sql .= $where[0];
        $sql .= $this->getOrder();

        return array($sql, $where[1]);
    }
    private function getWhere(){
        $lines = array();
        $params = array();
        foreach ($this->where['eq'] as $k => $v){
            $lines[] = " $k = ? ";
            $params[] = $v;
        }
        if (count($lines) === 0)
            return array('', $params);

        $ret = implode(' and ', $lines);
        return array(' where ' . $ret, $params);
    }
    private function getOrder(){
        if (count($this->orders) === 0)
            return '';
        return ' order by ' . implode(', ', $this->orders);
    }

    private function internalJoin($left, $name){
        if ($left instanceof Tsukiyo_Vo)
            $vo = $left;
        else if ($left instanceof Tsukiyo_Iterator)
            $vo = $left->getVo();
        else
            throw new Tsukiyo_Exception('BUG', E_USER_ERROR);

        foreach (self::$fkeys as $k => $v){
            list($fromTable, $fromCol) = explode('.', $k);
            list($toTable, $toCol) = explode('.', $v);

            $right = Tsukiyo_Util::toDbName($name);
            $left = $this->voToDbName($vo);
            if ($left === $fromTable && $right === $toTable){
                $vo->$name = $this->emptyVo($name);
                $this->setBindNames($vo->$name);
                return true;
            }else if ($right === $fromTable && $left === $toTable){
                $newVo = $this->emptyVo($name);
                $vo->$name = new Tsukiyo_Iterator($this, $newVo);
                $this->setBindNames($newVo);
                return true;
            }
        }
        foreach ($vo as $k => $v){
            if (!($v instanceof Tsukiyo_Vo) && !($v instanceof Tsukiyo_Iterator))
                continue;
            $ret = $this->internalJoin($v, $name);
            if ($ret)
                return true;
        }
    }
    private function setupIteratorRelations($parent, $parentIterator = null){
        if ($parent instanceof Tsukiyo_Iterator){
            $parentIterator = $parent;
            $parentVo = $parent->getVo();
        }else if ($parent instanceof Tsukiyo_Vo){
            $parentVo = $parent;
        }else{
            throw new Tsukiyo_Exception('BUG: Unknown object');
        }
        $hit = false;
        foreach ($parentVo as $k => $v){
            if ($v instanceof Tsukiyo_Iterator){
                if ($hit)
                    throw new Tsukiyo_Exception('Unsupported multiple iterator in vo');
                $hit = true;
                $parentIterator->setChildIterator($v);
                $pkeys = $this->getPkeyColumns($this->voToDbName($parentVo));
                $v->setParent($parentVo, $pkeys);
            }
            if ($v instanceof Tsukiyo_Iterator ||
                $v instanceof Tsukiyo_Vo){
                $ret = $this->setupIteratorRelations($v, $parentIterator);
                if ($ret)
                    return true;
            }
        }
    }
    private function getJoinUsing($left, $right){
        if (is_array($left)){
            foreach ($left as $l){
                $ret = $this->getJoinUsing($l, $right);
                if ($ret)
                    return $ret;
            }
            throw new Tsukiyo_Exception("Unknown join column by $left and $right.");
        }
        foreach (self::$fkeys as $k => $v){
            $from = explode('.', $k);
            $to = explode('.', $v);

            if ($left === $from[0] && $right === $to[0] ||
                $right === $from[0] && $left === $to[0])
                return $from[1];
        }
    }

    private function setBindNames($vo){
        $dbName = $this->voToDbName($vo);

        $voData = new Tsukiyo_Orm_VoData($dbName, $vo);
        $cols = self::$tables[$dbName]['cols'];
        foreach ($cols as $k => $typ){
            $voData->addBindData($k, $typ);
        }
        $this->voDatum->addVoData($voData);
    }

    private function voToDbName(Tsukiyo_Vo $vo){
        $voName = str_replace($this->voPrefix, '', get_class($vo));
        return Tsukiyo_Util::toDbName($voName);
    }

    private function getPkeyColumns($dbName){
        $pkeys = self::$tables[$dbName]['pkeys'];
        $ret = array();
        foreach ($pkeys as $pkey){
            $ret[] = Tsukiyo_Util::toVoName($pkey);
        }
        return $ret;
    }

    private function initTableConfig($configFile){
        if (self::$tables)
            return;

        Tsukiyo_Parser::generate($this->driver, $configFile);

        $data = parse_ini_file($configFile, true);
        $tables = array();
        foreach ($data as $k => $v){
            if ($k === Tsukiyo_Parser::FKEY_SECTION)
                continue;

            list($table, $pkey) = explode(':', $k);
            $pkeys = explode(',', $pkey);
            $tables[$table] = array('pkeys' => $pkeys,
                                    'cols' => $v);
        }
        self::$tables = $tables;
        self::$fkeys = $data[Tsukiyo_Parser::FKEY_SECTION];
    }
}

class Tsukiyo_Orm_VoDatum
{
    private $voData = array();
    public function __construct(){
    }
    public function addVoData(Tsukiyo_Orm_VoData $d){
        $this->voData[] = $d;
    }
    public function getSelect(){
        $cols = array();
        foreach ($this->voData as $v){
            $cols = array_merge($cols, $v->getSelectCols());
        }
        return implode(',', $cols);
    }
    public function bind($stmt){
        foreach ($this->voData as $v)
            $v->bind($stmt);
    }
}
class Tsukiyo_Orm_VoData
{
    public $dbName;
    public $bindData = array();
    private $vo;
    public function __construct($dbName, $vo){
        $this->dbName = $dbName;
        $this->vo = $vo;
    }
    public function addBindData($colName, $typ){
        $this->bindData[] =
            new Tsukiyo_Orm_BindData($this->dbName, $colName, $typ, $this->vo);
    }
    public function bind($stmt){
        foreach ($this->bindData as $b){
            $b->bind($stmt);
        }
    }
    public function getSelectCols(){
        $cols = array();
        foreach ($this->bindData as $b){
            $cols[] = $b->getFullName() . ' as ' . $b->getAlias();
        }
        return $cols;
    }
}
class Tsukiyo_Orm_BindData
{
    private $dbName;
    private $colName;
    private $typ;
    private $vo;
    public function __construct($dbName, $colName, $typ, $vo){
        $this->dbName = $dbName;
        $this->colName = $colName;
        $this->typ = $typ;
        $this->vo = $vo;
    }
    public function getFullName(){
        return $this->dbName . '.' . $this->colName;
    }
    public function getAlias(){
        return '_' . $this->dbName . '_' . $this->colName;
    }
    public function getType(){
        return $this->typ;
    }
    public function getVoProperty(){
        return $this->voProp;
    }
    public function bind($stmt){
        $propName = Tsukiyo_Util::toVoName($this->colName);
        $stmt->bindColumn($this->getAlias(), $this->vo->$propName, $this->typ);
    }
}
