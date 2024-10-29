<?php
use Translator\Vendor\Translator\Script\Core\Configuration;
use Translator\Vendor\Translator\Script\Core\Database;
use Translator\Vendor\Translator\Script\Core\Processor;

define('TRANSLATOR_SCRIPT_TRANSLATION', true);
define('TRANSLATOR_SCRIPT_TRANSLATION_VERSION', 'wordpress_plugin/1.8.11');

ini_set('display_errors', false);

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');

Configuration::getInstance()->load(__DIR__);

Configuration::getInstance()->set('base_dir', realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR  . '..' . DIRECTORY_SEPARATOR  . '..') . DIRECTORY_SEPARATOR);

$HTTP_HOST = sanitize_text_field($_SERVER['HTTP_HOST']);

$token = Database::getInstance()->retrieveWordpressOption('translator_api_token', $HTTP_HOST);
$cache_enabled = Database::getInstance()->retrieveWordpressOption('cache_enabled');
$cache_max_size = Database::getInstance()->retrieveWordpressOption('cache_max_size');
$debug = Database::getInstance()->retrieveWordpressOption('debug') ? 5 : false;

if (function_exists('is_plugin_active') && is_plugin_active('woocommerce/woocommerce.php')) {
    $cache_enabled = 0;
}
Configuration::getInstance()->set('token', $token);

Configuration::getInstance()->set('cache_enabled', $cache_enabled);
Configuration::getInstance()->set('cache_max_size', $cache_max_size);
Configuration::getInstance()->set('debug', $debug);

$processor = new Processor();
$translator_language = sanitize_text_field($_GET['translator_language']);
$translator_action = sanitize_text_field($_GET['translator_action']);
$REQUEST_METHOD = sanitize_text_field($_SERVER['REQUEST_METHOD']);

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- View request, no action
if ($translator_language != "" && $translator_language === 'zz-zz' &&  $translator_action != "") {
    switch ($translator_action) {
        case 'clear-cache':
            $processor->clearCache();
            break;
        case 'update-certificates':
            $processor->updateCertificates();
            break;
    }
} elseif ($REQUEST_METHOD === 'POST' && $translator_language != "" && $translator_language === 'zz-zz') {
    $processor->editor();
} else {
    $processor->run();
}
