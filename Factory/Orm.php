<?php

class Tsukiyo_Factory_Orm
{
    private $config = array();

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function create($tableName)
    {
        if (!$this->config){
            trigger_error('Not configured', E_USER_WARNING);
            return;
        }

        try {
            require_once dirname(dirname(__FILE__)) . '/Driver/Factory.php';
            $db = Tsukiyo_Driver_Factory::factory($this->config['dsn']);
        }catch (PDOException $e){
            // PDO error
            trigger_error($e->getMessage(), E_USER_ERROR);
        }catch (Exception $e){
            // framework error
            trigger_error($e->getMessage(), E_USER_ERROR);
        }
        require_once dirname(dirname(__FILE__)) . '/Orm.php';
        $orm = new Tsukiyo_Orm($db, $tableName);
        $orm->setAutoCreateConfig($this->config['autoConfig']);
        $orm->setConfigFilePath($this->config['configFile']);
        $orm->setVoPrefix($this->config['voPrefix']);
        if (!$orm->existsTable()){
            require_once dirname(dirname(__FILE__)) . '/Inflector.php';
            if($orm->existsTable(Tsukiyo_Inflector::singularize($tableName))){
                $orm->setTableName(Tsukiyo_Inflector::singularize($tableName));
                require_once dirname(dirname(__FILE__)) . '/Iterator/Orm.php';
                $orm = new Tsukiyo_Iterator_Orm($orm);
            }
        }
        return $orm;
    }
}
