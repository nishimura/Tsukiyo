<?php
/**
 * Simple O/R Mapping Library
 *
 * @package   Tsukiyo
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 * @copyright Copyright (c) 2012 Satoshi Nishimura
 */

require_once __DIR__ . '/Parser.php';
require_once __DIR__ . '/Util.php';
require_once __DIR__ . '/Exception.php';
require_once __DIR__ . '/Helper.php';

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
    private $where = array();
    private $singleWhere = array();
    private $orders = array();
    private $limit;
    private $offset;

    private $joins = array();
    private $bindNames = array();
    private $bindData = array();
    private $voDatum;

    // resource
    private $stmt;
    private $stmtIndex;

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
            trigger_error("Table $name is not exists.", E_USER_WARNING);
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

    /** ============ Selection ===================*/
    public function id($ids){
        $ids = (array)$ids;
        if (count($ids) !== count($this->config['pkeys']))
            throw new Tsukiyo_Exception('Unmatched id count ' . count($ids)
                                        . ':' . count($this->config['pkeys']));
        $pkeys = $this->config['pkeys'];
        foreach ($pkeys as $i => $pkey)
            $this->where['and']['='][$pkey] = $ids[$i];

        return $this;
    }
    public function eq($where){
        return $this->addAnd(Tsukiyo_Helper::eq($where));
    }
    public function ne($where){
        return $this->addAnd(Tsukiyo_Helper::ne($where));
    }
    public function lt($where){
        return $this->addAnd(Tsukiyo_Helper::lt($where));
    }
    public function le($where){
        return $this->setWhere(Tsukiyo_Helper::le($where));
    }
    public function gt($where){
        return $this->setWhere(Tsukiyo_Helper::gt($where));
    }
    public function ge($where){
        return $this->setWhere(Tsukiyo_Helper::ge($where));
    }
    public function like($where){
        return $this->addAnd(Tsukiyo_Helper::like($where));
    }
    public function notLike($where){
        return $this->addAnd(Tsukiyo_Helper::notLike($where));
    }
    public function starts($where){
        return $this->addAnd(Tsukiyo_Helper::starts($where));
    }
    public function notStarts($where){
        return $this->addAnd(Tsukiyo_Helper::notStarts($where));
    }
    public function ends($where){
        return $this->addAnd(Tsukiyo_Helper::ends($where));
    }
    public function notEnds($where){
        return $this->addAnd(Tsukiyo_Helper::notEnds($where));
    }
    public function isNull($where){
        return $this->addAnd(Tsukiyo_Helper::isNull($where));
    }
    public function isNotNull($where){
        return $this->addAnd(Tsukiyo_Helper::isNotNull($where));
    }

    public function addAnd(){
        return $this->addWhere('and', func_get_args());
    }
    public function addOr(){
        return $this->addWhere('or', func_get_args());
    }
    public function addWhere($andOr, $where){
        if (!isset($this->where[$andOr]))
            $this->where[$andOr] = array();

        foreach ($where as $v)
            $this->where[$andOr][] = $v;
        return $this;
    }
    public function where($where){
        $this->where = $where;
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
    public function limit($limit){
        if (!is_numeric($limit) && $limit !== null)
            throw new Tsukiyo_Exception('limit is not numeric');
        $this->limit = $limit;
    }
    public function offset($offset){
        if (!is_numeric($offset) && $offset !== null)
            throw new Tsukiyo_Exception('offset is not numeric');
        $this->offset = $offset;
    }


    public function join($name){
        return $this->internalJoin($name, false);
    }
    public function outerJoin($name){
        return $this->internalJoin($name, true);
    }
    private function internalJoin($name, $outer){
        if (!$this->parseJoin($this->vo, $name, false))
            throw new Tsukiyo_Exception("Unknown join table $name.");
        $this->joins[$name] = $outer;
        return $this;
    }

    public function result(){
        $sql = $this->getSql();

        $this->stmt = $this->driver->query($sql[0], $sql[1]);
        $this->voDatum->bind($this->stmt);
        $ret = $this->stmt->fetch(PDO::FETCH_BOUND);
        if (!$ret){
            return null;
        }

        $this->setupIteratorRelations($this->vo);
        return $this->vo;
    }

    /** ============ Update ===================*/
    public function save($vo){
        $pkeys = $this->config['pkeys'];
        $count = count($pkeys);
        $hit = 0;
        foreach ($pkeys as $i => $pkey){
            $voName = Tsukiyo_Util::toVoName($pkey);
            if (isset($vo->$voName))
                $hit++;
        }

        if ($hit === 0)
            return $this->insert($vo);
        else if ($hit === $count)
            return $this->update($vo);
        else
            throw new Tsukiyo_Exception('Undetermined insert or update.');
    }
    private function setIdsByVo($vo){
        $pkeys = $this->config['pkeys'];
        foreach ($pkeys as $i => $pkey){
            $voName = Tsukiyo_Util::toVoName($pkey);
            $this->where['='][$pkey] = $vo->$voName;
        }
    }
    public function update($vo){
        $this->setIdsByVo($vo);
        $pkeys = $this->config['pkeys'];

        $cols = array();
        $params = array();
        foreach ($this->config['cols'] as $col => $typ){
            if (in_array($col, $pkeys))
                continue;
            $voName = Tsukiyo_Util::toVoName($col);
            $cols[] = "$col = ?";
            $params[] = $vo->$voName;
        }
        $sql = "update $this->dbName set ";
        $sql .= implode(', ', $cols);
        $where = $this->getWhere();
        if ($where[0]){
            $sql .= ' where ' . $where[0];
            foreach ($where[1] as $p)
                $params[] = $p;
        }

        return $this->driver->execute($sql, $params);
    }
    public function insert($vo){
        $cols = array();
        $vals = array();
        $params = array();
        foreach ($this->config['cols'] as $col => $typ){
            $voName = Tsukiyo_Util::toVoName($col);
            if (!isset($vo->$voName))
                continue;
            $cols[] = $col;
            $vals[] = '?';
            $params[] = $vo->$voName;
        }
        $sql = "insert into $this->dbName (";
        $sql .= implode(', ', $cols);
        $sql .= ') values (';
        $sql .= implode(', ', $vals);
        $sql .= ')';


        $ret = $this->driver->execute($sql, $params);
        foreach ($this->config['seqs'] as $pkey => $seq){
            $prop = Tsukiyo_Util::toVoName($pkey);
            if (!isset($vo->$prop))
                $vo->$prop = $this->driver->lastInsertId($seq);
        }
        return $ret;
    }
    public function delete($voOrIds){
        if ($voOrIds instanceof Tsukiyo_Vo){
            $this->setIdsByVo($voOrIds);
        }else{
            $this->id($voOrIds);
        }
        $sql = "delete from $this->dbName ";
        $where = $this->getWhere();
        if ($where[0])
            $sql .= ' where ' . $where[0];

        return $this->driver->execute($sql, $where[1]);
    }

    /** ============= utility methods =============== */
    public function builderCount($sql, $params){
        $stmt = $this->driver->query($sql, $params);
        $ret = $stmt->fetch(PDO::FETCH_NUM);
        if ($stmt)
            $stmt->closeCursor();
        $stmt = null;
        if (!$ret || !isset($ret[0])){
            return null;
        }
        return $ret[0];
    }
    public function query($sql, $params){
        return $this->driver->query($sql, $params);
    }
    public function releaseStmt(){
        if (!$this->stmt)
            return $this;

        $this->stmt->closeCursor();
        $this->stmt = null;
        return $this;
    }

    /** ============ Iterator ===================*/
    public function iterator(){
        $ret = new Tsukiyo_Iterator($this, $this->vo);
        $this->setupIteratorRelations($ret);
        $ret->setRoot();
        return $ret;
    }
    public function count(){
        $sql = $this->getSql(true);
        return $this->builderCount($sql[0], $sql[1]);
    }

    public function getStmtIndex(){
        return $this->stmtIndex;
    }
    public function fetchIfNotMove($stmtIndex){
        if ($this->stmtIndex === false)
            return false;
        if (is_numeric($this->stmtIndex) && $stmtIndex !== $this->stmtIndex)
            return $this->stmtIndex;

        if (!$this->stmt){
            $sql = $this->getSql();
            $this->stmt = $this->query($sql[0], $sql[1]);
            $this->voDatum->bind($this->stmt);
        }
        $ret = $this->stmt->fetch(PDO::FETCH_BOUND);
        if ($ret === false){
            $this->stmtIndex = false;
            return false;
        }
        $this->stmtIndex++;
        return $this->stmtIndex;
    }

    /** ============ common method =============*/
    private function getSql($count = false){
        if ($count){
            $select = 'count(*)';
        }else{
            $select = $this->voDatum->getSelect();
        }
        $sql = "select $select from $this->dbName ";
        $left = array($this->dbName);
        foreach ($this->joins as $join => $outer){
            $joinTable = Tsukiyo_Util::toDbName($join);
            $using = $this->getJoinUsing($left, $joinTable);
            if ($outer)
                $sql .= ' left outer ';
            $sql .= " join $joinTable using ($using) ";
            $left[] = $joinTable;
        }
        $where = $this->getWhere();
        if ($where[0])
            $sql .= ' where ' . $where[0];
        if (!$count)
            $sql .= $this->getOrder();
        if (is_numeric($this->limit))
            $sql .= " limit $this->limit ";
        if (is_numeric($this->offset))
            $sql .= " offset $this->offset ";

        return array($sql, $where[1]);
    }
    private function getWhere(){
        $tops = array();
        $params = array();
        foreach ($this->where as $sub => $block){
            $lines = array();
            foreach ($block as $arr){
                foreach ($arr as $op => $kv){
                    $ops = array();
                    foreach ($kv as $k => $v){
                        if ($v instanceof Tsukiyo_Helper_Single){
                            $ops[] = " $k $op ";
                        }else{
                            $ops[] = " $k $op ? ";
                            $params[] = $v;
                        }
                    }
                    $lines[] = '(' . implode(' and ', $ops) . ')';
                }
            }
            $tops[] = '(' . implode(' ' . $sub . ' ', $lines) . ')';
        }
        $sql = implode(' and ', $tops);
        return array($sql, $params);
    }
    private function getOrder(){
        if (count($this->orders) === 0)
            return '';
        return ' order by ' . implode(', ', $this->orders);
    }

    private function parseJoin($left, $name){
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
            $ret = $this->parseJoin($v, $name);
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
            if ($parentIterator && ($v instanceof Tsukiyo_Iterator)){
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

            list($table, $pkeydata) = explode(':', $k, 2);
            $pkeydata = explode(',', $pkeydata);
            $pkeys = array();
            $seqs = array();
            foreach ($pkeydata as $line){
                if (!$line)
                    continue;
                list($pkey, $seq) = explode(':', $line);
                $pkeys[] = $pkey;
                if ($seq)
                    $seqs[$pkey] = $seq;
            }
            $tables[$table] = array('pkeys' => $pkeys,
                                    'seqs' => $seqs,
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
