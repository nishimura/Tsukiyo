<?php

class Tsukiyo_Parser
{
    const FKEY_SECTION = '__forignKeys__';
    public static function generate($db, $file){
        $tables = $db->getMetaTables();
        $data = '';
        foreach ($tables as $table){
            $pkeys = $db->getMetaPrimaryKeys($table);
            $pkeys = implode(',', $pkeys);
            $data .= "[$table:$pkeys]\n";

            $metaCols = $db->getMetaColumns($table);
            $columns = array();
            $types = array();
            foreach ($metaCols as $metaCol){
                list($c, $t) = explode(':', $metaCol);
                $columns[] = $c;
                $types[] = $t;
            }
            foreach ($columns as $i => $col){
                $data .= "$col\t = ${types[$i]}\n";
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
