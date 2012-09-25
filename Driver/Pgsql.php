<?php
/**
 * DB Driver Class File
 *
 * PHP versions 5
 *
 * @package   Tsukiyo
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 * @copyright 2005-2011 Satoshi Nishimura
 */

require_once dirname(dirname(__FILE__)) . '/Driver.php';

/**
 * PostgreSQL Driver Class.
 *
 * @package   Tsukiyo
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 */
class Tsukiyo_Driver_Pgsql extends Tsukiyo_Driver
{
    const META_TABLES_SQL = "
      select tablename from pg_tables
      where schemaname != 'pg_catalog' and
      schemaname != 'information_schema' ";

    const META_COLUMNS_SQL = "
SELECT a.attname, t.typname, a.attlen, a.atttypmod, a.attnotnull, a.atthasdef, a.attnum
FROM pg_class c, pg_attribute a, pg_type t, pg_namespace n
WHERE relkind = 'r' AND c.relname=?
 and c.relnamespace=n.oid and n.nspname='public'
 AND a.attnum > 0
 AND a.atttypid = t.oid AND a.attrelid = c.oid ORDER BY a.attnum";

    const META_KEYS_SQL = "
SELECT column_name,column_default
FROM information_schema.columns 
LEFT OUTER JOIN information_schema.constraint_column_usage
 USING (table_catalog, table_schema, table_name, column_name)
LEFT OUTER JOIN information_schema.table_constraints
 USING (table_name, table_schema, constraint_catalog, constraint_schema, constraint_name)
where table_name = ?
  and constraint_type = 'PRIMARY KEY'
  and table_schema='public'
order by ordinal_position";

    const META_FKEYS_SQL = "
select src.table_name, src.column_name,
  dist.table_name as to_table_name, dist.column_name as to_column_name
from information_schema.key_column_usage as src
join information_schema.referential_constraints using(constraint_name, constraint_schema)
join information_schema.key_column_usage as dist
  on unique_constraint_name = dist.constraint_name
  and src.constraint_schema = dist.constraint_schema
where src.constraint_schema = 'public'
";

    /**
     * Return tables information.
     *
     * @return array
     * @access public
     */
    public function getMetaTables(){
        $stmt = $this->query(self::META_TABLES_SQL);
        if ($stmt === false){
            return array();
        }

        $rows = array();
        foreach ($stmt as $row){
            $rows[] = $row[0];
        }

        return $rows;
    }

    /**
     * Return columns information.
     *
     * @return array
     * @access public
     */
    public function getMetaColumns($tableName){
        $stmt = $this->query(self::META_COLUMNS_SQL, array($tableName));
        if ($stmt === false){
            return array();
        }

        $rows = array();
        foreach ($stmt as $row){
            switch($row[1]){
            case 'float8':
            case 'numeric':
                $type = PDO::PARAM_STR; // Not exists PARAM_FLOAT by current version.
                break;

            case 'int4':
            case 'int8':
                $type = PDO::PARAM_INT;
                break;

            case 'bool':
                $type = PDO::PARAM_BOOL;
                break;

            default:
                $type = PDO::PARAM_STR;
                break;
            }

            $rows[$row[0]] = $type;
        }

        return $rows;
    }

    /**
     * Return primary key information.
     *
     * @return array
     * @access public
     */
    public function getMetaPrimaryKeys($tableName){
        $stmt = $this->query(self::META_KEYS_SQL, $tableName);
        if ($stmt === false){
            return array();
        }

        $rows = array();
        foreach ($stmt as $row){
            $seq = str_replace('nextval(\'', '', $row[1]);
            $seq = str_replace('\'::regclass)', '', $seq);
            $rows[$row[0]] = $seq;
        }

        return $rows;
    }

    /**
     * Return primary key information.
     *
     * @return array
     * @access public
     */
    public function getMetaForignKeys(){
        $stmt = $this->query(self::META_FKEYS_SQL);
        if ($stmt === false){
            return array();
        }

        $keys = array();
        foreach ($stmt as $row){
            $keys[$row[0] . '.' . $row[1]] = $row[2] . '.' . $row[3];
        }

        return $keys;
    }

    /**
     * Lock table.
     *
     * @param string $table
     * @access public
     */
    public function lock($table){
        $this->query("LOCK TABLE $table IN SHARE ROW EXCLUSIVE MODE");
    }

    /**
     * Return current sequence.
     *
     * @param string $table
     */
    public function currval($table, $pkey){
        $stmt = $this->query("SELECT currval('{$table}_{$pkey}_seq')");

        if (!$stmt)
            return false;

        $ret = $stmt->fetch(PDO::FETCH_NUM);
        if (!$ret){
            $stmt = null;
            return false;
        }

        $stmt = null;
        return $ret[0];
    }

    public function lastInsertId($name = null){
        return +$this->lastInsertIdRaw($name);
    }
}
