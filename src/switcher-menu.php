<?php

defined('ABSPATH') || die('');
include_once(TRANSLATOR_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'helper.php');
if (is_admin()) {
    return;
}

add_action('init', function () use ($languages_names) {
    $hide_other_langs = false;
    $translator_options = translatorGetOptions(); // Get from module parameters
    $check_valid = \Translator\WordPress\Admin\helper::checkValidation($translator_options['translator_api_token']);
    if($check_valid !== true){
        return;
    }
    $languages_enabled_param = isset($translator_options['languages_enabled']) ? $translator_options['languages_enabled'] : array();
    $default_language = isset($translator_options['language_default']) ? $translator_options['language_default'] : 'en';
    $design_format = isset($translator_options['design_format']) ? $translator_options['design_format'] : 'flag_lang_name';
    $usage_limit = isset($translator_options['used_limit']) ? (int)$translator_options['used_limit'] : '';
    $total_limit = isset($translator_options['total_limit']) ? $translator_options['total_limit'] : '';

    if($total_limit != 'unlimited'){
        $total_limit = (int)$total_limit;
        if($usage_limit >= $total_limit){
            $hide_other_langs = true; // show only default
        }
    }
    
    $language_list = array($default_language => $languages_names->{$default_language}->name);

    foreach ($languages_enabled_param as $language) {
        if ($language === $default_language) {
            continue;
        }

        if (!isset($languages_names->{$language})) {
            continue;
        }

        $language_list[$language] = $languages_names->{$language}->name;
    }
    //sanitize_text_field

    $REQUEST_URI =  sanitize_text_field($_SERVER['REQUEST_URI']);

    if (preg_match('@(\/+)$@', parse_url($REQUEST_URI, PHP_URL_PATH), $matches) && !empty($matches[1])) {
        $trailing_slashes = $matches[1];
    } else {
        $trailing_slashes = '';
    }

    $base = rtrim(translatorForceRelativeUrl(site_url()), '/');

    $config = array_merge(array(
        'languages' => $language_list,
        'base' => $base,
        'original_path' => rtrim(substr(rtrim(parse_url($REQUEST_URI, PHP_URL_PATH), '/'), strlen($base)), '/'),
        'trailing_slashes' => $trailing_slashes,
        'hide_other_langs' => $hide_other_langs
    ), $translator_options);

    // Remove api token
    unset($config['translator_api_token']);

    $HTTPS = sanitize_text_field($_SERVER['HTTPS']);

    // for alternate link & hreflang
    $scheme = !empty($HTTPS) ? 'https' : 'http';
    $host = parse_url(site_url(), PHP_URL_HOST);
    $path = $config['original_path'];
    $query = parse_url(site_url(), PHP_URL_QUERY);
    $alternates = $language_list;
    $alternates['x-default'] = 'x-default';

    $head_content = [];
    global $wpdb;
    foreach ($alternates as $language_code => $language_name) {
        $url_translation = null;
        if ($path) {
            $db_query = $wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'translator_urls WHERE hash_source=%s AND language=%s', md5($path), $language_code);
            $url_translation = $wpdb->get_row($db_query);
        }

        if (!is_wp_error($url_translation) && !empty($url_translation)) {
            $url = $scheme . '://' . $host . $base . htmlentities($url_translation->translation) . $trailing_slashes . $query;
        } else {
            $url = $scheme . '://' . $host . $base . (in_array($language_code, array($default_language, 'x-default')) ? '' : '/' . $language_code) . $path . $trailing_slashes . $query;
        }

        $head_content[] = '<link rel="alternate" hreflang="' . $language_code . '" href="' . $url . '" />';
    }

    if (!empty($head_content)) {
        add_action('wp_head', function ($a) use ($head_content) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped already
            echo(implode("\n", $head_content));
        });
    }

    // create menu item
    add_filter('wp_get_nav_menu_items', function ($items) use ($language_list, $config, $languages_names) {
        if (doing_action('customize_register')) { // needed since WP 4.3, doing_action available since WP 3.9
            return $items;
        }
        
        $last_item = end($items);
        $last_menu_order = $last_item->menu_order;

        $flag_path = TRANSLATOR_PLUGIN_URL . 'assets'. DIRECTORY_SEPARATOR .'images'. DIRECTORY_SEPARATOR .'flags' . DIRECTORY_SEPARATOR;

        $found = false;
        $new_items = array();
        $offset = 0;
        $HTTP_TRANSLATOR_ORIGINAL_LANGUAGE = sanitize_text_field($_SERVER['HTTP_TRANSLATOR_ORIGINAL_LANGUAGE']??'');


        if(isset($HTTP_TRANSLATOR_ORIGINAL_LANGUAGE) && $HTTP_TRANSLATOR_ORIGINAL_LANGUAGE != ""){
            $current_language = $HTTP_TRANSLATOR_ORIGINAL_LANGUAGE;
        }else{
            $language_get = sanitize_text_field($_GET['language']??'');
            $current_language = (!empty($language_get) && in_array($language_get, array_keys($config['languages']))) ? $language_get : $config['language_default'];
        }

    
        
        $current_short_code = array_search($language_list[$current_language], $language_list);
        $current_lang_img = '<img src="'.$flag_path.$current_language .'.svg" class="translator-flag-img"></img>';
        foreach ($items as $item) {
            $options = get_post_meta($item->ID, '_translator_menu_item', true);
            if ($options) {
                // parent item for dropdown
                if($current_language == $config['language_default']){
                    if($config['original_path']  == ''){
                        $parent_menu_link = home_url().'/'.$config['original_path'];
                    }else{
                        $parent_menu_link = home_url().$config['original_path'];
                    }
                }else{
                    if($config['original_path']  == ''){
                        $parent_menu_link = home_url().'/'.$current_language.'/'.$config['original_path'];
                    }else{
                        $parent_menu_link = home_url().'/'.$current_language.$config['original_path'];
                    }
                }
                // $title = '<span>'.rtrim(explode('(', $language_list[$current_language])[0]).'</span>'; 

                $parent_title = createMenuTitle($language_list[$current_language],$current_language, $current_lang_img, $config['design_format']);

                // $item->title = $current_lang_img.$title;
                $item->title = $parent_title;
                $item->attr_title = '';
                $item->url = $parent_menu_link;
                $item->classes = array('translator_switcher translator_parent_menu_item');
                $new_items[] = $item; // save current language


                if(!$config['hide_other_langs']){
                    // create sub menu for language switcher
                    $i = 1;
                    foreach($language_list as $lang_key => $lang_lis){
                        if($lang_lis == $language_list[$current_language]){
                            continue;
                        }
                        if($lang_key == $config['language_default']){
                            if($config['original_path']  == ''){
                                $menu_link = home_url().'/'.$config['original_path'];
                            }else{
                                $menu_link = home_url().$config['original_path'];
                            }
                        }else{
                            if($config['original_path']  == ''){
                                $menu_link = home_url().'/'.$lang_key.'/'.$config['original_path'];
                            }else{
                                $menu_link = home_url().'/'.$lang_key.$config['original_path'];
                            }
                        }
                        // $title_with_img = '<img src="'.$flag_path.$lang_key.'.svg" class="translator-sub-flag-img"/><span>'.$lang_lis.'</span>';
                        $title_img = '<img src="'.$flag_path.$lang_key.'.svg" class="translator-sub-flag-img"/>';
                        $sub_menu_title = createMenuTitle($lang_lis, $lang_key, $title_img, $config['design_format']);
                        $sub_item = new stdClass();
                        $sub_item->title = $sub_menu_title;
                        $sub_item->attr_title = '';
                        $sub_item->url = $menu_link;
                        $sub_item->menu_item_parent = $item->ID;
                        $sub_item->ID = $lang_key;
                        $sub_item->db_id = $lang_key;
                        $sub_item->menu_order = $last_menu_order+$i;
                        $sub_item->post_name = $lang_lis;
                        $sub_item->type = '';
                        $sub_item->object = '';
                        $sub_item->object_id = '';
                        $sub_item->target = '';
                        $sub_item->attr_title = '';
                        $sub_item->description = '';
                        $sub_item->xfn = '';
                        $sub_item->status = '';
                        $sub_item->classes = ['notranslate'];
                        $new_items[] = $sub_item;
                        $i++;
                    }
                }                
                $found = true;
            } else {
                $item->menu_order += $offset;
                $new_items[] = $item;
            }
        }

        if ($found) {
            $config['current_language'] = $current_language;
            wp_enqueue_style('translator_switcher', plugin_dir_url(dirname(__FILE__)) . '/assets/css/frontend_style.css', array(), TRANSLATOR_VERSION);
            wp_enqueue_script('translator_switcher', plugin_dir_url(dirname(__FILE__)) . 'assets/js/frontend_script.js', array(), TRANSLATOR_VERSION);
            wp_localize_script('translator_switcher', 'translator_configs', array('vars' => array('configs' => $config)));
            // $custom_css = translatorRenderCustomCss($config);
            // wp_add_inline_style('translator_switcher', $custom_css);
        }
        return $new_items;
    }, 20);

    /* add data attr */
    function add_menu_atts( $atts, $item, $args ) {
        if (!$item instanceof WP_Post) {
            if(isset($item->classes) && is_array($item->classes) && in_array('notranslate', $item->classes)) {
                $atts['translate'] = 'no';
            }
            return $atts;
        } elseif($item->post_title == 'Automatic Translation Languages' || gettype($item->ID) == 'string'){
            $atts['data-language'] = 'language_switcher_link'; // add data attribute
            $atts['translate'] = 'no'; // add data attribute
        }
        return $atts;
    }
    add_filter( 'nav_menu_link_attributes', 'add_menu_atts', 10, 3 );

    /* translator shortcode */
    add_shortcode('automatic_translator', function () use ($language_list, $config, $languages_names) {
        wp_enqueue_style('translator_switcher', plugin_dir_url(dirname(__FILE__)) . 'assets/css/frontend_style.css', array(), TRANSLATOR_VERSION);
        wp_enqueue_script('translator_switcher', plugin_dir_url(dirname(__FILE__)) . 'assets/js/frontend_script.js', array(), TRANSLATOR_VERSION);
        wp_localize_script('translator_switcher', 'translator_configs', array('vars' => array('configs' => $config)));
        $custom_css = '';
        $design_format = $config['design_format'];
        $HTTP_TRANSLATOR_ORIGINAL_LANGUAGE = sanitize_text_field($_SERVER['HTTP_TRANSLATOR_ORIGINAL_LANGUAGE']);
        if(isset($HTTP_TRANSLATOR_ORIGINAL_LANGUAGE)  && $HTTP_TRANSLATOR_ORIGINAL_LANGUAGE != ""){
            $current_language = $HTTP_TRANSLATOR_ORIGINAL_LANGUAGE;
        }else{
            $language_get = sanitize_text_field($_GET['language']);
            $current_language = (!empty($language_get) && in_array($language_get, array_keys($config['languages']))) ? $language_get : $config['language_default'];
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View request, no action
       
        $flag_path = TRANSLATOR_PLUGIN_URL . 'assets'. DIRECTORY_SEPARATOR .'images'. DIRECTORY_SEPARATOR .'flags' . DIRECTORY_SEPARATOR;

        $display = '<div class="select" translate="no">';
        /* default language */
        $current_short_code = array_search($language_list[$current_language], $language_list);
        $current_lang_name = $language_list[$current_language];
        $current_lang_img = '<img src="'.$flag_path.$current_language .'.svg" class="translatorsc-flag-img"></img>';
        $default_title = createMenuTitle($current_lang_name, $current_short_code, $current_lang_img, $config['design_format']);
        if($current_language == $config['language_default']){
            if($config['original_path']  == ''){
                $default_link = home_url().'/'.$config['original_path'];
            }else{
                $default_link = home_url().$config['original_path'];
            }
        }else{
            if($config['original_path']  == ''){
                $default_link = home_url().'/'.$current_language.'/'.$config['original_path'];
            }else{
                $default_link = home_url().'/'.$current_language.$config['original_path'];
            }
        }
        $display .= '<div class="selectBtn" data-type="defaultLang">'.$default_title.'</div>';
        
        /* for other languages */
        if(!$config['hide_other_langs']){
            $display .= '<div class="selectDropdown">';
            foreach($language_list as $lang_key => $lang_lis){
                if($lang_lis == $language_list[$current_language]){
                    continue;
                }
                if($lang_key == $config['language_default']){
                    if($config['original_path']  == ''){
                        $ot_lang_link = home_url().'/'.$config['original_path'];
                    }else{
                        $ot_lang_link = home_url().$config['original_path'];
                    }
                }else{
                    if($config['original_path']  == ''){
                        $ot_lang_link = home_url().'/'.$lang_key.'/'.$config['original_path'];
                    }else{
                        $ot_lang_link = home_url().'/'.$lang_key.$config['original_path'];
                    }
                }
                $title_img = '<img src="'.$flag_path.$lang_key.'.svg" class="translatorsc-sub-flag-img"/>';
                $ot_lang_title = createMenuTitle($lang_lis, $lang_key, $title_img, $config['design_format']);
                
                $display .= '<a class="dropdown-item" translate="no" data-language="language_switcher_link" href="'.$ot_lang_link.'">';
                $display .= '<div class="option" data-type="'.$lang_key.'Lang">'.$ot_lang_title.'</div>';
                $display .= '</a>';
            }     
            $display .= '</div>';       
        }

        $display .= '</div>';
        return $display;
    });

    // add_action('wp_footer', function () use ($translator_options) {
    //     if (!$translator_options['translator_api_token']) {
    //         return;
    //     }

    //     echo do_shortcode('[translator]');
    // });
});


/**
 * Render custom CSS
 *
 * @param array  $options    Options
 * @param string $custom_css Custom CSS string
 *
 * @return string
 */
function translatorRenderCustomCss($options, $custom_css = '')
{
    return $custom_css;
}

/**
 * Force Relative Url
 *
 * @param string $url Url
 *
 * @return null|string|string[]
 */
function translatorForceRelativeUrl($url)
{
    return preg_replace('/^(http)?s?:?\/\/[^\/]*(\/?.*)$/i', '$2', '' . $url);
}

function createMenuTitle($lang_name, $lang_code, $img, $design_form){
    if($design_form == 'flag_lang_name'){
        $title = $img.'<span translate="no">'.rtrim(explode('(', $lang_name)[0]).'</span>';
        return $title;
    }elseif($design_form == 'flag_lang_code'){
        $title = $img.'<span translate="no">'.strtoupper($lang_code).'</span>';
        return $title;
    }elseif($design_form == 'flag'){
        $title = $img;
        return $title;
    }elseif($design_form == 'lang_name'){
        $title = '<span translate="no">'.rtrim(explode('(', $lang_name)[0]).'</span>';
        return $title;
    }elseif($design_form == 'lang_code'){
        $title = '<span translate="no">'.strtoupper($lang_code).'</span>';
        return $title;
    }else{
        $title = $img.'<span translate="no">'.rtrim(explode('(', $lang_name)[0]).'</span>';
        return $title;
    }
}
