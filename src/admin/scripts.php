<?php
/* Prohibit direct script loading */
defined('ABSPATH') || die('No direct script access allowed!');

//ajax requests

add_action( "wp_ajax_save_api_key", "save_api_key" );
function save_api_key(){
    $token = sanitize_text_field($_POST['tokenkey']);
    if($token != ''){
        $args  = array(
            'method'        => 'POST',
            'httpversion'   => '1.0',
            'headers'       => array('Referer' => site_url()),
            'body'          => array(
                                'domain' => site_url(),
                                'site_key' => $token
                            )
        );
        // check condition
        $result = wp_remote_post( 'https://login.automatic-translation.online/api/validation', $args );
        if ($result instanceof \WP_Error) {
            esc_html_e($result->get_error_message());
        }
        $api_response = $result['response'];
        if($api_response['code'] == 401){
            $api_body = json_decode($result['body']);
            if($api_body->errors == 'Invalid site key'){
                $err_msg = "Please make sure you use the right Translator API key.";
            }else{
                $err_msg = $api_body->errors;
            }
            esc_html_e($err_msg);
        }else{
            $api_body = json_decode($result['body']);
            $limit = $api_body->plan_limit;
            $usage = $api_body->usage;
            $overwrite_url = $api_body->overwrite_url;
            $getData = get_option('translator_options');
            if($getData){
                if(isset($getData['translator_api_token']) && $getData['translator_api_token'] != $token){
                    $getData['translator_api_token'] = $token;
                    if(isset($getData['total_limit']) && $getData['total_limit'] != $limit){
                        $getData['total_limit'] = $limit;
                        $getData['has_admin_sub'] = ($overwrite_url != '') ? 'true' : 'false';
                        $getData['overwrite_url'] = $overwrite_url;
                    }
                    if(isset($getData['used_limit']) && $getData['used_limit'] != $usage){
                        $getData['used_limit'] = $usage;
                    }
                    update_option('translator_options',$getData);
                }
            }else{
                $t_options = array(
                            'translator_api_token' => $token,
                            'total_limit' => $limit,
                            'used_limit'  => $usage,
                            'has_admin_sub' => ($overwrite_url != '') ? 'true' : 'false',
                            'overwrite_url' => $overwrite_url
                        );
                update_option('translator_options',$t_options);
            }
            esc_html_e("success");
        }
    }
    exit();
}

add_action( "wp_ajax_generate_sitemap", "generate_sitemap" ); // 
function generate_sitemap(){
    $languages = [];
    $source_languages = '';
    $root_url = home_url().'/';
    $contents = file_get_contents($root_url);
    $options = translatorGetOptions();
    if(isset($options['language_default'])){
        $languages["default_language"] = $options['language_default'];
    }
    if(isset($options['languages_enabled'])){
         //$options['languages_enabled'] = array('de','es');
        foreach($options['languages_enabled'] as $lang_enable){
            $languages["source_languages"][] = $lang_enable;
        }
    }

    if(isset($languages["default_language"])){
        $source_languages = "";
        if(isset($languages["source_languages"])){                         
            $source_languages = implode("&", $languages["source_languages"]);
        }
        $data_arr = array(
            'contents' => $contents,
            'default_language' => $languages["default_language"],
            'source_languages' => $languages["source_languages"],
            'url' => $root_url
        );

        $args = array(
            'body'        => $data_arr,
            'timeout'     => '5',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => array('Content-Type: application/json'),
            'cookies'     => array(),
        );


        $response = wp_remote_post( 'https://login.automatic-translation.online/api/url_translation', $args );

        if(!$result instanceof \WP_Error && isset($response["body"])){


            $res = json_decode($response["body"]);

                if(isset($res->data) && !empty($res->data)){
                    header('Content-type: text/xml'); 
                    $domtree = new \DOMDocument('1.0', 'UTF-8');
                    $xmlRoot = $domtree->createElement("urlset");
                    $xmlRoot->setAttribute("xmlns","http://www.sitemaps.org/schemas/sitemap/0.9");
                    $xmlRoot->setAttribute("xmlns:xhtml","http://www.w3.org/1999/xhtml");

                    /* append it to the document created */
                    $xmlRoot = $domtree->appendChild($xmlRoot);                        
                    $ii = 0;                        
                    foreach ($res->data as $key_lang => $data_res) {
                        if($ii == 0){
                            foreach ($data_res as $key_link => $link_value) {
                                $currentTrack = $domtree->createElement("url");
                                $currentTrack = $xmlRoot->appendChild($currentTrack);

                                $key_link_text = str_replace($root_url, "", $key_link);
                                $key_link_text = ltrim($key_link_text, '/');
                                $key_link_text = $root_url.$key_link_text; 

                                $currentTrackChild = $domtree->createElement("loc");
                                $currentTrackChild->textContent = $key_link_text;
                                $currentTrack->appendChild($currentTrackChild);

                                $lastmod = $domtree->createElement("lastmod",date('c', strtotime(date("Y-m-d H:i:s"))));
                                $currentTrack->appendChild($lastmod);

                                $priority = $domtree->createElement("priority","1.0");
                                $currentTrack->appendChild($priority);                        

                                 foreach ($res->data as $lang_k => $lang_v) {
                                    if(isset($res->data->$lang_k->{"$key_link"})){
                                        $lang_value = $res->data->$lang_k->{"$key_link"};
                                        $lang_value = str_replace($root_url, "", $lang_value);
                                        $lang_value = ltrim($lang_value, '/');
                                        $lang_value = $root_url.$lang_value; 

                                        $currentTrackChild2 = $domtree->createElement("xhtml:link");
                                        $currentTrackChild2->setAttribute("rel","alternate");
                                        $currentTrackChild2->setAttribute("hreflang",$lang_k);
                                        $currentTrackChild2->setAttribute("href",$lang_value);
                                        $currentTrack->appendChild($currentTrackChild2);
                                    }                               
                                }                        
                            }                      
                        }
                        $ii++;
                    }
                    $domtree->preserveWhiteSpace = false;
                    $domtree->formatOutput = true;

                    $domtree->saveXML();
                    $domtree->save(ABSPATH ."/sitemap.xml");
                    $return = array(
                                'message'  => 'success'
                            );
                    wp_send_json($return);die;
                }else{
                    esc_html_e("no response");exit();
                }
        }else{
            return false;
        }
       
        
    }else{
        esc_html_e( "something went wrong" );exit();
    }   
}

/* Save JSON data API */
function save_json_data(WP_REST_Request $request_data) {
    $file_content = file_get_contents($_POST["json_data"]);
    $file_name = sanitize_text_field($_POST["file_name"]);
    file_put_contents(TRANSLATOR_PLUGIN_PATH.'overide_files/'.$file_name,$file_content);

    esc_html_e("done"); die;
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'automatic-translator/v1', '/savejson', array(
        'methods' => 'POST',
        'callback' => 'save_json_data',
    ) );
} );

/* Get Language API */
function get_lang(WP_REST_Request $request_data) {
    $options = translatorGetOptions();

    if(isset($options['languages_enabled'])){
        foreach ($options['languages_enabled'] as $languages_enabled) {
            $languages[] = $languages_enabled; 
        }
    }
    echo json_encode($languages);die;
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'automatic-translator/v1', '/getlang', array(
        'methods' => 'GET',
        'callback' => 'get_lang',
    ) );
} );

/* delete existing */
add_action('save_post','save_post_callback',1,2);
function save_post_callback($post_id,$post){
    include_once(__DIR__ . DIRECTORY_SEPARATOR . '../configuration.php');

    $success = 0;

    $options = translatorGetOptions();
    if(isset($options["translator_api_token"])){
        if(isset($post->post_type) && ($post->post_type == "page" || $post->post_type == "post")){
            $link = get_permalink($post->ID);
            $uri_path = str_replace(home_url(), "", $link);
            $opstat = TranslatorConfiguration::sourceDeleteCacheComp($uri_path);
            if (!isset($opstat['removed']) || !$opstat['removed']) {
                $opstat = TranslatorConfiguration::sourceDeleteCacheComp(rtrim($uri_path, "/"));
            }
            if ((isset($opstat['removed']) && $opstat['removed'])) {
                deleteCacheApiHit("Cache clear from page save");
            }
        }else{
            TranslatorConfiguration::clearCache();
            deleteCacheApiHit("Cache clear from save data");
        }
    }
    return;
}

function deleteCacheApiHit($message){

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

add_action( 'rest_api_init', function () {
    register_rest_route( 'automatic-translator/v1', '/saveDoc', array(
        'methods' => 'POST',
        'callback' => 'saveDoc',
    ) );
} );

/* Get Language API */
function saveDoc(WP_REST_Request $request_data) {

    $data = array();

    define('TRANSLATOR_SCRIPT_TRANSLATION', true);

    require_once(TRANSLATOR_PLUGIN_PATH."vendor/translator/script-php/src/Request.php");

    $upload_dir = wp_upload_dir(); 

    $file_name = sanitize_text_field($_POST["file_name"]);
    $file_url = sanitize_url($_POST["file_url"]);

    $data = \Translator\Vendor\Translator\Script\Core\Request::saveData($upload_dir,$file_name,$file_url);


    echo json_encode($data);

    die;
}
