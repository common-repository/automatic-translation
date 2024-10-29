<?php
namespace Translator\Vendor\Translator\Script\Core;

defined('TRANSLATOR_SCRIPT_TRANSLATION') or die();

class Processor {

    public static $version = TRANSLATOR_SCRIPT_TRANSLATION_VERSION;

    public function __construct($version = null)
    {
        if ($version !== 'null') {
            Processor::$version = $version;
        }

        if (!empty(Configuration::getInstance()->get('debug')) && Configuration::getInstance()->get('debug')) {
            if (is_int(Configuration::getInstance()->get('debug'))) {
                $verbosity = Configuration::getInstance()->get('debug');
            } else {
                $verbosity = 0;
            }
            Debug::enable($verbosity);
        }

        // Generate data folder name and create it if it doesn't exit
        if (Configuration::getInstance()->get('data_dir') === null) {
            $data_folder = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..') . DIRECTORY_SEPARATOR . md5('data' . Configuration::getInstance()->get('token'));
            if (!file_exists($data_folder)) {
                mkdir($data_folder);
                mkdir($data_folder . DIRECTORY_SEPARATOR . 'database');
                mkdir($data_folder . DIRECTORY_SEPARATOR . 'cache');
                mkdir($data_folder . DIRECTORY_SEPARATOR . 'tmp');
                file_put_contents($data_folder . DIRECTORY_SEPARATOR . '.htaccess', 'deny from all');
            }
            Configuration::getInstance()->set('data_dir', $data_folder);
        }

        // Finalize defer actions on shutdown
        register_shutdown_function(function() {
            Defer::getInstance()->finalize();
        });
    }

    /**
     * Load the page and translate it
     */
    public function run()
    {
        if (!isset($_GET['translator_language'])) {
            die();
        }

	// Normalize language in request url to avoid duplicate content and redirect loop
        $norm_url = Request::getInstance()->getLanguageNormalizedURL();
        if (strlen($norm_url['url'])!=strlen(rtrim($norm_url['normurl'],'/'))) {
            Response::getInstance()->setRedirect($norm_url['normurl']);
            Response::getInstance()->end();
        }

        Debug::log('$_SERVER: ' . print_r(array_merge($_SERVER, ['PHP_AUTH_PW' => '', 'HTTP_AUTHORIZATION' =>  '', 'HTTP_COOKIE' => '']), true), 5);

        Hook::trigger('onBeforeMakeRequest');

        ob_start();
        $request = new CurlRequest();
        $request->makeRequest();

        Hook::trigger('onAfterMakeRequest');

        if (Response::getInstance()->getResponseCode() === 304) {
            Debug::log('304 Not modified');
            Response::getInstance()->end();
        }

        // 13-june
        $request =  Request::getInstance();
        $site_key = Configuration::getInstance()->get('token'); 
        if($site_key == ""){
            return;
        }
        $domain = Request::getInstance()->getBaseUrl();
        $domain = str_replace("https://", "", $domain);
        $domain = str_replace("http://", "", $domain);
        $domain = str_replace("/", "", $domain);

        $contents = Response::getInstance()->getContent();
        $contents = str_replace('&nbsp;','<nbsp></nbsp>',$contents);

        //HOTFIX: invalid html closing tag in javascript
        $matches = [];
        preg_match_all('/<script([\S\s]*?)<\/script>/i', $contents, $matches, PREG_SET_ORDER, 0);
        foreach($matches as $match) {
            if(count($match)==2) {
                $script = trim(array_shift($match));
                $inner_script = trim(array_shift($match));
                $replacement = str_replace('</', '<\/',$inner_script);
                $contents = str_replace($script, str_replace($inner_script, $replacement, $script), $contents);
            }
        }

        Response::getInstance()->setContent($contents);

        preg_match_all("/\<\w[^<>]*?\>([^<>]+?\<\/\w+?\>)?|\<\/\w+?\>/i", $contents, $matches);

        $lang_code = $request->getLanguage(); 
        if($lang_code == "zz-zz"){
            return;
        }
        // end 13


        // We want to translate the page
        $editor_enabled = !empty($_COOKIE['translatorEditorToken']) && !empty($_COOKIE['translatorEditorStatus']);
        $cache_enabled = Configuration::getInstance()->get('cache_enabled');

        /*if ($editor_enabled) {
            Translation::getInstance()->enableEditor($_COOKIE['translatorEditorToken']);
            Response::getInstance()->addHeader('Cache-Control', 'no-store');
        } else if ($cache_enabled) {
            // Serve cache if it exists
            Cache::getInstance()->serve();
        }*/
        if (Configuration::getInstance()->get('cache_enabled')) {
            Cache::getInstance()->serve();
        }

        if(!Cache::getInstance()->$_cache_load){  // if cache not exist call translation function
            Translation::getInstance()->translate();
        }else{
            // cache exists
        }

        if (Configuration::getInstance()->get('cache_enabled')) {
            Defer::getInstance()->defer(function () {
                Cache::getInstance()->save();
            });
        }

        $contents = Response::getInstance()->getContent();
        $contents = str_replace('<nbsp></nbsp>','&nbsp;',$contents);

        $contents = str_replace('action="/', 'action="/'.$lang_code.'/', $contents);
        $contents = str_replace('action="'.home_url().'/', 'action="'.home_url().'/'.$lang_code.'/', $contents);

        $contents  = Cache::getInstance()->overwriteText($contents,$lang_code);

        $req_url = Request::getInstance()->getRequestedUrl();
        $req_url = str_replace(home_url()."/", "", $req_url);
        $req_url = str_replace($lang_code, "", $req_url); 

        global $wpdb;

        $translator_urls_table= $wpdb->prefix."translator_urls"; 

        $urls_d =$wpdb->get_row("select translation from `$translator_urls_table` where source = '$req_url' AND language = '$lang_code'");

        if($urls_d){
            $tr = $urls_d->translation;
            $contents .= "<script>window.history.pushState('', '', '$tr');</script>";
        }

        Response::getInstance()->setContent($contents);
        Response::getInstance()->end();
    }

    public function update()
    {
        Updater::getInstance()->update();
    }

    public function editor()
    {
        if (!empty($_POST['token']) && !empty($_POST['expires']) && !empty($_POST['timestamp']) && !empty($_POST['signature'])) {
            // Validate the signature from translator
            if ($_POST['timestamp'] < time()-120) {
                // Make sure the timestamp is not more than a few minutes old (120 seconds)
                die('Wrong signature');
            }

            $POST = $_POST;
            ksort($POST);

            $params = [];
            foreach($POST as $key => $value) {
                if ($key === 'signature') {
                    continue;
                }

                $params[] = $key . '=' . $value;
            }

            $signature = hash_hmac('sha256', implode('', $params), Configuration::getInstance()->get('token'));

            if ($signature !== $_POST['signature']) {
                die('Wrong signature');
            }

            Response::getInstance()->addCookie('translatorEditorToken', $_POST['token'], strtotime($_POST['expires']));
            Response::getInstance()->addCookie('translatorEditorStatus', 1);
        }

        $content = file_get_contents(__DIR__ .  DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'editor.html');

        $options = '';
        foreach (json_decode($_POST['languages']) as $language) {
            $options .= '<option value="'.htmlspecialchars($language->code).'">'.htmlspecialchars($language->name).'</option>';
        }

        $content = str_replace('{{options}}', $options, $content);

        Response::getInstance()->setContent($content);
        Response::getInstance()->end();
    }

    public function clearCache()
    {
        Cache::getInstance()->clear();
    }

    public function updateCertificates()
    {
        Certificates::getInstance()->downloadCertificates();
    }

}
