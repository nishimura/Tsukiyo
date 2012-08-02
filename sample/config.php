<?php

require_once '../Db.php';

/**
 * Proxy of Tsukiyo_Factory.
 *
 * @package   Tsukiyo_Sample
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 */
class Db extends Tsukiyo_Db
{
    const VO_PREFIX = 'Vo_';
    public function __construct(){
        $this->setDsn('pgsql:host=localhost dbname=tsukiyo user=tsukiyo password=tsukiyo')
            ->setConfigFile('auto-generated.ini')
            ->setAutoConfig(true)
            ->setVoPrefix(self::VO_PREFIX);
    }
}
$db = new Db();
spl_autoload_register(array($db, 'autoload'));
