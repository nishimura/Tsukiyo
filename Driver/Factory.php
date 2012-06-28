<?php
/**
 * Db Driver Factory Class File
 *
 * PHP versions 5
 *
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 * @copyright 2012 Satoshi Nishimura
 */

/**
 * Class of creation database driver class.
 *
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 */
class Tsukiyo_Driver_Factory
{
    static private $dbs = array();
    
    static public function factory($dsn){
        // If same dsn, return same object.
        if (isset(self::$dbs[$dsn]))
            return self::$dbs[$dsn];

        list($driver, $other) = explode(':', $dsn, 2);

        $driverFile = '';
        switch ($driver){
        case 'pgsql':
            $driverFile = 'Pgsql.php';
            $driverName = 'Pgsql';
            break;
        case 'sqlite':
            $driverFile = 'Sqlite.php';
            $driverName = 'Sqlite';
            break;
        }

        if (!$driverFile)
            throw new Exception('Database Driver not found.');

        $driverFilePath = dirname(__FILE__) . '/' . $driverFile;

        if (!file_exists($driverFilePath))
            throw new Exception('Database Driver File not found.');

        $className = preg_replace('/Factory$/', '', __CLASS__) . $driverName;
        require_once $driverFilePath;
        self::$dbs[$dsn] = new $className($dsn);
        return self::$dbs[$dsn];
    }

}
