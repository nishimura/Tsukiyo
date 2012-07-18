<?php
/**
 * Utility to convert name
 *
 * @package   Tsukiyo
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 * @copyright Copyright (c) 2012 Satoshi Nishimura
 */

/**
 * Utility to convert name
 *
 * @package Tsukiyo
 * @author  Satoshi Nishimura <nishim314@gmail.com>
 * @copyright Copyright (c) 2012 Satoshi Nishimura
 */
class Tsukiyo_Util
{
    public static function toDbName($voName){
        $dot = strpos($voName, '.');
        if ($dot !== false){
            return self::toDbName(substr($voName, 0, $dot))
                . '.' . self::toDbName(substr($voName, $dot + 1));
        }
        $length = strlen($voName);
        $name = strtolower($voName[0]);
        for ($i = 1; $i < $length; $i++){
            $ord = ord($voName[$i]);
            if ($ord >= 97 && $ord <= 122){
                // lower case
                $name .= $voName[$i];
            }else if ($ord >= 65 && $ord <= 90){
                // upper case
                $name .= '_' . strtolower($voName[$i]);
            }else{
                // unknown
                $name .= $voName[$i];
            }
        }
        return $name;
    }
    public static function toVoName($dbName, $upperFirst = false){
        $name = str_replace('_', ' ', $dbName);
        $name = str_replace(' ', '', ucwords($name));

        if (!$upperFirst)
            $name[0] = strtolower($name[0]);
        return $name;
    }
}
