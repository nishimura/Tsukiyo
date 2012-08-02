<?php

require_once 'config.php';


/**
 * @package   Tsukiyo_Sample
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 */
class Delete1
{
    private $db;
    public function __construct($db){
        $this->db = $db;
    }
    public function run(){
        $item = $this->db->from('Item')
            ->limit(1)
            ->result();
        if (!$item)
            return;
        $this->db->delete($item);
        echo "delete $item->itemId<br>\n";
    }
}

$delete = new Delete1($db);
$delete->run();
