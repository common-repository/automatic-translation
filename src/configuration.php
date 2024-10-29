<?php
defined('ABSPATH') || die('');

/**
 * Class Translator Configuration
 */
class TranslatorConfiguration
{
    /**
     * Languages names
     *
     * @var array
     */
    public $languages_names = array();
    public $design_formats  = array();

    /**
     * Constructor 
     *
     * @param array $languages_names & $design_formats
     */
    public function __construct($languages_names,$design_formats)
    {
        if (!empty($languages_names)) {
            $this->languages_names = $languages_names;
        }
        if(!empty($design_formats)){
            $this->design_formats = $design_formats;
        }
        add_action('admin_menu', array($this, 'registerMenuMainPage'));
        add_action('admin_head', array($this, 'adminHeadCode'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueScripts'));
    }



    /**
     * CSS of custom menu icon
     *
     * @return void
     */
    public function adminHeadCode()
    {
        echo('<style>
            #toplevel_page_automatic_translation .dashicons-before img {
                width: 20px;
                padding-top: 8px;
            }
            </style>');
    }

    /**
     * Register menu main page
     *
     * @return void
     */
    public function registerMenuMainPage()
    {
        add_menu_page(
            'Automatic Translation',
            'Automatic Translation',
            'manage_options',
            'automatic_translation',
            array($this, 'renderSettings'),
            TRANSLATOR_PLUGIN_URL . 'assets/images/translator-logo.png'
        );
    }

    public function recursive_sanitize_text_field($array) {

        $values_array = array();

        foreach ( $array as $key => $value ) {
            if ( is_array( $value ) ) {

                foreach ($value as $key2 => $val) {
                    $val = sanitize_text_field($val);
                }
                
            }
            else {
                $value = sanitize_text_field( $value );
            }
        }

        return $array;
    }

    /**
     *  Render settings to show page
     *
     * @return void
     */
    public function renderSettings()
    {
        // check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        $errors = $translate_languages = [];
        $alert_type = $alert_msg = $translation_msg = $plan_limit = $usage = $overwrite_url = '';
        $show_alert = $invalid_translation = false;

        if (isset($_POST['translator_options'])) {
            $all_data = $this->recursive_sanitize_text_field($_POST["translator_options"]);
            if(!wp_verify_nonce($_POST['main_settings'], 'translator_settings')){
                // missmatch nonce
                die;
            }

            $old_options = translatorGetOptions();
            
            $default_language = sanitize_key($all_data['default_lang']);           
            $token = sanitize_text_field($all_data['token_key']);

            if($token != '' && $old_options['translator_api_token'] !== $token){
                $args  = array(
                    'method'        => 'POST',
                    'httpversion'   => '1.0',
                    'headers'       => array('Referer' => site_url()),
                    'body'          => array(
                                        'domain' => site_url(),
                                        'site_key' => $token
                                    )
                );
                // check key validation
                $result = wp_remote_post( 'https://login.automatic-translation.online/api/validation', $args );
                if ($result instanceof \WP_Error) {
                    esc_html_e($result->get_error_message());
                    exit();
                } else {
                    $api_response = $result['response'];
                    if($api_response['code'] == 401){
                        $api_body = json_decode($result['body']);
                        if($api_body->errors == 'Invalid site key'){
                            $err_msg = "Please make sure you use the right Translator API key.";
                        }else{
                            $err_msg = $api_body->errors;
                        }
                        $alert_type = 'danger';
                        $alert_msg = $err_msg;
                        $show_alert = true;
                    }else{
                        $api_body = json_decode($result['body']);
                        $plan_limit = $api_body->plan_limit;
                        $usage = $api_body->usage;
                        $overwrite_url = $api_body->overwrite_url;
                        $translator_options['translator_api_token'] = $token;
                        $translator_options['total_limit'] = $plan_limit;
                        $translator_options['used_limit'] = $usage;
                        $translator_options['has_admin_sub'] = ($overwrite_url != '') ? 'true' : 'false';
                        $translator_options['overwrite_url'] = $overwrite_url;
                    }
                }
            }elseif($token == ''){
                // with error
                $alert_msg = "Please enter Translator API key to translate website content";
                $show_alert = true;
                $alert_type = "danger";
            }

            if(!isset($all_data['translate_lang'])){
                $invalid_translation = true;
                $translation_msg = "Please select translation languages";
            }else{
                $translate_languages = $all_data['translate_lang'];
                $pos = array_search($all_data['default_lang'], $translate_languages);
                if($pos !== false){
                    unset($translate_languages[$pos]);
                }
            }

            $pre_text = '';
            $post_text = '';
            $add_flag_automatically = 0;
            $alternate_link = 0;
            $enable_flag = 0;
            $enable_language_name = 0;
            $browser_redirect = 0;
            $cache_enabled = 1;
            $cache_max_size = 200;
            $search_translation = 0;
            $debug = 0;

            $translator_options['language_default'] = $all_data['default_lang'];
            $translator_options['languages_enabled'] = $translate_languages;
            $translator_options['cache_enabled'] = $cache_enabled;
            $translator_options['cache_max_size'] = $cache_max_size;
            $translator_options['design_format'] = $all_data['design_format'];

            if(!array_key_exists('translator_api_token', $translator_options)){
                $pre_data = get_option('translator_options');
                if(!empty($pre_data) && is_array($pre_data)){
                    $translator_options['translator_api_token'] = $pre_data['translator_api_token'];
                    $translator_options['total_limit'] = $pre_data['total_limit'];
                    $translator_options['used_limit'] = $pre_data['used_limit'];
                    $translator_options['has_admin_sub'] = ($pre_data['overwrite_url'] != '') ? 'true' : 'false';
                    $translator_options['overwrite_url'] = $pre_data['overwrite_url'];
                }else{
                    $translator_options['translator_api_token'] = '';
                    $translator_options['total_limit'] = '';
                    $translator_options['used_limit'] = '';
                    $translator_options['has_admin_sub'] = '';
                    $translator_options['overwrite_url'] = '';
                }
            }
            update_option('translator_options', $translator_options);

            if($show_alert === false){
                $show_alert = true;
                $alert_type = 'success';
                $alert_msg = "Translator setting saved successfully!";
            }
            
        }
        if(isset($_GET["layout"]) && $_GET["layout"] == "cacheurls"){

            $sucess_cache_message = "";
            if(isset($_GET["cache_url_id"])){
               $sucess_cache_message = $this->deleteCache(sanitize_text_field($_GET["cache_url_id"]));
            }
            require_once(TRANSLATOR_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'src/admin/views' . DIRECTORY_SEPARATOR . 'cacheurls.php');
        }else{
            require_once(TRANSLATOR_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'src/admin/views' . DIRECTORY_SEPARATOR . 'main.php');
        }

        
    }

    public function deleteCache($url_id){
        $message = '';
        if(!empty($url_id)){
            global $wpdb;
            $translator_urls_table= $wpdb->prefix."translator_urls"; 
            $query1 = $wpdb->prepare(
                "SELECT * FROM {$translator_urls_table} WHERE `id` = %d",
                $url_id
            );
            $source = $wpdb->get_row($query1);

            if(isset($source->source)){
                self::sourceDeleteCacheComp($source->source);
                $message = "Delete Cache from url (". home_url().$source->source.")";
                $this->deleteCacheFromCompHit($message);
                $message = "Cache clear successfully!";
            }
        }
        return $message;
    }

    public static function sourceDeleteCacheComp($urll){
        $options = translatorGetOptions();

        $opstat = ['removed'=>0, 'failed'=>0];
        if(isset($options["translator_api_token"])){
            $token_hash = md5('data'.$options["translator_api_token"]);

            $folder = TRANSLATOR_PLUGIN_PATH."vendor/translator/script-php/".$token_hash."/cache/". md5(trim($urll));
            $files = glob($folder . '/*.php');
            foreach($files as $file) {
                if(@unlink($file)) {
                    $opstat['removed']++;
                } else {
                    $opstat['failed']++;
                }
            }
            if(@rmdir($folder)) {
                $opstat['removed']++;
            } else {
                $opstat['failed']++;
            }
        }
        return $opstat;
    }
    
    public static function clearCache(){
        $options = translatorGetOptions();

        if(!isset($options["translator_api_token"])){
            return;
        }
        $token_hash = md5('data'.$options["translator_api_token"]);
        $cache_path = TRANSLATOR_PLUGIN_PATH."vendor/translator/script-php/$token_hash/cache/";
        $folders = scandir($cache_path);
        if (!$folders || !count($folders)) return;
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

    public function deleteCacheComp($urlss){


        $options = translatorGetOptions();



    

        foreach ($urlss as $key => $urll) {

           
            

            $translated_hash_path =  $urll->language."_".md5(trim($urll->translation)); 

            if(isset($options["translator_api_token"])){
                 $token_hash = md5('data'.$options["translator_api_token"]);
                 $file_hash =  TRANSLATOR_PLUGIN_PATH."vendor/translator/script-php/".$token_hash."/cache/".$translated_hash_path.".php";
                 if(file_exists($file_hash)){
                    
                    unlink($file_hash);

                 }
            }

        }  
                 
               
    }
    public function deleteCacheFromCompHit($message){

            $url = home_url()."/";
            $post_data = array(
                        "url"=>$url,
                        "message"=>$message,
                    );
            $args = array(
                'body'        => $post_data,
                'timeout'     => '5',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => array(),
                'cookies'     => array(),
            );

    
            $response = wp_remote_post( 'https://login.automatic-translation.online/api/createApiLog', $args );

          
    }

    /**
     * Patch htaccess file
     *
     * @throws Exception With custom error message
     *
     * @return void
     */
    protected function patchHtaccess()
    {
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            WP_Filesystem();
        }

        // Save htaccess content
        $htaccess_path = ABSPATH . DIRECTORY_SEPARATOR . '.htaccess';
        if (!$wp_filesystem->exists($htaccess_path)) {
            throw new Exception(__('Htaccess file doesn\'t exist. This may be a problem is you are under an Apache server. Please check the documentation to finish the installation manually: <a href="https://www.translator.com/documentation/translator-installation/install-translator-on-wordpress/" target="_blank">how to configure Translator</a>', 'translator'), 0);
        }

        $script_path = TRANSLATOR_PLUGIN_PATH . 'script.php';
        $script_path_parts = explode('/', trim($script_path, '/'));
        $abspath_parts = explode('/', trim(ABSPATH, '/'));

        $script_relative_path = array_slice($script_path_parts, count($abspath_parts));
        $script_relative_path = implode('/', $script_relative_path);

        $htaccess_content = $wp_filesystem->get_contents($htaccess_path);
        $htaccess_content_original = $htaccess_content;
        $htaccess_patched = false;
        if (strpos($htaccess_content, '#### TRANSLATOR DO NOT EDIT ####') !== false) {
            $htaccess_patched = true;
        }

        $content =
            '#### TRANSLATOR DO NOT EDIT ####' . PHP_EOL .
            '<IfModule mod_rewrite.c>' . PHP_EOL .
            '   RewriteEngine On' . PHP_EOL .
            '   RewriteRule ^(af|sq|am|ar|hy|az|bn|bs|bg|ca|zh-cn|zh-tw|hr|cs|da|nl|en|eo|et|fi|fr|de|el|gu|ht|ha|iw|hi|hmn|hu|is|ig|id|ga|it|ja|kn|kk|km|ko|ku|lo|lv|lt|lb|mk|mg|ms|ml|mt|mi|mr|mn|ne|no|ps|fa|pl|pt|pa|ro|ru|sm|sr|sd|sk|sl|es|su|sw|sv|tg|ta|te|th|tr|uk|ur|vi|cy|zz-zz)(?:$|/)(.*)$ ' . $script_relative_path . '?translator_language=$1&original_url=$2 [L,QSA]' . PHP_EOL .
            '</IfModule>' . PHP_EOL .
            '#### TRANSLATOR DO NOT EDIT END ####' . PHP_EOL;

        if ($htaccess_patched) {
            // Replace previous version
            $htaccess_content = preg_replace('/#### TRANSLATOR DO NOT EDIT ####.*?#### TRANSLATOR DO NOT EDIT END ####/', $content, $htaccess_content);
        } else {
            // Add it at the beginning of the file
            $htaccess_content = $content . PHP_EOL . $htaccess_content;
        }

        if ($htaccess_content_original === $htaccess_content) {
            return;
        }

        if (!is_writable($htaccess_path)) {
            throw new Exception(__('Htaccess file is not writable, please make sure to allow the current script to update the .htaccess file to make translator work as expected. You can also check our online documentation to read <a href="https://www.translator.com/documentation/translator-installation/install-translator-on-wordpress/" target="_blank">how to configure translator</a>.', 'translator'), 1);
        }

        // Only write if necessary
        if (!$wp_filesystem->put_contents($htaccess_path, $htaccess_content)) {
            throw new Exception(__('Failed to write to htaccess file, please make sure to allow the current script to update the .htaccess file to make translator work as expected. You can also check our online documentation to read <a href="https://www.translator.com/documentation/translator-installation/install-translator-on-wordpress/" target="_blank">how to configure translator</a>.', 'translator'), 2);
        }
    }

    /**
     * Enqueue scripts
     *
     * @return void
     */
    public function enqueueScripts()
    {
        global $current_screen;
        if (!empty($current_screen) && $current_screen->id === 'toplevel_page_automatic_translation') {
            wp_enqueue_script(
                'translator_select_script',
                TRANSLATOR_PLUGIN_URL . 'assets/js/select.min.js',
                array('jquery'),
                TRANSLATOR_VERSION,
                true
            );
            wp_enqueue_script(
                'translator_custom_script',
                TRANSLATOR_PLUGIN_URL . 'assets/js/admin-script.js',
                array('jquery'),
                TRANSLATOR_VERSION,
                true
            );
            wp_localize_script('translator_custom_script', 'translator_vars', array('siteurl' => site_url()));
            wp_enqueue_style(
                'translator_select_style',
                TRANSLATOR_PLUGIN_URL . 'assets/css/select.min.css',
                array(),
                TRANSLATOR_VERSION
            );
            wp_enqueue_style(
                'translator_font_awesome',
                TRANSLATOR_PLUGIN_URL . 'assets/css/all.min.css',
                array(),
                TRANSLATOR_VERSION
            );

            wp_enqueue_style('translator_admin_css', TRANSLATOR_PLUGIN_URL . 'assets/css/admin-style.css', array(), TRANSLATOR_VERSION);
            wp_enqueue_style('translator_admin_bootstrap', TRANSLATOR_PLUGIN_URL.'assets/css/bootstrap.min.css', array(), '5.0');
        }
    }
}

new TranslatorConfiguration($languages_names,$design_formats);
