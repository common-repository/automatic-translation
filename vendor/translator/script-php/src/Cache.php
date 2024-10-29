<?php

namespace Translator\Vendor\Translator\Script\Core;

defined('TRANSLATOR_SCRIPT_TRANSLATION') or die();

class Cache
{
    /**
     * @var null|Request
     */
    private static $_instance = null;

    /**
     * Hash of the original content retrieved from untranslated page
     *
     * @var null|string
     */
    private $_hash = null;

    /**
     * @var null|string
     */
    private $_content = null;

    /**
     * @var null|string
     */
    private $_language = null;

    /**
     * @var string The action parameter to use in request
     */
    public $_action = 'clear-cache';

    public $_cache_load = false;

    public $_web_page_char = 0;

    /**
     * Retrieve singleton instance
     *
     * @return Request|null
     */
    public static function getInstance() {

        if(is_null(self::$_instance)) {
            self::$_instance = new Cache();
        }

        return self::$_instance;
    }

    public function getPath() {
        return Configuration::getInstance()->get('data_dir') . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;
    }

    private function getHash($request_url, $language=null) {
        if (!$language) {
            $language = Request::getInstance()->getLanguage();
        }
        $url = '/'.ltrim($request_url, '/' . $language);
        $folder = $this->getCacheFolder($url);
        $params = [];

        sort($params);

        return $folder . DIRECTORY_SEPARATOR . $language . '_' . md5(implode('', $params));
    }

    private function getCacheFolder($request_url) {
        return md5(trim($request_url));
    }

    public function serve() {

        $response = Response::getInstance();

        $content = $response->getContent();
/*
        if (!$content) {
            return false;
        }
*/

        $request_url = Request::getInstance()->getRequestedUrl();
        $request_url = str_replace(home_url(), "", $request_url);
//        $this->_hash = md5(trim($request_url));
        $this->_hash = $this->getHash($request_url);

      //  $this->_hash = md5(trim(Request::getInstance()->getRequestedUrl()));

        /* count characters */
        $stripped = strip_tags($content);
        $decoded_c  = html_entity_decode($stripped);
        $s = str_replace(' ', '', $decoded_c); // remove whitespace to count characters
        $s = str_replace('\n', '', $decoded_c); // remove whitespace to count characters
        $s = str_replace('\t', '', $decoded_c); // remove whitespace to count characters
        $s = trim(preg_replace('/\s\s+/', ' ', str_replace("\n", " ", $s)));
        $this->_web_page_char = strlen($s);

        /* Find subscription status */
        if(isset($_GET['translator_action']) && $_GET['translator_action'] !== null){
            if($_GET['translator_action'] == 'delete-cache'){                
                $this->clear_cache();
            }
        }

        // In case we failed to json_encode (non utf8 chars and no mbstring extension)
        if (!$this->_hash) {
            return false;
        }

        $language = Request::getInstance()->getLanguage();

       /* if (!preg_match('/^[a-z]{2,3}(?:-[a-z]{2})?$/', $language)) {
            // Some characters are not allowed skip
            return false;
        }*/

        $this->_language = $language;

        if (!$this->load()) {
            $req_url1 = Request::getInstance()->getRequestedUrl();
            $req_url1 = str_replace(home_url(), "", $req_url1);
          

            global $wpdb;

            $translator_urls_table= $wpdb->prefix."translator_urls"; 

            $urls_d1 = $wpdb->get_row("select source from `$translator_urls_table` where translation = '$req_url1' AND language = '$language'");
           
            if($urls_d1){

                $tr_url = "/".$language.$urls_d1->source;
//                $this->_hash = md5(trim($tr_url));
                $this->_hash = $this->getHash(trim($tr_url));
                if (!$this->load()) {
                    
                    return false;
                }

            }else{
                return false;
            }
        }

        $this->_cache_load = true;
        $this->_content = $this->overwriteText($this->_content,$this->_language); 

        $req_url = Request::getInstance()->getRequestedUrl();
        $req_url = str_replace(home_url()."/", "", $req_url); 
        $req_url = str_replace($language, "", $req_url); 

        global $wpdb;

        $translator_urls_table= $wpdb->prefix."translator_urls"; 

        $urls_d =$wpdb->get_row("select translation from `$translator_urls_table` where source = '$req_url' AND language = '$language'");
       // echo "<pre>"; print_r($urls_d); die;
        if($urls_d){
            $tr = $urls_d->translation;
            $this->_content .= "<script>window.history.pushState('', '', '$tr');</script>";
        }

        $response->setContent($this->_content);

        $response->end();
    }

    public function overwriteText($contents,$lang_code){
        $site_key = Configuration::getInstance()->get('token'); 
        if(file_exists(TRANSLATOR_PLUGIN_PATH.'/overide_files/'.$site_key.'.json')){
            $dataa = file_get_contents(TRANSLATOR_PLUGIN_PATH.'/overide_files/'.$site_key.'.json');
            $dataa = json_decode($dataa);          
            if(isset($dataa->content->$lang_code)){
                $contentdd = (array) $dataa->content->$lang_code;
                foreach ($contentdd as $key_cont => $cont) {
                    if($cont != ""){
                        $key_cont  = str_replace("\U00DC", "Ü", $key_cont);
                        $key_cont  = str_replace("\u00dc", "Ü",  $key_cont);
                        $key_cont  = str_replace("\U00DF", "ß",  $key_cont);
                        $key_cont  = str_replace( "\u00df", "ß", $key_cont);
                        $key_cont  = str_replace("\U00E4", "ä",  $key_cont);
                        $key_cont  = str_replace("\u00e4", "ä",  $key_cont);
                        $key_cont  = str_replace("\u00F6", "ö",  $key_cont);
                        $key_cont  = str_replace("\u00f6", "ö",  $key_cont);
                        $key_cont  = str_replace("\u00FC", "ü",  $key_cont);
                        $key_cont  = str_replace("\U00fc", "ü",  $key_cont);
                        $key_cont  = str_replace("\U00C4", "Ä",  $key_cont);
                        $key_cont  = str_replace("\u00c4", "Ä",  $key_cont);
                        $key_cont  = str_replace("\u00D6", "Ö",  $key_cont);
                        $key_cont  = str_replace("\u00d6", "Ö",  $key_cont);
                        $contents = str_replace($key_cont, $cont, $contents);  
                    }                    
                }
            }         
        } 
        $contents = str_replace("<dd>", "", $contents);
        $contents = str_replace("</dd>", "", $contents);
        $contents = str_replace("1312 ", "", $contents);
        $contents = str_replace(" 1312", "", $contents);
        $contents = str_replace("1312", "", $contents);

        $contents = str_replace("<p><!doctype", "<!doctype", $contents);
        $contents = str_replace("<p><!doctype", "<!doctype", $contents);
        $contents = str_replace("</html></p>", "</html>", $contents);
        $contents = str_replace("</html></p>", "</html>", $contents);

        return $contents;
    }

    protected function load() {
       /* $checkData = Database::getInstance()->checkCache($_SERVER['REQUEST_URI'], $this->_web_page_char);
        if($checkData){
            return false; // if return true then skip cache and send to translation
        }*/

        $cache_file = $this->getPath() . $this->_hash . '.php';
        if (!file_exists($cache_file)) {
            return false;
        }

        $content = file_get_contents($cache_file);

        // Update cache file modified time
        touch($cache_file);

        // Remove php head
        $this->_content = substr($content, 15);

        return true;
    }

    public function save() {
        if (!$this->_hash || !$this->_language) {
            return false;
        }

        $response = Response::getInstance();

        $content = $response->getContent();

        if (!$content) {
            return false;
        }

        $cache_file = $this->getPath() . $this->_hash . '.php';
        $cache_path = pathinfo($cache_file, PATHINFO_DIRNAME);

        if (!file_exists($cache_path)) {
            mkdir($cache_path);
        }

        file_put_contents($cache_file, '<?php die(); ?>' . $content);

        return true;
    }

    /**
     * Check if the request to launch this task should be executed or not
     *
     * @return bool
     */
    public function shouldBeExecuted() {
        if (!Configuration::getInstance()->get('cache_enabled')) {
            return false;
        }
        $cache_info_file = $this->getPath() . 'clear.txt';
        if (file_exists($cache_info_file) && (int)file_get_contents($cache_info_file) + Configuration::getInstance()->get('cache_time_check') > time()) {
            return false;
        }
        return true;
    }

    public function clear() {
        if (!$this->shouldBeExecuted()) {
            return;
        }

        $cache_path = $this->getPath();

        $folders = scandir($cache_path);
        if (!count($folders)) return;
        $folders = array_diff($folders, ['.', '..']);
        if (!count($folders)) return;

        $total_size = 0;
        $total_cleared = 0;
        foreach($folders as $folder) {
            $files = glob($cache_path . $folder . '/*.php');
            foreach($files as $file) {
                $size = filesize($file);
                $total_cleared += $size;
                $total_size += $size;
                @unlink($file);
            }
            @rmdir($cache_path . $folder);
        }

        file_put_contents($cache_path . 'clear.txt', time());

        $response = Response::getInstance();
        $response->setContent('Cleared cache: ' . (int)($total_cleared/1000) . 'kb');
        $response->end();
    }

    public function clear_cache(){
        $cache_path = $this->getPath();

        $folders = scandir($cache_path);
        if (!count($folders)) return;
        $folders = array_diff($folders, ['.', '..']);
        if (!count($folders)) return;

        foreach($folders as $folder) {
            $files = glob($cache_path . $folder . '/*.php');
            foreach($files as $file) {
                $x = $file;
                @unlink($file);
            }
            @rmdir($cache_path . $folder);
        }
    }
}
