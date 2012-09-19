<?php
/**
 * Main Class of Tsukiyo Framework
 *
 * PHP versions 5
 *
 * @package Tsukiyo
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 * @copyright 2012 Satoshi Nishimura
 */

require_once(dirname(__FILE__) . '/Orm.php');
require_once(dirname(__FILE__) . '/Vo.php');
require_once(dirname(__FILE__) . '/Driver/Factory.php');

/**
 * Main Class of Tsukiyo Framework
 *
 * @package Tsukiyo
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 */
class Tsukiyo_Db
{
    protected $config = array('dsn' => '',
                              'autoConfig' => true,
                              'configFile' => 'cache/tables.ini',
                              'voPrefix' => 'Tsukiyo_Vo_');

    /**
     * Set dsn
     */
    public function setDsn($dsn)
    {
        $this->config['dsn'] = $dsn;
        return $this;
    }
    /**
     * Set auto generate config file
     */
    public function setAutoConfig($auto)
    {
        $this->config['autoConfig'] = $auto;
        return $this;
    }
    /**
     * Set config file path
     */
    public function setConfigFile($path)
    {
        $this->config['configFile'] = $path;
        return $this;
    }
    public function setVoPrefix($prefix){
        $this->config['voPrefix'] = $prefix;
        return $this;
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
                               $this->config['voPrefix'],
                               $this->config['autoConfig']);
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
    public function delete($vo, $ids = null){
        if (is_string($vo) && $ids){
            $orm = $this->create($vo);
            $arg = $ids;
        }else if ($vo instanceof Tsukiyo_Vo){
            $orm = $this->create($this->voToDbName($vo));
            $arg = $vo;
        }else{
            throw new Tsukiyo_Exception('Unknown type of argument1');
        }
        return $orm->delete($arg);
    }
    public function begin(){
        return $this->getDriver()->begin();
    }
    public function commit(){
        return $this->getDriver()->commit();
    }
    public function abort(){
        return $this->getDriver()->abort();
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
