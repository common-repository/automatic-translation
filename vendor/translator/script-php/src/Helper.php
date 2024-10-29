<?php
namespace Translator\Vendor\Translator\Script\Core;

defined('TRANSLATOR_SCRIPT_TRANSLATION') or die();

class Helper {

    public static function getIpAddress()
    {
        if (isset($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = (string)trim(current(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])));
            if ($ip) {
                return $ip;
            }
        }

        if (isset( $_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return '';
    }

    public static function getClassStaticVars($classname)
    {
        $class = new \ReflectionClass($classname);
        return $class->getStaticProperties();
    }
}
