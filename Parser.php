<?php
/**
 * Database setting file parser
 *
 * @package   Tsukiyo
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 * @copyright Copyright (c) 2012 Satoshi Nishimura
 */

/**
 * Database setting file parser
 *
 * @package Tsukiyo
 * @author  Satoshi Nishimura <nishim314@gmail.com>
 * @copyright Copyright (c) 2012 Satoshi Nishimura
 */
class Tsukiyo_Parser
{
    const FKEY_SECTION = '__forignKeys__';
    public static function generate($db, $file){
        $tables = $db->getMetaTables();
        $data = '';
        foreach ($tables as $table){
            $metaPkeys = $db->getMetaPrimaryKeys($table);
            $pkeys = '';
            foreach ($metaPkeys as $pkey => $seq){
                $pkeys .= "$pkey:$seq,";
            }
            $pkeys = rtrim($pkeys, ',');
            $data .= "[$table:$pkeys]\n";

            $metaCols = $db->getMetaColumns($table);
            foreach ($metaCols as $col => $typ){
                $data .= "$col\t = $typ\n";
            }
            $data .= "\n";
        }
        $data .= "[" . self::FKEY_SECTION . "]\n";
        $fkeys = $db->getMetaForignKeys();
        foreach ($fkeys as $from => $to){
            $data .= "$from\t= $to\n";
        }
        file_put_contents($file, $data);
    }
}
