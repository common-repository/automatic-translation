<?php

namespace Translator\Vendor\Translator\Script\Core;

defined('TRANSLATOR_SCRIPT_TRANSLATION') or die();

class Request
{
    /**
     * @var null|Request
     */
    private static $_instance = null;

    protected $href;
    protected $protocol;
    protected $hostname;
    protected $pathname;
    protected $base_directory;
    protected $query;
    protected $language;
    protected $trailing_slashes;


    private function __construct()
    {
        $this->parseBaseDirectory();

        $this->language = isset($_GET['translator_language'])?$_GET['translator_language']:'';

        $this->protocol = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' || !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http";
        $this->hostname = $_SERVER['HTTP_HOST'];
        $href = $this->protocol . '://' . $this->hostname . substr($_SERVER['REQUEST_URI'], strlen($this->base_directory . $this->language) + 1);
        $this->pathname = parse_url($href, PHP_URL_PATH);
        $this->pathname = $this->pathname === null ? '' : urldecode($this->pathname);
        $this->query = parse_url($href, PHP_URL_QUERY);
        $this->query = $this->query === null ? '' : $this->query;
        preg_match('/.*?(\/*)$/', $this->pathname, $matches);
        $this->trailing_slashes =  $matches[1];

        Debug::log('Requested url: ' . $this->getRequestedUrl());
    }

    protected function parseBaseDirectory() {
        if (defined('JPATH_ROOT') && method_exists('JURI', 'getPath')) {
            // We are in a Joomla installation
            $this->base_directory = \JUri::getInstance()->root(true);
        } elseif (defined('ABSPATH') && function_exists('site_url')) {
            // We are in a WordPress installation
            $this->base_directory = site_url( '', 'relative');
        } else {
            $base_dir = rtrim(str_replace('\\', '/', Configuration::getInstance()->get('base_dir')), '/');
            if (empty($_SERVER['CONTEXT_DOCUMENT_ROOT']) || (isset($_SERVER['SCRIPT_FILENAME']) && strpos($_SERVER['SCRIPT_FILENAME'], $_SERVER['CONTEXT_DOCUMENT_ROOT']) !== 0)) {
                $document_root = $_SERVER['DOCUMENT_ROOT'];
                $document_root = rtrim(str_replace('\\', '/', $document_root), '/');
                $base_directory = substr($base_dir, strlen($document_root));
            } else {
                $document_root = $_SERVER['CONTEXT_DOCUMENT_ROOT'];
                $document_root = rtrim(str_replace('\\', '/', $document_root), '/');
                $base_directory = substr($base_dir, strlen($document_root));
                $base_directory = trim($base_directory, '/') . $_SERVER['CONTEXT_PREFIX'];
            }

            if ($base_directory) {
                $this->base_directory = '/' . trim($base_directory, '/');
            } else {
                $this->base_directory = '';
            }
        }
    }

    /**
     * Retrieve singleton instance
     *
     * @return Request|null
     */
    public static function getInstance() {

        if(is_null(self::$_instance)) {
            self::$_instance = new Request();
        }

        return self::$_instance;
    }

    public function getProtocol()
    {
        return $this->protocol;
    }

    public function getHostname()
    {
        return $this->hostname;
    }

    public function getPathname($with_trailing_slashes = true)
    {
        if ($with_trailing_slashes === false) {
            return rtrim($this->pathname, '/');
        }
        return $this->pathname;
    }

    public function getQuery($with_mark = false)
    {
        return (($with_mark && $this->query!=='')?'?':'') . $this->query;
    }

    public function getBaseDir()
    {
        return $this->base_directory;
    }

    public function getTrailingSlashes()
    {
        return $this->trailing_slashes;
    }

    public function getLanguage()
    {
        return $this->language;
    }

    public function getBaseUrl()
    {
        return $this->getProtocol() . '://' . $this->getHostname() . $this->getBaseDir();
    }

    public function getRequestedUrl()
    {
        return $this->getProtocol() . '://' . $this->getHostname() . $this->getBaseDir() . '/' . $this->getLanguage() . $this->getPathname(false) . $this->getTrailingSlashes() . $this->getQuery(true);
    }

    /**
     * Retrieve language normalized url
     */
    public function getLanguageNormalizedURL() {
        $url = $this->getPathname(true);
        // Detect nested language request and redirect to source
        $options = translatorGetOptions();
        if(isset($options["languages_enabled"])){
            $paths = explode('/', $url);
            $paths_count = count($paths);
            foreach($paths as $key=>$value) {
                if (empty($value) || in_array(strtolower($value), $options["languages_enabled"])) {
                    unset($paths[$key]);
                } else {
                    break;
                }
            }
            if (count($paths)!=$paths_count) {
                $url = rtrim((count($paths)?implode('/', $paths):''), '/');
            }
        }
        return ['url'=> '/' . $this->getLanguage() . $this->getPathname(false),'normurl'=>'/' . $this->getLanguage() . '/' . $url];
    }

    /**
     * Retrieve current multilingual non translated url
     *
     * @return string
     */
    public function getNonTranslatedUrl()
    {
        // Find the translated url in database
        $translated_url = '/' . $this->getLanguage() . $this->getPathname(false);
        $non_translated_url = Database::getInstance()->getSourceUrl($translated_url);

        if($non_translated_url == ""){

            $translated_url = '/' . $this->getLanguage() . $this->getPathname(false)."/";
            $non_translated_url = Database::getInstance()->getSourceUrl($translated_url);

        }
        if($non_translated_url == ""){

            $path = rtrim($this->getPathname(false),"/");

            $translated_url = '/' . $this->getLanguage() .  $path;
            $non_translated_url = Database::getInstance()->getSourceUrl($translated_url);

        }

       
        if($non_translated_url == ""){

            $options = translatorGetOptions();

            if(isset($options["languages_enabled"])){
                foreach ($options["languages_enabled"] as $key => $languages_enabled) {

                   

                    $translated_url1 = '/' . $languages_enabled . $this->getPathname(false); 
                    $non_translated_url = Database::getInstance()->getSourceUrlAllLang($translated_url1);

                    if($non_translated_url == ""){

                        $translated_url1 = '/' . $languages_enabled . $this->getPathname(false)."/";
                        $non_translated_url = Database::getInstance()->getSourceUrl($translated_url1);

                    }
                    if($non_translated_url == ""){

                        $path = rtrim($this->getPathname(false),"/");

                        $translated_url1 = '/' . $languages_enabled  .  $path;
                        $non_translated_url = Database::getInstance()->getSourceUrl($translated_url1);

                    }
                    if($non_translated_url != ""){
                    
                       break; 
                    }
                    
                }
            } 

        }


        Debug::log('Search translated url in database ' . $translated_url . ', ' . ($non_translated_url===false?'nothing found':'found: '.$non_translated_url), 2);

        if ($non_translated_url === false) {
            // Not found in database, fall back to the current url
            $non_translated_url = $this->getPathname(false);
        }

        return $this->getProtocol() . '://' . $this->getHostname() . $this->getBaseDir() . rtrim($non_translated_url, '/') . $this->getTrailingSlashes() . $this->getQuery(true);
    }

    public function saveData($upload_dir,$file_name,$file_url){

        $data = array();

        if($file_url != "" && $file_name != ""){


            $path = $upload_dir["basedir"]."/".$file_name;
            $url = $upload_dir["baseurl"]."/".$file_name;

            file_put_contents($path, file_get_contents($file_url));

            if(file_exists($path)){
                $data["server_doc_path"] = str_replace(home_url(), "", $url);
            }
        }
        return $data;

    }

}
