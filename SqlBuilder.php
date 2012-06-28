<?php

require_once(dirname(__FILE__) . '/Util.php');
require_once(dirname(__FILE__) . '/Exception.php');
require_once(dirname(__FILE__) . '/Iterator.php');

class Tsukiyo_SqlBuilder
{
    private $orm;
    private $tables;
    private $table;
    private $vo;
    private $fkeys;
    private $joins = array();
    private $where = array();
    private $params = array();
    private $forUpdate = false;
    public function __construct($orm, $tables, $fkeys)
    {
        $this->orm = $orm;
        $this->tables = $tables;
        $this->fkeys = $fkeys;
    }
    public function forUpdate(){
        $this->forUpdate = true;
        return $this;
    }
    public function from($table){
        $this->table = $table;
        $this->vo = $this->orm->emptyVo($table);
        return $this;
    }
    public function id($values){
        $values = (array)$values;
        $conf = $this->getTableConfig();
        $count = count($values);
        if ($count !== count($conf['pkeys'])){
            trigger_error('Unmatch primary key count.', E_USER_WARNING);
            return false;
        }
        for ($i = 0; $i < $count; $i++){
            $this->where[$conf['pkeys'][$i]] = $values[$i];
        }
        return $this;
    }

    public function eq($where){
        foreach ($where as $k => $v){
            $dbProp = Tsukiyo_Util::toDbName($k);
            $this->where[$dbProp] = $v;
        }
        return $this;
    }

    public function result(){
        $sql = $this->getSql();
        $this->orm->builderResult($this->vo, $sql[0], $sql[1]);
        if ($this->joins){
            // TODO: SQL一回発行
        }
        return $this->vo;
    }

    public function join($name){
        $this->joins[] = $name;
        return $this;
    }


    /** ============ Iterator ===================*/
    public function iterator(){
        return new Tsukiyo_Iterator($this, $this->vo);
    }
    public function iteratorStmt(){
        $sql = $this->getSql();
        $stmt = $this->orm->query($sql[0], $sql[1]);
        $this->orm->bindColumns($this->table, $stmt, $this->vo);
        return $stmt;
    }
    public function count(){
        $sql = $this->getSql(true);
        return $this->orm->builderCount($sql[0], $sql[1]);
    }

    /** ============ private method =============*/
    private function getSql($count = false){
        $dbTable = Tsukiyo_Util::toDbName($this->table);
        if ($count)
            $select = ' count(*) ';
        else
            $select = ' * ';
        $where = $this->getWhere();
        $sql = "select $select from $dbTable ";
        foreach ($this->joins as $join){
            $joinTable = Tsukiyo_Util::toDbName($join);
            $using = $this->getJoinUsing($dbTable, $joinTable);
            $sql .= " join $joinTable using ($using) ";
        }
        $sql .= $where[0];
        return array($sql, $where[1]);
    }
    private function getWhere(){
        $lines = array();
        $params = array();
        foreach ($this->where as $k => $v){
            $lines[] = " $k = ? ";
            $params[] = $v;
        }
        $ret = implode(' and ', $lines);
        return array(' where ' . $ret, $params);
    }

    private function getJoinUsing($left, $right){
        foreach ($this->fkeys as $k => $v){
            $from = explode('.', $k);
            $to = explode('.', $v);

            if ($left === $from[0] && $right === $to[0] ||
                $right === $from[0] && $left === $to[0])
                return $from[1];
        }
        throw new Tsukiyo_Exception("Unknown join column by $left and $right.");
    }

    private function getTableConfig(){
        $dbTable = Tsukiyo_Util::toDbName($this->table);
        if (!isset($this->tables[$dbTable])){
            throw new Tsukiyo_Exception("Table $this->table is not exists.");
        }
        return $this->tables[$dbTable];
    }
}
