<?php
/**
 * Class file for inflection.
 *
 * PHP versions 5
 *
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 * @copyright 2012 Satoshi Nishimura
 */

/**
 * Class for inflection.
 * 
 * @author    Satoshi Nishimura <nishim314@gmail.com>
 */
class Tsukiyo_Inflector
{
    private static $singularized = array();

    private static $irregularSingular = array(
        'children' => 'child',
        'men' => 'man',
        'people' => 'person'
                                       );

    private static $uninflectedSingular = array(
        '.*ss', 'information');

    private static $singularRules = array(
        '/(o)es$/i' => '\1',
        '/uses$/' => 'us',
        '/(x|ch|ss|sh)es$/i' => '\1',
        '/([^aeiouy]|qu)ies$/i' => '\1y',
        '/([lr])ves$/i' => '\1f',
        '/s$/i' => '');

    /**
     * Returns singular word from plural.
     * 
     * @param string $word plural word
     * @return string singular word
     */
    static public function singularize($word)
    {
        if (isset(self::$singularized[$word]))
            return self::$singularized[$word];

        $irregulars = join('|', array_keys(self::$irregularSingular));
        $pattern = '/(' . $irregulars . ')$/i';
        if (preg_match($pattern, $word, $matches)){
            self::$singularized[$word]
                = $matches[1]
                . substr($word, 0, 1)
                . substr(self::$irregularSingular[strtolower($matches[1])], 1);
            return self::$singularized[$word];
        }

        $pattern = '/^(' . join('|', self::$uninflectedSingular) . ')$/i';
        if (preg_match($pattern, $word)){
            self::$singularized[$word] = $word;
            return $word;
        }

        foreach (self::$singularRules as $rule => $rep){
            if (preg_match($rule, $word)){
                self::$singularized[$word] = preg_replace($rule, $rep, $word);
                return self::$singularized[$word];
            }
        }
        self::$singularized[$word] = $word;
        return $word;
    }

    /**
     * Returns underscored singular word from CamelCasedPluralWord.
     *
     * @param string $camelCasedPluralWord
     * @return string underscored word
     */
    static public function classify($camelCasedPluralWord)
    {
        $str = preg_replace('/(?<=\\w)([A-Z])/', '_\\1', $camelCasedPluralWord);
        return ucfirst(self::singularize($str));
    }
}
