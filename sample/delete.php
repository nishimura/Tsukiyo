<?php

require_once 'config.php';
require_once '../Helper.php';

/**
 * @package   Tsukiyo_Sample
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 */
class Delete
{
    private $db;
    public function __construct($db){
        $this->db = $db;
    }
    public function run(){
        $or = Tsukiyo_Helper::$or;
        $ret = $this->db->from('Item')
            ->sub($or()
                  ->eq(array('name' => 'foo2'))
                  ->eq(array('name' => 'foo3')))
            ->delete();
        echo "delete $ret<br>\n";
    }
}

$delete = new Delete($db);
$delete->run();
