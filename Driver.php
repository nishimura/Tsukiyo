<?php
/**
 * Database Driver Class File
 *
 * PHP versions 5
 *
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 * @copyright 2012 Satoshi Nishimura
 */

/**
 * Database Driver Class
 *
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 */
abstract class Tsukiyo_Driver
{
    /** @var PDO  */
    private $conn;

    /**
     * Return tables information.
     *
     * @return array
     * @access public
     */
    abstract public function getMetaTables();

    /**
     * Return Columns information.
     *
     * @return array
     * @access public
     */
    abstract public function getMetaColumns($tableName);

    /**
     * Return primary key information.
     *
     * @return array
     * @access public
     */
    abstract public function getMetaPrimaryKeys($tableName);

    /**
     * Return forign key information.
     *
     * @return array
     * @access public
     */
    abstract public function getMetaForignKeys();

    /**
     * Setting DSN.
     *
     * @param string $dsn 
     * @param string $mode error mode for PDO
     * @access public
     */
    public function __construct($dsn, $errMode = null){
        try{
            $this->conn = new PDO($dsn);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }catch (PDOException $e){
            trigger_error($e->getMessage(), E_USER_ERROR);
        }
    }

    /**
     * Close connection.
     *
     * @access public
     */
    public function __destruct(){
        $this->conn = null;
    }

    /**
     * Get a row by primary key.
     *
     * @param string $sql 
     * @param int $key
     * @return string[]
     * @access public
     */
    public function getRowByKey($sql, $key){
        return $this->getRow($sql, $key, true);
    }

    /**
     * Get a row.
     *
     * @param string $sql
     * @param string|array $where
     * @return string[]
     * @access public
     */
    public function getRow($sql, $where = null, $onlyone = false){
        if ($onlyone && $where === null){
            trigger_error('Not set second argument.', E_USER_WARNING);
            return null;
        }

        $stmt = $this->query($sql, $where);
        $count = $stmt->rowCount();

        if ($stmt && $count){
            if ($onlyone && $count !== 1)
                trigger_error("Two or more data exists [$sql].", E_USER_WARNING);
            
            return $stmt->fetchRow();
        }
    }

    /**
     * Query.
     *
     * @param string $sql
     * @param mixed $p place holder
     * @return PDOStatement
     * @access public
     */
    public function query($sql, $p = null){
        if ($p !== null){
            static $oldSql;
            static $stmt;
            if ($oldSql !== $sql || !$stmt){
                // for speedup
                $oldSql = $sql;
                $stmt = $this->conn->prepare($sql);

                if (!$stmt)
                    return false;
            }

            if (!is_array($p)){
                $p = array($p);
            }

            $stmt->execute($p);

        }else{
            $stmt = $this->conn->query($sql);
            if (!$stmt)
                return false;
        }

        if ($stmt->errorCode() != '00000'){
            $errInfo = $stmt->errorInfo();
            trigger_error($sql . '['.$errInfo[0].':'.$errInfo[1].']'.$errInfo[2], E_USER_WARNING);
        }

        return $stmt;
    }

    /**
     * PDO Execute.
     *
     * @param string $sql
     * @param mixed $p
     * @return int
     */
    public function execute($sql, $p = null){
        if ($p === null)
            return $this->conn->exec($sql);

        $stmt = $this->query($sql, $p);
        if (!$stmt)
            return 0;
        
        $code = $stmt->errorCode();
        if ($code !== '00000'){ // Error Code defined by ANSI SQL
            $error = $stmt->errorInfo();

            if (isset($error[2]))
                $msg = $error[2];
            else
                $msg = '';
            
            trigger_error("SQL error [$code]：$msg : arguments : " . var_export($p, true),
                          E_USER_WARNING);
            return 0;
        }

        return $stmt->rowCount();
    }

    /**
     * Begin transaction.
     * 
     * @access public
     */
    public function begin(){
        $this->conn->beginTransaction();
    }

    /**
     * Finish transaction.
     * 
     * @access public
     */
    public function commit(){
        $this->conn->commit();
    }

    /**
     * Abort transaction.
     * 
     * @access public
     */
    public function abort(){
        $this->conn->rollBack();
    }

    /**
     * Lock table.
     *
     * @param string $table
     * @access public
     */
    abstract function lock($table);

    public function quote($str, $type = null){
        return $this->conn->quote($str, $type);
    }

    /**
     * Setting PDO error mode.
     *
     * @param int $mode
     */
    public function setErrorMode($mode){
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, $mode);
    }
}
