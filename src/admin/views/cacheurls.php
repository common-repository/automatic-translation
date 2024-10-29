<?php
defined('ABSPATH') || die('');

include_once(TRANSLATOR_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'helper.php');
$options = translatorGetOptions();
$languages_content = file_get_contents(TRANSLATOR_PLUGIN_PATH . '/assets/languages.json');
$site_languages = json_decode($languages_content, true);
$design_formats = isset($this->design_formats) ? $this->design_formats : [];

$show_default = false;
if(isset($options['translator_api_token']) && $options['translator_api_token'] != ''){
    $check_validation = \Translator\WordPress\Admin\helper::checkValidation($options['translator_api_token']);
    if($check_validation !== true){
        $show_default = true;
        $default_alert_msg = $check_validation;
    }
}
global $wpdb;

$translator_urls_table= $wpdb->prefix."translator_urls"; 

$urlls = $wpdb->get_results("select id,source from `$translator_urls_table` group by source");
wp_enqueue_script("jquery-jsdatatable",TRANSLATOR_PLUGIN_URL."assets/js/jquery.dataTables.min.js",array());
wp_enqueue_style("jquery-cssdatatable",TRANSLATOR_PLUGIN_URL."assets/css/jquery.dataTables.min.css",array());

?>
<style type="text/css">
    #urlTable_wrapper{
       margin-top: 22px;
    }
</style>
<div class="translator-section">
    <h2 class="translator-main-heading">Automatic Translation</h2>
    <div class="translator-form-body">
        <div class="row">
            <div class="form-items">
                <?php if(isset($options['translator_api_token']) && $options['translator_api_token'] != ''){ ?>
                    <div class="row">
                        <div class="col-md-10">
                       
                        </div>
                       
                        <div class="col-md-2">
                        <?php $back_url = home_url()."/wp-admin/admin.php?page=automatic_translation"; ?>
                            <a href="<?php esc_html_e( $back_url );?>"><button class="btn btn-primary float-end px-4 mt-2"><i class="fas fa-back"></i> Back </button></a>
                        </div>
                    </div>
                <?php } ?>
                <!-- Alert messages -->
                <div class="row text-center" id="alert-div">
                <?php if($sucess_cache_message != ""){ ?>
                    <div class="alert alert-success alert-dismissible fade show translator-alert-box" role="alert">
                        <?php  esc_html_e($sucess_cache_message); ?>
                    </div>
                <?php } ?>
                </div>
                <table class="table table-striped" id="urlTable">
                    <thead>
                        <tr>
                            <th width="70%" class="nowrap left">
                                Urls
                            </th>
                            
                            <th width="30%" class="nowrap left">
                               Action
                            </th>
                            
                        </tr>
                    </thead>
                    
                    <tbody>
                        <?php foreach ($urlls as $i => $item) : ?>
                        <tr class="row<?php esc_html_e($i) % 2; ?>">
                            <td class="left">
                                <?php

                                if (strpos($item->source, 'http') !== false) {
                                      esc_html_e($item->source);
                                }else{
                                      esc_html_e(home_url().$item->source);
                                }

                                ?>
                            </td>
                            
                            <td class="small break-word hidden-phone hidden-tablet">
                                <?php 

                                 $cr_url = home_url(add_query_arg($wp->query_vars, $wp->request))."&cache_url_id=".$item->id;


                                ?>
                                <a href="<?php  esc_html_e($cr_url);?>">Clear cache</a>
                            </td>
                        </tr>

                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="loader-overlay" id="loader-div" style="display:none;">
        <div id="loader"></div>
    </div>
</div>
<script type="text/javascript">
    jQuery(document).ready( function () {
        jQuery('#urlTable').DataTable();
    } );
</script>