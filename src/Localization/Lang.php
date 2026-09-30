<?php

namespace App\Localization;

class Lang
{
    /** @var array<string, string> */
    protected static $phrases = [];
    /** @var string */
    protected static $language = 'de';

    public static function setLanguage(string $lang): void
    {
        self::$language = $lang;
        $file = __DIR__ . "/../../assets/lang/{$lang}.php";
        if (file_exists($file)) {
            self::$phrases = require $file;
        } else {
            throw new \Exception("Language file for '{$lang}' not found.");
        }
    }

    /**
     * @param list<string|int|float> $values
     */
    public static function get(string $key, array $values = []): string
    {
        if (!isset(self::$phrases[$key])) {
            return "[unknown:{$key}]";
        }

        return vsprintf(self::$phrases[$key], $values);
    }
}
