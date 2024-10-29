<?php
namespace Translator\Vendor\Translator\Script\Core;

defined('TRANSLATOR_SCRIPT_TRANSLATION') or die();

class Configuration {
    /**
     * @var null|Configuration
     */
    private static $_instance = null;

    /** Mandatory configuration **/
    private $token = '';

    /** Basic configuration **/
    private $cache_enabled = true;
    private $cache_max_size = 200; // In megabyte
    private $cache_time_check = 600; // In seconds

    /** Advanced configuration **/
    private $cms = '';
    private $server_ip = null;
    private $server_port = null;
    private $debug = false;
    private $data_dir = null;
    private $base_dir = null;
    private $dl_certificates = false;

    /** Development configuration */
    private $port = 443;
    private $host = 'translator.masterhomepage.ch';
    private $update_url = '';

    private function __construct()
    {
    }

    /**
     * Retrieve singleton instance
     *
     * @return Configuration|null
     */
    public static function getInstance() {

        if(is_null(self::$_instance)) {
            self::$_instance = new Configuration();
        }

        return self::$_instance;
    }

    public function load($basePath) {
        if (file_exists($basePath . DIRECTORY_SEPARATOR . 'ConfigurationLocal.php')) {
            // We override the WordPress Configuration with a local configuration
            require_once($basePath . DIRECTORY_SEPARATOR . 'ConfigurationLocal.php');
            $class = \Translator\Script\ConfigurationLocal::class;
        } else {
            require_once($basePath . DIRECTORY_SEPARATOR . 'Configuration.php');
            $class = \Translator\Script\Configuration::class;
        }

        foreach (get_class_vars($class) as $attribute_name => $attribute_value) {
            Configuration::getInstance()->set($attribute_name, $attribute_value);
        }
        foreach (get_class_methods($class) as $hook) {
            if (strpos($hook, 'on') !== 0) {
                continue;
            }
            Hook::add($hook, $class);
        }
    }

    public function get($property) {
        if (property_exists($this, $property)) {
            return $this->$property;
        }
        return null;
    }

    public function set($property, $value) {
        if ($property === '_instance') {
            return $this;
        }
        if (property_exists($this, $property)) {
            $this->$property = $value;
        }

        return $this;
    }
}
