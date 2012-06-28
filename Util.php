<?php

class Tsukiyo_Util
{
    public static function toDbName($voName){
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
