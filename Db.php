<?php
/**
 * Main Class of Tsukiyo Framework
 *
 * PHP versions 5
 *
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 * @copyright 2012 Satoshi Nishimura
 */

require_once(dirname(__FILE__) . '/Orm.php');
require_once(dirname(__FILE__) . '/Driver/Factory.php');

/**
 * Main Class of Tsukiyo Framework
 *
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 */
class Tsukiyo_Db
{
    protected $config = array('dsn' => '',
                              'autoConfig' => true,
                              'configFile' => 'cache/tables.ini',
                              'voPrefix' => 'Vo_');

    /**
     * Set dsn
     */
    public function setDsn($dsn)
    {
        $this->config['dsn'] = $dsn;
    }
    /**
     * Set auto generate config file
     */
    public function setAutoConfig($auto)
    {
        $this->config['autoConfig'] = $auto;
    }
    /**
     * Set config file path
     */
    public function setConfigFile($path)
    {
        $this->config['configFile'] = $path;
    }
    public function setVoPrefix($prefix){
        $this->config['voPrefix'] = $prefix;
    }

    public function generateVo($name){
        return $this->create($name)->emptyVo();
    }

    public function from($name){
        return $this->create($name);
    }

    public function autoload($className){
        if (preg_match('/^' . $this->config['voPrefix'] . '/', $className)){
            $name = str_replace($this->config['voPrefix'], '', $className);
            $this->generateVo($name);
        }
    }

    public function create($name){
        $driver = $this->getDriver();
        return new Tsukiyo_Orm($driver, $this->config['configFile'], $name,
                               $this->config['voPrefix']);
    }

    private function voToDbName($vo){
        return str_replace($this->config['voPrefix'], '', get_class($vo));
    }
    public function save($vo){
        return $this->create($this->voToDbName($vo))->save($vo);
    }
    public function insert($vo){
        return $this->create($this->voToDbName($vo))->insert($vo);
    }
    public function update($vo){
        return $this->create($this->voToDbName($vo))->update($vo);
    }
    public function delete($vo){
        return $this->create($this->voToDbName($vo))->delete($vo);
    }

    public function getDriver(){
        try {
            $db = Tsukiyo_Driver_Factory::factory($this->config['dsn']);
            return $db;
        }catch (PDOException $e){
            // PDO error
            trigger_error($e->getMessage(), E_USER_ERROR);
        }catch (Exception $e){
            // framework error
            trigger_error($e->getMessage(), E_USER_ERROR);
        }
    }
}
