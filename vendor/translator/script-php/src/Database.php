<?php

namespace Translator\Vendor\Translator\Script\Core;

use Translator\Vendor\Translator\Script\Core\Databases\Mysql;
use Translator\Vendor\Translator\Script\Core\Databases\Sqlite;

defined('TRANSLATOR_SCRIPT_TRANSLATION') or die();

class Database
{
    /**
     * @var null|Database
     */
    private static $_instance = null;

    /**
     * @var null|Mysql
     */
    protected $_database;

    private $_configuration = null;

    private function __construct()
    {
        $cms = 'none';
        if (empty(Configuration::getInstance()->get('cms')) || Configuration::getInstance()->get('cms') === 'auto') {
            $base_dir = Configuration::getInstance()->get('base_dir');
            if (file_exists($base_dir . 'wp-config.php')) {
                $cms = 'wordpress';
            }
        } elseif (strtolower(Configuration::getInstance()->get('cms')) === 'wordpress') {
            $cms = 'wordpress';
        }

        if ($cms === 'wordpress') {
            $this->_configuration = $this->retrieveWordPressConfiguration();
            $this->_database = Mysql::getInstance();
            $connection_result = $this->_database->connect($this->_configuration);
        } else {
            $this->_database = Sqlite::getInstance();
            $connection_result = $this->_database->connect();
        }

        if (!$connection_result) {
            //fixme: redirect to non translated page
        }
    }

    /**
     * Retrieve singleton instance
     *
     * @return Database|null
     */
    public static function getInstance() {

        if(is_null(self::$_instance)) {
            self::$_instance = new Database();
        }

        return self::$_instance;
    }

    /**
     * Retrieve Wordpress database credentials and tries to connect
     *
     * @return bool|\stdClass
     */
    protected function retrieveWordPressConfiguration()
    {
        $config = new \stdClass();

        global $wpdb;
        if (!empty($wpdb) && !empty($wpdb->db_version())) {
            // We have already mysql connected

            $config->db = $wpdb->__get('dbname');
            $config->user = $wpdb->__get('dbuser');
            $config->password = $wpdb->__get('dbpassword');
            $config->host = $wpdb->__get('dbhost');
            $config->dbprefix = $wpdb->base_prefix;
            $config->multisite = is_multisite();
            if (defined('DOMAIN_CURRENT_SITE')) {
                $config->domain_current_site = DOMAIN_CURRENT_SITE;
            }

        } else {
            // Fallback to loading configuration file
            $configuration_file = Configuration::getInstance()->get('base_dir') . DIRECTORY_SEPARATOR . 'wp-config.php';
            if (!file_exists($configuration_file)) {
                return false;
            }

            $config_content = file_get_contents($configuration_file);

            preg_match_all('/define\( *[\'"](.*.)[\'"] *, *(?:[\'"](.*?)[\'"]|([0-9]+)|(true)|(TRUE)) *\)/m', $config_content, $matches, PREG_SET_ORDER, 0);

            foreach ($matches as $config_line) {
                switch ($config_line[1]) {
                    case 'DB_NAME':
                        $config->db = $config_line[2];
                        break;
                    case 'DB_USER':
                        $config->user = $config_line[2];
                        break;
                    case 'DB_PASSWORD':
                        $config->password = $config_line[2];
                        break;
                    case 'DB_HOST':
                        $config->host = $config_line[2];
                        break;
                    case 'MULTISITE':
                        if ((!empty($config_line[3]) && (int)$config_line[3] > 0) || empty($config_line[4]) || empty($config_line[5])) {
                            $config->multisite = true;
                        } else {
                            $config->multisite = false;
                        }
                        break;
                    case 'DOMAIN_CURRENT_SITE':
                        $config->domain_current_site = $config_line[2];
                        break;
                }
            }

            preg_match('/\$table_prefix *= *[\'"](.*?)[\'"]/', $config_content, $matches);
            $config->dbprefix = $matches[1];
        }

        return $config;
    }

    public function getSourceUrl($url) {
        return $this->_database->getSourceUrl($url);
    }
    public function getSourceUrlAllLang($url) {
        return $this->_database->getSourceUrlAllLang($url);
    }

    public function getTranslatedUrl($url) {
        return $this->_database->getTranslatedUrl($url);
    }

    public function saveUrls($urls,$requested_path) {
        return $this->_database->saveUrls($urls,$requested_path);
    }

    public function removeUrls($urls) {
        return $this->_database->removeUrls($urls);
    }

    public function retrieveWordpressOption($option_name, $host = null) {
        if (!empty($this->_configuration->multisite) && $host !== $this->_configuration->domain_current_site) {
            return $this->_database->retrieveWordpressMultisiteOption($option_name, $host);
        } else {
            return $this->_database->retrieveWordpressOption($option_name);
        }
    }

    public function updateLimit($used,$total){
        return $this->_database->updateLimit($used,$total);
    }

    public function checkCache($request_url, $count){
        return $this->_database->checkCache($request_url, $count);
    }
}
