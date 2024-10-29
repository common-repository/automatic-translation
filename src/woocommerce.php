<?php
/**
 * Translate woocommerce order
 */

use Translator\Vendor\Translator\Script\Core\Boundary;
use Translator\Vendor\Translator\Script\Core\Configuration;
use Translator\Vendor\Translator\Script\Core\Helper;
use Translator\Vendor\Translator\Script\Core\Processor;
use Translator\Vendor\Translator\Script\Core\Request;
use Translator\Vendor\Translator\Script\Core\Translation;

include_once ABSPATH . 'wp-admin/includes/plugin.php';
if (!is_plugin_active('woocommerce/woocommerce.php')) {
    return;
}

$translatore_options = translatorGetOptions();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View request, no action
if (!empty($_SERVER['HTTP_TRANSLATOR_ORIGINAL_LANGUAGE']) && $_SERVER['HTTP_TRANSLATOR_ORIGINAL_LANGUAGE'] !== $translator_options['language_default'] && in_array($_SERVER['HTTP_TRANSLATOR_ORIGINAL_LANGUAGE'], $translator_options['languages_enabled']??[])) {
    add_filter('woocommerce_ajax_get_endpoint', function ($endpoint) {
        return str_replace('%%endpoint%%', '%%endpoint%%&translator_language=' . sanitize_text_field($_SERVER['HTTP_TRANSLATOR_ORIGINAL_LANGUAGE']), $endpoint);
    });
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View request, no action
if (!empty($_GET['translator_language']) && $_GET['translator_language'] !== $translator_options['language_default'] && in_array($_GET['translator_language'], $translator_options['languages_enabled'])) {
    /**
     * Translate WooCommerce fragments
     *
     * @param array $data WooCommerce fragments
     *
     * @return mixed
     */
    function translatorUpdateWooCommerceFragments($data)
    {
        if (empty($data)) {
            return $data;
        }

        $content = '<html><head></head><body>';
        foreach ($data as $class => $fragment) {
            $content .= '<divtranslator data-wp-translator-class="' . $class . '">' . $fragment . '</divtranslator>';
        }
        $content .= '</body></html>';

        define('TRANSLATOR_SCRIPT_TRANSLATION', 1);
        define('TRANSLATOR_SCRIPT_TRANSLATION_VERSION', 'wordpress_plugin/1.8.11');

        include_once(TRANSLATOR_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');

        Configuration::getInstance()->load(TRANSLATOR_PLUGIN_PATH);

        $options = translatorGetOptions();
        Configuration::getInstance()->set('token', $options['translator_api_token']);

        $boundary = new Boundary();
        $request =  Request::getInstance();

        $boundary->addPostFields('version', Processor::$version);
        $boundary->addPostFields('url', $request->getBaseUrl());
        $boundary->addPostFields('domain', $request->getBaseUrl());
        $boundary->addPostFields('language', sanitize_text_field($_GET['translator_language'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View request, no action
        $boundary->addPostFields('requested_path', '/');
        $boundary->addPostFields('content', $content);
        $boundary->addPostFields('site_key', Configuration::getInstance()->get('token'));
        $boundary->addPostFields('ip', Helper::getIpAddress());
        $boundary->addPostFields('response_code', 200);
        $boundary->addPostFields('user_agent', !empty($_SERVER['HTTP_USER_AGENT'])?sanitize_text_field($_SERVER['HTTP_USER_AGENT']):'');

        $ch = curl_init();

        list($translated_content, $response_code) = Translation::getInstance()->_translate($ch, $boundary);

        if (!$translated_content || $response_code !== 200) {
            // We failed to translate
            return $data;
        }

        curl_close($ch);

        $result = json_decode($translated_content);

        foreach ($data as $class => &$fragment) {
            preg_match('/<divtranslator data-wp-translator-class="' . preg_quote($class) . '">(.*?)<\/divtranslator>/s', $result->content, $matches);
            if (!$matches) {
                return $data;
            }
            $fragment = $matches[1];
        }

        return $data;
    }

    add_filter('woocommerce_update_order_review_fragments', 'translatorUpdateWooCommerceFragments', 1000, 1);
    add_filter('woocommerce_add_to_cart_fragments', 'translatorUpdateWooCommerceFragments', 1000, 1);
}

/**
 * Reset wc fragment
 */

add_action('wp_loaded', function () {

    $script ='try {
            jQuery(document).ready(function($) {
                if (typeof wc_cart_fragments_params === "undefined") {
                    return false;
                }
                if (typeof translator_configs.vars.configs.current_language === "undefined") {
                    return;                
                }
               
                function check() {
                    if(window.localStorage.getItem("translator_wc_lang") !== translator_configs.vars.configs.current_language) {
                        window.localStorage.setItem("translator_wc_lang", translator_configs.vars.configs.current_language);
                        $(document.body).trigger("wc_fragment_refresh");                    
                    }
                }
               
                $(document.body).on("wc_fragments_loaded", function () {
                    check();
                });
            });
        } catch (e) {
            console.warn(e);
        }';

    wp_register_script('translator_woocommerce_cart_fragments', '', array('jquery'), '', true);
    wp_enqueue_script('translator_woocommerce_cart_fragments');
    wp_add_inline_script('translator_woocommerce_cart_fragments', $script);
});
