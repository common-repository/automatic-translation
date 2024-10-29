<?php
namespace Translator\Vendor\Translator\Script\Core;

defined('TRANSLATOR_SCRIPT_TRANSLATION') or die();

class Translation {

    /**
     * @var null|Translation
     */
    private static $_instance = null;

    /**
     * The editor token in cas we are in edition mode
     *
     * @var null|string
     */
    private $editor_token = null;

    /**
     * Retrieve singleton instance
     *
     * @return Translation|null
     */
    public static function getInstance() {

        if(is_null(self::$_instance)) {
            self::$_instance = new Translation();
        }

        return self::$_instance;
    }

    public function enableEditor($token) {
        $this->editor_token = $token;
    }

    public function translate()
    {
        $response = Response::getInstance();

        if (!$response->getContent()) {
            return;
        }

        Hook::trigger('onBeforeTranslation');

        $boundary = Boundary::getInstance();
        $request =  Request::getInstance();

        $boundary->addPostFields('version', Processor::$version);
        $boundary->addPostFields('request_url', Request::getInstance()->getRequestedUrl());
        $boundary->addPostFields('url', $request->getBaseUrl());
        $boundary->addPostFields('cms', 'wordpress');
        $boundary->addPostFields('domain', $request->getBaseUrl());
        $boundary->addPostFields('language', $request->getLanguage());
        $boundary->addPostFields('requested_path', $request->getPathname());
        $boundary->addPostFields('raw_request_url', $request->getPathname());
        $boundary->addPostFields('content', $response->getContent());
        $boundary->addPostFields('site_key',Configuration::getInstance()->get('token'));
        $boundary->addPostFields('ip', Helper::getIpAddress());
        $boundary->addPostFields('response_code', $response->getResponseCode());
        $boundary->addPostFields('user_agent', !empty($_SERVER['HTTP_USER_AGENT'])?$_SERVER['HTTP_USER_AGENT']:'');
        if(isset($_GET['current_language'])){
            $boundary->addPostFields('default_language',$_GET['current_language']);
        }
        if ($this->editor_token) {
            // $boundary->addPostFields('editor_token', $this->editor_token);
        }

        Hook::trigger('onBeforeTranslationRequest');

        $ch = curl_init();

        list($translated_content, $response_code) = $this->_translate($ch, $boundary);

        if (!$translated_content || $response_code !== 200) {
            // Failed to translate, redirect visitor to the non translated page
            $response->clearContent();
            $non_translated_url = $request->getNonTranslatedUrl();
            Debug::log('Failed to translate, response code '.$response_code.', error: ' . curl_error($ch) . ', message: ' . $translated_content . ', redirect to ' . $non_translated_url);
            Debug::saveError($translated_content);
            $response->setRedirect($non_translated_url, 307);
            $response->end();
        }

        curl_close($ch);

        $result = json_decode($translated_content);
        if (!$result) {
            // Failed to decode content, redirect visitor to the non translated page
            $response->clearContent();
            $non_translated_url = $request->getNonTranslatedUrl();
            Debug::log('Failed to decode translated content, redirect to ' . $non_translated_url);
            $response->setRedirect($non_translated_url, 307);
            $response->end();
        }

        Debug::log('Translation decoded ' . print_r($result, true), 3);

        if (isset($result->url_translations)) {
            // Defer::getInstance()->defer(function() use ($result) {
                $new_urls = $result->url_translations;
                //$new_urls = get_object_vars($new_urls);
                $requested_path = $request->getBaseUrl();
                Database::getInstance()->saveUrls((array)$new_urls, $requested_path);
            // });
        }

        if (isset($result->urls_untranslated)) {
            Defer::getInstance()->defer(function() use ($result) {
                Database::getInstance()->removeUrls((array)$result->urls_untranslated);
            });
        }

        if(isset($result->plan_limit) && isset($result->used_limit)){
            Defer::getInstance()->defer(function() use ($result) {
                Database::getInstance()->updateLimit($result->used_limit,$result->plan_limit);
            });
        }

        if(Cache::getInstance()->$_cache_load){
            $word = Cache::getInstance()->$_web_page_char;
            Database::getInstance()->checkCache($result->redirect,$word);
        }

        if (isset($result->redirect)) {
            Debug::log('Translation redirect to ' . $result->redirect);
            // $response->clearContent();
            // $response->setRedirect($result->redirect . $request->getQuery(true), 301);
            // $response->end();
        }

        if (isset($result->content_type)) {
            $response->setContentType($result->content_type);
        }

        $response->setContent($result->content);
        $response->setResponseCode(200, false);

        Hook::trigger('onAfterTranslation');
    }

    public function _translate(&$ch, &$boundary) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://login.automatic-translation.online/api/v2/translation');
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $boundary->getContent());
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Cache-Control: no-cache',
            'Content-Type: multipart/form-data; boundary=' . $boundary->getBoundary()
        ));
        if ((int)Configuration::getInstance()->get('port') === 443) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            if (Configuration::getInstance()->get('dl_certificates') === true) {
                // curl_setopt($ch, CURLOPT_CAINFO, Certificates::getInstance()->getPath());
            }
        }

        $time_start = microtime(true);
        $translated_content = curl_exec($ch);
        $content_data = json_decode($translated_content);
        if(!isset($content_data->status)){
            die("Something went wrong.");
        }
        Debug::timing('Curl translation request took %s', $time_start);
        $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        Debug::log('Translated content: ' . $translated_content, 5);

        return [$translated_content, $response_code];
    }

    public function translateJson($content, $url, $language) {
        Hook::trigger('onBeforeJsonTranslation');

        $boundary = Boundary::getInstance();

        $content = json_encode($content);

        $boundary->addPostFields('version', Processor::$version);
        $boundary->addPostFields('url', $url);
        $boundary->addPostFields('domain', $url);
        $boundary->addPostFields('language', $language);
        $boundary->addPostFields('is_search', true);
        $boundary->addPostFields('cms', 'wordpress');
        $boundary->addPostFields('content', $content);
        $boundary->addPostFields('site_key',Configuration::getInstance()->get('token'));
        $boundary->addPostFields('ip', Helper::getIpAddress());
        $boundary->addPostFields('user_agent', !empty($_SERVER['HTTP_USER_AGENT'])?$_SERVER['HTTP_USER_AGENT']:'');
        if(isset($_GET['current_language'])){
            $boundary->addPostFields('default_language',$_GET['current_language']);
        }

        Hook::trigger('onBeforeJsonTranslationRequest');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://login.automatic-translation.online/api/translation');
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $boundary->getContent());
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Cache-Control: no-cache',
            'Content-Type: multipart/form-data; boundary=' . $boundary->getBoundary()
        ));
        if ((int)Configuration::getInstance()->get('port') === 443) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            if (Configuration::getInstance()->get('dl_certificates') === true) {
                // curl_setopt($ch, CURLOPT_CAINFO, Certificates::getInstance()->getPath());
            }
        }

        $time_start = microtime(true);
        $translated_content = curl_exec($ch);
        Debug::timing('Curl translation request took %s', $time_start);
        $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        Debug::log('Translated content: ' . $translated_content, 5);

        curl_close($ch);

        if (!$translated_content || $response_code !== 200) {
            return false;
        }

        $result = json_decode($translated_content);
        if (!$result) {
            return false;
        }

        Debug::log('Translation decoded ' . print_r($result, true), 3);

        Hook::trigger('onAfterJsonTranslation');

        return json_decode($result->content);
    }
}
