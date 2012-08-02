<?php

require_once 'config.php';


/**
 * @package   Tsukiyo_Sample
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 */
class Select
{
    private $db;
    public function __construct($db){
        $this->db = $db;
    }
    public function all(){
        $items = $this->db->from('Item')
            ->outerJoin('SubItem')
            ->outerJoin('SubSubItem')
            ->order(array('Item.itemId', 'SubItem.subItemId', 'val'))
            ->iterator();
        foreach ($items as $item){
            echo "$item->createdAt $item->itemId $item->name\n";
            foreach ($item->SubItem as $sub){
                echo "\t$sub->subItemId $sub->name $sub->opt\n";
                foreach ($sub->SubSubItem as $subsub)
                    echo "\t\tS$subsub->subSubItemId $subsub->val\n";
            }
        }
    }
    public function sub(){
        $subs = $this->db->from('SubItem')
            ->join('Item')
            ->outerJoin('SubSubItem')
            ->order(array('SubItem.subItemId', 'val'))
            ->iterator();
        foreach ($subs as $sub){
            echo "$sub->subItemId $sub->name $sub->opt ("
                , $sub->Item->name, ")\n";
            foreach ($sub->SubSubItem as $subsub)
                echo "\t$subsub->subSubItemId $subsub->val\n";
        }
    }
}

$select = new Select($db);
?>
<!DOCTYPE html>
<pre>
<?php
$select->all();
$select->sub();

?>
</pre>
