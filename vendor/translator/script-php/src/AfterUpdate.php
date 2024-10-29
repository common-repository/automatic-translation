<?php

namespace Translator\Vendor\Translator\Script\Core;

defined('TRANSLATOR_SCRIPT_TRANSLATION') or die();

class AfterUpdate
{
    static function afterUpdateRun($base_folder)
    {
        Debug::log('After update done');
    }
}
