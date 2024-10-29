<?php
/**
 * Plugin Name: Automatic Translation
 * Plugin URI: https://automatic-translation.online/
 * Description: Automatic translation plugin used to translate frontend website content with Deepl machine translator.
 * Version: 1.0.4
 * Text Domain: automatic-translation.online
 * Author: masterhomepage
 * Author URI: https://www.masterhomepage.ch/
 * License: GPL2
 */

defined('ABSPATH') || die('');

/* Plugin requirements code */
$curlInstalled = function_exists('curl_version');
$phpVersionOk = version_compare(PHP_VERSION, '7.0', '>=');
if (!$curlInstalled || !$phpVersionOk) {
    add_action('admin_init', function () {
        $activate = sanitize_text_field($_GET['activate']);
        if (current_user_can('activate_plugins') && is_plugin_active(plugin_basename(__FILE__))) {
            deactivate_plugins(__FILE__);
            unset($activate);
        }
    });
    add_action('admin_notices', function () use ($curlInstalled, $phpVersionOk) {

        echo('<div class="error">');
        if (!$curlInstalled) {
            echo('<p><strong>Curl php extension is required</strong> to install Translator Plugin, please make sure to install it before installing Automatic Translation Plugin again.</p>');
        }
        if (!$phpVersionOk) {
            echo('<p><strong>PHP 7.0 is the minimal version required</strong> to install Translator Plugin, please make sure to update your PHP version before installing Automatic Translation Plugin.</p>');
        }
        echo('</div>');
    });
    return;
}

define('TRANSLATOR_VERSION', '1.0.4');
define('TRANSLATOR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TRANSLATOR_PLUGIN_PATH', plugin_dir_path(__FILE__));

register_activation_hook(__FILE__, function () {
    if (!get_option('translator_install_time', false)) {
        add_option('translator_install_time', time());
    }
});

/* Installation Code */
add_action('admin_init', function () {
    $installed_version = get_option('translator_version', null);

    if (!$installed_version) {
        // for plugin installation
        define('TRANSLATOR_SCRIPT_TRANSLATION', true);
        require_once(__DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'translator' . DIRECTORY_SEPARATOR . 'script-php' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Databases' . DIRECTORY_SEPARATOR . 'Mysql.php');

        global $wpdb;
        $mysql_instance = \Translator\Vendor\Translator\Script\Core\Databases\Mysql::getInstance();
        $install_query = $mysql_instance->getInstallQuery($wpdb->base_prefix . 'translator_urls');
        $wpdb->query($install_query);
    } else {
        // for plugin version update
        if (version_compare($installed_version, '1.0.0') === -1) {
            // Do not add flag on already installed versions
            $translator_options = get_option('translator_options');
            $translator_options['add_flag_automatically'] = 0;
            update_option('translator_options', $translator_options);
        }
    }

    if ($installed_version !== TRANSLATOR_VERSION) {
        update_option('translator_version', TRANSLATOR_VERSION);
    }
});


function translatorGetOptions()
{
    $defaults = array(
        'translator_api_token' => '',
        'total_limit' => 0,
        'used_limit' => 0,
        'language_default' => 'en',
        'languages_enabled' => [],
        'cache_enabled' => 1,
        'cache_max_size' => 200,
        'has_admin_sub' => '',
        'overwrite_url' => '',
        'design_format' => 'flag_lang_name',
        'search_translation' => 'true',

    );
    $options = get_option('translator_options');
    if (!empty($options) && is_array($options)) {
        $options = array_merge($defaults, $options);
    } else {
        $options = $defaults;
    }
    return $options;
}

include_once(__DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'admin/scripts.php');

if (wp_doing_ajax()) {
    // for ajax request
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'debug.php');
    return;
}

$languages_content = file_get_contents(dirname(__FILE__) . '/assets/languages.json');
$languages_names = json_decode($languages_content);
$design_formats = array(
                    'flag_lang_name'    => 'Flags with language name',
                    'flag_lang_code'    => 'Flags with language code',
                    'flag'              => 'Flag',
                    'lang_name'         => 'Language Name',
                    'lang_code'         => 'Language Code',
                );

include_once(__DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'switcher-menu.php');
include_once(__DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'woocommerce.php');

include_once(__DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'configuration.php');
include_once(__DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'admin/menu.php');

register_deactivation_hook(__FILE__, 'translatorunInstall');

function translatorunInstall()
{
    global $wp_filesystem;
    if (empty($wp_filesystem)) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        WP_Filesystem();
    }

    // Save htaccess content
    $htaccess_path = ABSPATH . DIRECTORY_SEPARATOR . '.htaccess';
    $htaccess_content = $wp_filesystem->get_contents($htaccess_path);
    if ($wp_filesystem->exists($htaccess_path) && is_writable($htaccess_path)) {
        if (strpos($htaccess_content, '#### TRANSLATOR DO NOT EDIT ####') !== false) {
            $htaccess_content = preg_replace('/#### TRANSLATOR DO NOT EDIT ####.*?#### TRANSLATOR DO NOT EDIT END ####/s', '', $htaccess_content);
            $wp_filesystem->put_contents($htaccess_path, $htaccess_content);
        }
    }
}


add_action('admin_notices', function () {
    $translate_plugins = array(
        'sitepress-multilingual-cms/sitepress.php' => 'WPML Multilingual CMS',
        'polylang/polylang.php' => 'Polylang',
        'polylang-pro/polylang.php' => 'Polylang Pro',
        'translatepress-multilingual/index.php' => 'TranslatePress',
        'weglot/weglot.php' => 'Weglot',
        'gtranslate/gtranslate.php' => 'GTranslate',
        'conveythis-translate/index.php' => 'ConveyThis',
        'google-language-translator/google-language-translator.php' => 'Google Language Translator',
        'linguise/linguise.php' => 'Linguise',
    );

    foreach ($translate_plugins as $path => $plugin_name) {
        if (is_plugin_active($path)) {
            echo('<div class="error">');
            echo('<p>'. sprintf(esc_html__('We\'ve detected that %s translation plugin is installed. Please disable it before using Automatic Translation to avoid conflict with translated URLs mainly', 'translator'), '<strong>'. esc_html_e($plugin_name) .'</strong>') .'</p>');
            echo('</div>');
        }
    }
});

add_action('parse_query', function ($query_object) {
    $translator_original_language = false;
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) === 'HTTP_') {
            $key = str_replace(' ', '-', strtolower(str_replace('_', ' ', substr($name, 5))));
            if ($key === 'translator-original-language') {
                $translator_original_language = $value;
                break;
            }
        }
    }

    if (!$translator_original_language) {
        return;
    }

    $options = translatorGetOptions();

    if (!$options['search_translation']) {
        return;
    }

    if ($query_object->is_search()) {
        $raw_search = $query_object->query['s'];

        define('TRANSLATOR_SCRIPT_TRANSLATION', 1);
        define('TRANSLATOR_SCRIPT_TRANSLATION_VERSION', 'wordpress_plugin/1.8.11');
        include_once('vendor' . DIRECTORY_SEPARATOR . 'autoload.php');

       // echo "<pre>"; print_r($options); die;

        searchTranslation($raw_search,$options["language_default"],$translator_original_language,$query_object);

       /* die;

        Configuration::getInstance()->set('cms', 'wordpress');
        Configuration::getInstance()->set('token', $options['translator_api_token']);

        $translation = \Translator\Vendor\Translator\Script\Core\Translation::getInstance()->translateJson(['search' => $raw_search], site_url(), $translator_original_language, '/');

        if (empty($translation->search)) {
            return;
        }*/

        
    }
});
    function searchTranslation($text,$default_language,$source_language,$query_object){

           

            $post_data = array(
                        "text"=>$text,
                        "default_language"=>$default_language,
                        "source_language"=>$source_language,
                    );

            $args = array(
                'body'        => $post_data,
                'timeout'     => '5',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => array('Content-Type: application/json'),
                'cookies'     => array(),
            );

    
            $response = wp_remote_post( 'https://login.automatic-translation.online/api/searchTranslation', $args );

            if(isset($response["body"])){

                $response = json_decode($response["body"]);
                if(isset($response->text)){

                     $query_object->set('s', $response->text);
                     
                }else{
                    return;
                    
                }
            }
           
             
    }
            

function translatorFirstHook()
{
    add_filter( 'show_admin_bar' , '__return_false' );
    
    static $run = null;
    if ($run) {
        return;
    }
    $run = true;


    // Check if it is admin area or not 
    if (is_admin()) {
        /* for xml file code */
        $cr_time = date("Y-m-d");
        $crontime_file = TRANSLATOR_PLUGIN_PATH."src/admin/crontime.log";
        if(file_exists($crontime_file)){
            $crontime = file_get_contents($crontime_file);     
            if($crontime != $cr_time){
                $dd = file_put_contents($crontime_file, $cr_time);    
                \Translator\WordPress\Admin\helper::generateXml();            
            }
        }else{            
            file_put_contents($crontime_file, $cr_time);
        }
        return;
    }

    $translator_options = translatorGetOptions();


    

    if (!$translator_options['translator_api_token']) {
        return;
    }
    include_once('vendor' . DIRECTORY_SEPARATOR . 'autoload.php');

   
    $REQUEST_URI = sanitize_url($_SERVER['REQUEST_URI']);

    $base_dir = site_url('', 'relative');
    $path = substr($REQUEST_URI, strlen($base_dir));
    $path = parse_url('https://localhost/' . ltrim($path, '/'), PHP_URL_PATH);

    $parts = explode('/', trim($path, '/'));

    if (!count($parts) || $parts[0] === '') {
        parseUrl($translator_options);
        return;
    }
    $check_validation = \Translator\WordPress\Admin\helper::checkValidation($translator_options['translator_api_token']);
    if($check_validation !== true){
        $_GET['translator_action'] = 'delete-cache';
    }

    $language = $parts[0];

    if (!in_array($language, array_merge($translator_options['languages_enabled'], array('zz-zz')))) {
        parseUrl($translator_options);
        return;
    }

    $_GET['current_language'] = $translator_options['language_default']??'';
    $_GET['translator_language'] = $language;

    

    include_once('script.php');
}
add_action('muplugins_loaded', 'translatorFirstHook', 1);
add_action('plugins_loaded', 'translatorFirstHook', 1);


function parseUrl($translator_options){

        if($translator_options["languages_enabled"]){
            $languagessss = $translator_options["languages_enabled"];

           



            if(isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])){

                $detect_lang = sanitize_text_field($_SERVER["HTTP_ACCEPT_LANGUAGE"]); 






               
                foreach ($languagessss as  $languagess) {
                    

                    if(strpos($detect_lang,$languagess) !== false){


                      
                        
                        $lang_codee = getLanguageCookie();




                        if(!$lang_codee){
                            setLanguageCookie($languagess);
                            $lang_codee = $languagess; 


                            if(!isset($_SERVER["HTTP_TRANSLATOR_ORIGINAL_LANGUAGE"])){
                                global $wp;
                                $uri11 = home_url(add_query_arg($wp->query_vars, $wp->request));
                                if($uri11 != ""){
                                    

                                    $uri11 = str_replace(home_url()."/", home_url()."/".$languagess."/", $uri11);

                                     wp_redirect($uri11);
                                    // exit();


                                }

                            }
                        }

                        
                        break;

                    }
                }
                
            }

            
        }
    return;    

}

function setLanguageCookie($languageCode)
{
    setcookie('langg', $languageCode, time() + (86400 * 30), "/");

    return $languageCode;
   
}
/**
 * Get the language cookie
 *
 * @return  string
 *
 * @since   3.4.2
 */
function getLanguageCookie()
{
   
   
    $languageCode = sanitize_text_field($_COOKIE['langg']);
    return $languageCode;
}
