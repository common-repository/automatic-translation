<?php

namespace Translator\WordPress\Admin;

defined('ABSPATH') || die('');


/**
 * Class Helper
 */
class helper
{
    public static function checkValidation($key){
        $domain = get_site_url();

        $args = array(
                'body'        => 'domain='.$domain.'&site_key='.$key,
                'timeout'     => '5',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => array('Content-Type: application/x-www-form-urlencoded'),
                'cookies'     => array(),
            );

    
        $response = wp_remote_post( 'https://login.automatic-translation.online/api/validation', $args );

        if(!$response instanceof \WP_Error && isset($response["body"])){


            $res = json_decode($response["body"]);

            if($res->status == 401){
                return $res->errors;
            }else{
                return true;
            }
        }else{
            return false;
        }

        
    }

    public static function generateXml(){
        $languages = [];
        $source_languages = '';
        $root_url = home_url().'/';
        $contents = file_get_contents($root_url);
        $options = translatorGetOptions();
        if(isset($options['language_default'])){
            $languages["default_language"] = $options['language_default'];
        }
        if(isset($options['languages_enabled'])){
             $options['languages_enabled'] = array('de','es');
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

            if(!$response instanceof \WP_Error && isset($response["body"])){


                    $res = json_decode($response["body"]);
                    if(isset($res->data) && !empty($res->data)){
                        header('Content-type: text/xml'); 
                        $domtree = new \DOMDocument('1.0', 'UTF-8');
                        $xmlRoot = $domtree->createElement("urlset");
                        $xmlRoot->setAttribute("xmlns","https://www.sitemaps.org/schemas/sitemap/0.9");
                        $xmlRoot->setAttribute("xmlns:xhtml","https://www.w3.org/1999/xhtml");

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
                        return true;
                    }else{
                        return false;
                    }
            }else{
                return false;
            }
        }else{
            return false;
        } 
    }

}
