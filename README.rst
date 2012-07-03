================================
Tsukiyo: PHP O/R Mapping Library
================================

Simple O/R Mapping library with PHP5.


Sample Database
===============
Tables::

  CREATE TABLE item (
    item_id serial primary key,
    name    text not null,
    opt     text
  );
  CREATE TABLE sub_item (
    sub_item_id serial primary key,
    item_id     int references item(item_id),
    name        text not null
  );

Usage
=====
Initialization::

  require_once 'Tsukiyo/Db.php';
  $db = new Tsukiyo_Db();
  $db->setDsn('pgsql:host=localhost dbname=mydb user=myuser password=pass')
      ->setConfigFile('writable/config.ini');
      ->setAutoConfig(true)
      ->setVoPrefix('Vo_');

Selection::

  $item = $db->from('Item')
      ->eq('name' => 'Name 1')
      ->result();
  $itemAndSubItems = $db->from('Item')
      ->join('SubItem')
      ->eq('Item.name' => 'Name 1')
      ->result();
  $items = $db->from('Item')
      ->like('Item.name', 'a')
      ->order('itemId')
      ->iterator();

Update::

  $item->name = 'Item 2';
  $db->save($item);

Insert::

  $subItem = $db->generateVo('SubItem');
  $subItem->itemId = $item->itemId;
  $subItem->name = 'Sub Name 1';
  $db->save($subItem);

Delete::

  $db->delete($subItem);

Pager::

  require_once 'Tsukiyo/Pager.php';
  $pager = new Tsukiyo_Pager($items, 10);
  $pagerHtml = $pager->getHtml();


Notice
======
Vo that returned from iterator have references of variables.
The following code works as expected::

  $iterator = $db->from('Goods')->iterator();
  $sum = 0;
  foreach ($iterator as $goods){
      $sum += $goods->price;
  }
  echo $sum;

But, the following code does not work as expected::

  $iterator = $db->from('Goods')->iterator();
  $arr = array();
  foreach ($iterator as $goods){
      $arr[] = $goods;
  }
  $sum = 0;
  foreach ($arr as $goods){
      $sum += $goods->price;
  }
  echo $sum;

If you save vo to arrays or other variables, you need call setCloneVo method::

  $iterator = $db->from('Goods')->iterator()->setCloneVo(true);
  $arr = array();
  foreach ($iterator as $goods){
      $arr[] = $goods;
  }
  ...

