<?php
/**
 * Simple O/R Mapping Library
 *
 * @package   Tsukiyo
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 * @copyright Copyright (c) 2012 Satoshi Nishimura
 */

require_once __DIR__ . '/Iterator.php';
require_once __DIR__ . '/Parser.php';
require_once __DIR__ . '/Util.php';
require_once __DIR__ . '/Exception.php';
require_once __DIR__ . '/Helper.php';
require_once __DIR__ . '/Where.php';

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
    private $where;
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

    // settings
    private $toCloneVo;

    public function __construct($driver, $file, $name, $prefix, $createConfig)
    {
        $this->driver = $driver;
        $this->voName = $name;
        $this->dbName = Tsukiyo_Util::toDbName($name);
        $this->voPrefix = $prefix;

        $this->voDatum = new Tsukiyo_Orm_VoDatum();

        $this->initTableConfig($file, $createConfig);

        if (!isset(self::$tables[$this->dbName])){
            trigger_error("Table $name is not exists.", E_USER_WARNING);
            return;
        }
        $this->config = self::$tables[$this->dbName];
        $this->vo = $this->emptyVo();
        $this->setBindNames($this->vo);

        $this->where = new Tsukiyo_WhereTree('and');
    }

    public function emptyVo($name = null){
        if ($name === null)
            $name = $this->voName;

        $className = $this->voPrefix . $name;

        if (!class_exists($className, false)){
            $dbName = Tsukiyo_Util::toDbName($name);
            if (!isset(self::$tables[$dbName])){
                trigger_error("Table $tabneName is not exists.", E_USER_WARNING);
                return;
            }

            $code = "class $className implements Tsukiyo_Vo{\n";
            foreach (self::$tables[$dbName]['cols'] as $col => $typ){
                $propName = Tsukiyo_Util::toVoName($col);
                $code .= "  public \$$propName = null;\n";
            }
            $code .= "}\n";

            require_once dirname(__FILE__) . '/Vo.php';
            eval($code);
        }
        $vo = new $className();

        return $vo;
    }

    /* ============ Selection ===================*/
    public function id($ids){
        $ids = (array)$ids;
        if (count($ids) !== count($this->config['pkeys']))
            throw new Tsukiyo_Exception('Unmatched id count ' . count($ids)
                                        . ':' . count($this->config['pkeys']));
        $pkeys = $this->config['pkeys'];
        foreach ($pkeys as $i => $pkey)
            $this->where->eq(array($this->voName . '.' . Tsukiyo_Util::toVoName($pkey) =>$ids[$i]));

        return $this;
    }
    public function eq($where){
        $this->where->eq($where);
        return $this;
    }
    public function ne($where){
        $this->where->ne($where);
        return $this;
    }
    public function lt($where){
        $this->where->lt($where);
        return $this;
    }
    public function le($where){
        $this->where->le($where);
        return $this;
    }
    public function gt($where){
        $this->where->gt($where);
        return $this;
    }
    public function ge($where){
        $this->where->ge($where);
        return $this;
    }
    public function like($where){
        $this->where->like($where);
        return $this;
    }
    public function notLike($where){
        $this->where->notLike($where);
        return $this;
    }
    public function starts($where){
        $this->where->starts($where);
        return $this;
    }
    public function notStarts($where){
        $this->where->notStarts($where);
        return $this;
    }
    public function ends($where){
        $this->where->ends($where);
        return $this;
    }
    public function notEnds($where){
        $this->where->notEnds($where);
        return $this;
    }
    public function isNull($where){
        $this->where->isNull($where);
        return $this;
    }
    public function isNotNull($where){
        $this->where->isNotNull($where);
        return $this;
    }
    public function getInternalWhere(){
        return $this->where;
    }
    public function sub(Tsukiyo_WhereTree $where){
        $this->where->add($where);
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
    public function limit($limit){
        if (!is_numeric($limit) && $limit !== null)
            throw new Tsukiyo_Exception('limit is not numeric');
        $this->limit = $limit;
        return $this;
    }
    public function offset($offset){
        if (!is_numeric($offset) && $offset !== null)
            throw new Tsukiyo_Exception('offset is not numeric');
        $this->offset = $offset;
        return $this;
    }


    public function join($name, $where = null){
        return $this->internalJoin($name, false, $where);
    }
    public function outerJoin($name, $where = null){
        return $this->internalJoin($name, true, $where);
    }

    private function searchJoinVo($vo, $name){
        $voName = str_replace($this->voPrefix, '', get_class($vo));
        if ($voName === $name)
            return $vo;
        foreach ($vo as $k => $v){
            $target = null;
            if ($v instanceof Tsukiyo_Vo)
                $target = $v;
            else if ($v instanceof Tsukiyo_Iterator)
                $target = $v->getVo();
            if ($target){
                $ret = $this->searchJoinVo($target, $name);
                if ($ret)
                    return $ret;
            }
        }
    }
    private function internalJoin($name, $outer, $where){
        if (strpos($name, '.') !== false){
            list($leftName, $rightName) = explode('.', $name);
            $leftVo = $this->searchJoinVo($this->vo, $leftName);
        }else{
            $rightName = $name;
            $leftName = null; // unknown, search all
            $leftVo = $this->vo;
        }
        if (!$this->parseJoin($leftVo, $rightName, false))
            throw new Tsukiyo_Exception("Unknown join table $name.");

        $sql = '';
        if ($outer)
            $sql .= ' left outer ';
        $joinTable = Tsukiyo_Util::toDbName($rightName);
        $sql .= ' join ' . $joinTable;
        $sql .= ' on (';

        if ($leftName){
            $left = Tsukiyo_Util::toDbName($leftName);
        }else{
            $left = array_keys($this->joins);
            array_unshift($left, $this->dbName);
        }

        $sql .= $this->getJoinOn($left, $joinTable);
        if (is_array($where)){
            $and = Tsukiyo_Helper::$and;
            $where = $and()->eq($where);
        }
        if ($where instanceof Tsukiyo_Where){
            $sql .= ' and ' . $where->getString();
            $params = $where->getParams();
        }else{
            $params = array();
        }

        $sql .= ')';

        $this->joins[$joinTable] = array($sql, $params);
        return $this;
    }
    private function getJoinOn($left, $right){
        if (is_array($left)){
            foreach ($left as $l){
                $ret = $this->getJoinOn($l, $right);
                if ($ret)
                    return $ret;
            }
            throw new Tsukiyo_Exception("Unknown join column by $left and $right.");
        }
        $ret = array();
        foreach (self::$fkeys as $k => $v){
            $from = explode('.', $k);
            $to = explode('.', $v);

            if ($left === $from[0] && $right === $to[0] ||
                $right === $from[0] && $left === $to[0])
                $ret[] = "$k = $v";
        }
        return implode(' and ', $ret);
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
        if ($this->toCloneVo)
            return $this->cloneVo();
        else
            return $this->vo;
    }

    /* ============ Update ===================*/
    public function save($vo){
        $pkeys = $this->config['pkeys'];
        $count = count($pkeys);
        if ($count > 1)
            throw new Tsukiyo_Exception('Unsupported save with multiple primary keys. Please use update or insert method.');

        $hit = 0;
        foreach ($pkeys as $i => $pkey){
            $voName = Tsukiyo_Util::toVoName($pkey);
            if (isset($vo->$voName))
                $hit++;
        }

        // TODO: refactoring and handle multiple pkey
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
            $this->where->eq(array(Tsukiyo_Util::toVoName($pkey)
                                   => $vo->$voName));
        }
    }
    public function update($vo){
        $this->setIdsByVo($vo);
        $pkeys = $this->config['pkeys'];

        $cols = array();
        $params = array();
        $types = array();
        foreach ($this->config['cols'] as $col => $typ){
            if (in_array($col, $pkeys))
                continue;
            $voName = Tsukiyo_Util::toVoName($col);
            $cols[] = "$col = ?";
            $params[] = $vo->$voName;
            $types[] = $typ;
        }
        $sql = "update $this->dbName set ";
        $sql .= implode(', ', $cols);
        $where = $this->where->getString();
        $whereParams = array();
        if ($where){
            $sql .= ' where ' . $where;
            foreach ($this->where->getParams() as $p)
                $whereParams[] = $p;
        }

        $stmt = $this->driver->prepareRaw($sql);
        $index = 1;
        foreach ($types as $i => $typ){
            $stmt->bindValue($index, $params[$i], $typ);
            $index++;
        }
        foreach ($whereParams as $p){
            $stmt->bindValue($index, $p);
            $index++;
        }
        return $stmt->execute();
    }
    public function insert($vo){
        if (property_exists($vo, 'createdAt') &&
            $vo->createdAt === null)
            $vo->createdAt = date('Y-m-d H:i:s');

        $cols = array();
        $vals = array();
        $params = array();
        $types = array();
        foreach ($this->config['cols'] as $col => $typ){
            $voName = Tsukiyo_Util::toVoName($col);
            if (!isset($vo->$voName))
                continue;
            $cols[] = $col;
            $vals[] = '?';
            $params[] = $vo->$voName;
            $types[] = $typ;
        }
        if (count($cols) === 0)
            throw new Tsukiyo_Exception('All properties are null');

        $sql = "insert into $this->dbName (";
        $sql .= implode(', ', $cols);
        $sql .= ') values (';
        $sql .= implode(', ', $vals);
        $sql .= ')';

        $stmt = $this->driver->prepareRaw($sql);
        foreach ($types as $index => $typ){
            $stmt->bindValue($index + 1, $params[$index], $typ);
        }

        $ret = $stmt->execute();
        foreach ($this->config['seqs'] as $pkey => $seq){
            $prop = Tsukiyo_Util::toVoName($pkey);
            if (!isset($vo->$prop))
                $vo->$prop = $this->driver->lastInsertId($seq);
        }
        return $ret;
    }
    public function delete($voOrIds = null){
        if ($voOrIds instanceof Tsukiyo_Vo){
            $this->setIdsByVo($voOrIds);
        }else if ($voOrIds !== null){
            $this->id($voOrIds);
        }
        $sql = "delete from $this->dbName ";
        $where = $this->where->getString();
        if ($where)
            $sql .= ' where ' . $where;

        return $this->driver->execute($sql, $this->where->getParams());
    }

    /* ============= utility methods =============== */
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
    public function setCloneVo($flag){
        $this->toCloneVo = $flag;
        return $this;
    }
    public function cloneVo($vo = null){
        if (!$vo)
            $vo = $this->vo;
        $clone = clone $vo;
        foreach ($vo as $k => $v){
            unset($clone->$k);
            if ($v instanceof Tsukiyo_Vo)
                $v = $this->cloneVo($v);
            $clone->$k = $v;
        }
        return $clone;
    }

    /* ============ Iterator ===================*/
    public function iterator(){
        $ret = new Tsukiyo_Iterator($this, $this->vo);
        $ret->setCloneVo($this->toCloneVo);
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

    /* ============ common method =============*/
    public function getSql($count = false){
        if ($count){
            $select = 'count(*)';
        }else{
            $select = $this->voDatum->getSelect();
        }
        $sql = "select $select from $this->dbName ";
        $params = array();
        foreach ($this->joins as $k => $v){
            list($joinSql, $ps) = $v;
            $sql .= $joinSql;
            foreach ($ps as $p)
                $params[] = $p;
        }

        $where = $this->where->getString();
        if ($where)
            $sql .= ' where ' . $where;
        if (!$count)
            $sql .= $this->getOrder();
        if (is_numeric($this->limit))
            $sql .= " limit $this->limit ";
        if (is_numeric($this->offset))
            $sql .= " offset $this->offset ";

        foreach ($this->where->getParams() as $p)
            $params[] = $p;
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
                $vo->$name->setCloneVo($this->toCloneVo);
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

    private function initTableConfig($configFile, $create){
        if (self::$tables)
            return;

        if ($create || !file_exists($configFile))
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
