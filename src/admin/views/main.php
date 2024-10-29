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

?>
<div class="translator-section">
    <h2 class="translator-main-heading">Automatic Translation</h2>
    <div class="translator-form-body">
        <div class="row">
            <div class="form-items">
                <?php if(isset($options['translator_api_token']) && $options['translator_api_token'] != ''){ ?>
                    <div class="row">
                        <div class="col-md-9">
                            <p class="translator-p mt-2"><strong>Note: </strong>You can add Language Switcher by two ways:
                                <ol>
                                    <li>To add Language Switcher in Menu, Go to Menu and add "Automatic Translation Languages" menu item.</li>
                                    <li>Add <strong>[automatic_translator]</strong> shortcode where you want to add language switcher.</li>
                                </ol>
                            </p>
                        </div>
                        <div class="col-md-3">
                        <?php if(!$show_default){ ?>    
                            <button class="btn btn-primary float-end px-4 mt-2" id="sitemap_xml"><i class="fas fa-sitemap"></i> Generate Sitemap</button>
                            <?php
                            $cr_url = home_url(add_query_arg($wp->query_vars, $wp->request));
                            ?>
                            '<a href="<?php esc_html_e($cr_url);?>&layout=cacheurls"><button class="btn btn-primary float-end px-4 mt-2" id="sitemap_xml"><i class="fas fa-arrow-alt-circle-right"></i> Cache urls</button></a>
                        <?php }?>
                        </div>
                    </div>
                <?php } ?>
                <!-- Alert messages -->
                <div class="row text-center" id="alert-div">
                <?php if($show_default){ ?>
                    <div class="alert alert-danger alert-dismissible fade show translator-alert-box" role="alert">
                        <?php esc_html_e($default_alert_msg); ?>
                    </div>
                <?php } ?>
                <?php if($show_alert){ ?>
                    <div class="alert alert-<?php esc_html_e($alert_type); ?> alert-dismissible fade show translator-alert-box" role="alert" id="alert_messages">
                        <?php esc_html_e($alert_msg); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } ?>
                </div>
                <!-- End alert messages -->
                <form id="setting-form" class="form-validate form-horizontal" action="" method="post">
                    <?php wp_nonce_field('translator_settings', 'main_settings'); ?>
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <label class="form-label"><?php esc_html_e('Translator API key', 'translator'); ?></label>
                            <input class="form-control" type="text" name="translator_options[token_key]" placeholder="Translator API Key" required id="token_key" value="<?php esc_html_e(isset($options['translator_api_token']) ? esc_html_e($options['translator_api_token']) : '') ?>">
                        </div>
                        <div class="col-md-6">
                            <button id="token_save" type="button" class="btn btn-primary mt-btn"><i class="fa fa-key"></i> <?php esc_html_e('Save Key', 'translator'); ?></button>
                        </div>
                    </div>
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <label class="form-label"><?php esc_html_e('Website default language', 'translator'); ?></label>
                            <select class="form-select" name="translator_options[default_lang]" required>
                                <?php 
                                if(count($site_languages) > 0){
                                    foreach($site_languages as $key => $site_language){
                                        if(isset($options['language_default'])){
                                            if($key == $options['language_default']){ ?>
                                                <option value='<?php esc_html_e($key);?>' selected><?php esc_html_e($site_language['name']);?></option>
                                            <?php }else{ ?>
                                                 <option value='<?php esc_html_e($key);?>'><?php esc_html_e($site_language['name']);?></option>
                                            <?php }   
                                        }
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?php esc_html_e('Website Translate Into', 'translator'); ?></label>
                            <select class="form-select" name="translator_options[translate_lang][]" id="multiple_lang" multiple>
                                <?php 
                                if(count($site_languages) > 0){
                                    foreach($site_languages as $key => $site_language){
                                        if(in_array($key, $options['languages_enabled'])){ ?>
                                            <option value='<?php esc_html_e($key);?>' selected><?php esc_html_e($site_language['name']);?></option>
                                         <?php }else{ ?>
                                            <option value='<?php esc_html_e($key);?>'><?php esc_html_e($site_language['name']);?></option>
                                        <?php }
                                    }
                                }
                                ?>
                            </select>
                            <?php 
                                if($invalid_translation){ ?>
                                    <span class="text-danger" id="t_err_msg"><?php esc_html_e($translation_msg);?></span>
                               <?php }
                            ?>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <hr/>
                        <div class="col-md-6">
                            <label class="form-label"><?php esc_html_e('Languages list display as:', 'translator'); ?></label>
                            <select class="form-select" name="translator_options[design_format]">
                                <?php 
                                    if(isset($design_formats) && count($design_formats) > 0){
                                        foreach($design_formats as $df => $design_format){
                                            if(isset($options['design_format'])){
                                                if($options['design_format'] == $df){ ?>
                                                    <option value="<?php esc_html_e($df);?>" selected><?php esc_html_e($design_format);?></option>
                                                <?php }else{ ?>
                                                     <option value="<?php esc_html_e($df);?>"><?php esc_html_e($design_format);?></option>
                                                <?php }
                                            }else{ ?>
                                                 <option value="<?php esc_html_e($df);?>"><?php esc_html_e($design_format);?></option>
                                            <?php }
                                        }
                                    }
                                ?>
                            </select>
                        </div>
                    </div>

                    <?php if(isset($options['total_limit']) && isset($options['translator_api_token']) && $options['translator_api_token'] != ''){ ?>
                        <div class="row mt-4">
                            <div class="card">
                                <div class="card-body">
                                    <?php if($options['total_limit'] === 'unlimited'){ ?>
                                        <h4 class="card-title">Overwrite Text </h4>
                                        <hr>
                                        <p class="translator-p">To overwrite website content <a href="<?php esc_html_e($options['overwrite_url']); ?>" target="_blank">click here</a></p>
                                    <?php 
                                        }else{ 
                                            $t_limit = (int)$options['total_limit'];
                                            $u_limit = (int)$options['used_limit'];
                                            if($u_limit >= $t_limit && $u_limit != 0){ ?>
                                                <p class="card-text text-danger">Your plan&#39;s Maximum Characters limit exceeded, to increase character limit please upgrade on <a href="https://login.automatic-translation.online/" target="_blank">https://login.automatic-translation.online/</a></p>
                                            <?php }else{
                                                $limit_round_off = round($u_limit/$t_limit * 100, 2);
                                            ?>    
                                                <h4 class="card-text"><?php echo(number_format($u_limit));?><small class="translator-small"><strong><?php echo($limit_round_off);?> % used</strong></small></h4>
                                                <p class="card-text translator-p">Out of max. <strong><?php echo(number_format($t_limit));?></strong> characters</p>
                                            <?php if($u_limit != '' && $t_limit != ''){ ?>
                                                    <div class="progress"><div class="progress-bar" style="width:<?php echo($limit_round_off);?>%"></div></div>
                                                <?php }
                                            }
                                        } 
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php } ?>                   


                    <div class="row mt-4 text-center">
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary"><?php esc_html_e('Save Setting', 'translator'); ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="loader-overlay" id="loader-div" style="display:none;">
        <div id="loader"></div>
    </div>
</div>