<?php

namespace Translator\Vendor\Translator\Script\Core;

defined('TRANSLATOR_SCRIPT_TRANSLATION') or die();

class CurlRequest
{
    public function makeRequest()
    {
        $ch = curl_init();

        if (in_array($_SERVER['REQUEST_METHOD'], array('POST', 'PUT'))) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
            } else {
                curl_setopt($ch, CURLOPT_PUT, true);
            }

            $post_fields = array();
            if (!empty($_FILES)) {
                foreach ($_FILES as $file_name => $file_value) {
                    $post_fields[$file_name] = '@' . realpath($file_value['tmp_name']) . ';filename=' . $file_value['name'] . ';type=' . $file_value['type'];
                }
            }

            if (!empty($_POST)) {
                foreach ($_POST as $post_name => $post_value) {
                    $post_fields[$post_name] = $post_value;
                }
            }

            if (count($post_fields)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
            }
            //fixme: handle x-www-form-urlencoded  form-data and raw https://www.w3.org/TR/html401/interact/forms.html#h-17.13.4.1
        }

        $url = Request::getInstance()->getNonTranslatedUrl();
       /* if(strpos($url,"?") === false){
            $url = $url.'?language='.$_GET['translator_language'];
        }else{
            $url = $url.'&language='.$_GET['translator_language'];
        }
*/
        Debug::log('Requesting website url ' . $url);

        $input_headers = array();
        foreach ($_SERVER as $header_name => $header_value) {
            if (substr($header_name, 0, 5) !== 'HTTP_') {
                continue;
            }

            if (in_array($header_name, array('HTTP_HOST', 'HTTP_ACCEPT_ENCODING', 'HTTP_CONTENT_LENGTH'))) {
                continue;
            }

            $input_headers[] = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($header_name, 5))))) . ': ' . $header_value;
        }

        $input_headers[] = 'Translator-Original-Language:' . preg_replace('[^a-zA-Z-]', '', Request::getInstance()->getLanguage());

        // Add real user IP
        $input_headers[] = 'X-Forwarded-For: ' . Helper::getIpAddress();

        curl_setopt($ch, CURLOPT_URL, $url);
        if (Configuration::getInstance()->get('server_ip') !== null) {
            curl_setopt($ch, CURLOPT_CONNECT_TO, [Request::getInstance()->getHostname() . ':' . Configuration::getInstance()->get('server_port') . ':' . Configuration::getInstance()->get('server_ip') . ':' . Configuration::getInstance()->get('server_port')]); // fixme: only available from php 7.0
        }
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $input_headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if (Configuration::getInstance()->get('dl_certificates') === true) {
            curl_setopt($ch, CURLOPT_CAINFO, Certificates::getInstance()->getPath());
        }

        $curl_multi = CurlMulti::getInstance();
        $curl_multi->addRequest(Cache::getInstance());
        $curl_multi->addRequest(Certificates::getInstance());
        $curl_multi->executeRequests();

        $time_start = microtime(true);
        $curl_response = curl_exec($ch);
        Debug::timing('Curl website request took %s', $time_start);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($curl_response, 0, $header_size);
        $body = substr($curl_response, $header_size);
        $redirected_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $response = Response::getInstance();

        if ($response_code === 0) {
            $response->setRedirect($url);
            Debug::log('Failed to retrieve website data, redirect to ' . $url . '. Error was :' . curl_error($ch));
            Debug::saveError('Failed to retrieve website data, redirect to ' . $url . '. Error was :' . curl_error($ch));
            $response->end();
        }

        // Add actual request headers
        foreach (explode("\r\n", $headers) as $index => $header) {
            if ($index === 0) continue;
            if ($header === '') continue;

            $header_parts = explode(':', $header, 2);

            if(count($header_parts) !== 2) {
                continue;
            }

            $response->addHeader($header_parts[0], ltrim($header_parts[1]));
        }

        $content_type = $response->getHeader('Content-Type');
        $content_type = explode(';', $content_type)[0];
        if (!in_array($content_type, ['text/html', 'application/json', 'application/xhtml+xml', 'application/xml', 'text/xml'])) {
            $response->setRedirect($url);
            Debug::log('Content type not translatable ' . $content_type);
            Debug::saveError('Content type not translatable ' . $content_type);
            $response->end();
        }

        $response->setResponseCode($response_code);
        $response->setContent($body);
        Debug::log('Original content retrieved: ' . $body, 5);

        $curl_multi->waitRequests();

        if ($redirected_url) {
            Debug::log('Website redirect to ' . $redirected_url);

            if (!$response->hasHeader('Translator-Translated-Redirect')) {
                // The url given is not a multilingual url, we need to translate it first
                $redirected_url = Url::translateUrl($redirected_url);
            }
            $response->setRedirect($redirected_url);
            $response->end();
        }

    }
}
