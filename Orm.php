<?php
/**
 * Simple O/R Mapping Library
 *
 * @package   Tsukiyo
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 * @copyright Copyright (c) 2012 Satoshi Nishimura
 */

require_once(dirname(__FILE__) . '/Parser.php');
require_once(dirname(__FILE__) . '/SqlBuilder.php');
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
    private $where = array();
    private $joins = array();

    // result
    private $vo;

    public function __construct($driver, $config, $name)
    {
        $this->driver = $driver;
        $this->voName = $name;
        $this->dbName = Tsukiyo_Util::toDbName($name);
        $this->voPrefix = $config['voPrefix'];
        

        $this->initTableConfig($config['configFile']);

        if (!isset(self::$tables[$this->dbName])){
            trigger_error("Table $tabneName is not exists.", E_USER_WARNING);
            return;
        }
        $this->config = self::$tables[$this->dbName];
        $this->vo = $this->emptyVo();
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


    public function join($name){
        $this->vo->$name = $this->emptyVo($name);
        $this->joins[] = $name;
        return $this;
    }

    public function result(){
        $sql = $this->getSql();
        $this->builderResult($this->vo, $sql[0], $sql[1]);
        if ($this->joins){
            // TODO: SQL一回発行
        }
        return $this->vo;
    }

    /** ============================ */
    public function builderResult($vo, $sql, $params){
        $stmt = $this->driver->query($sql, $params);
        $this->bindColumns($vo, $stmt);
        $ret = $stmt->fetch(PDO::FETCH_BOUND);
        $stmt = null;
        if (!$ret){
            return null;
        }
    }
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
        foreach ($this->config['cols'] as $k => $v){
            $prop = Tsukiyo_Util::toVoName($k);
            $stmt->bindColumn($k, $vo->$prop, $v);
        }
    }

    private function getSql($count = false){
        if ($count)
            $select = ' count(*) ';
        else
            $select = ' * ';
        $where = $this->getWhere();
        $sql = "select $select from $this->dbName ";
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
        foreach ($this->where['eq'] as $k => $v){
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
