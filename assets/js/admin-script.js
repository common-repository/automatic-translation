jQuery("#multiple_lang").select2({
    placeholder: "Select languages to translate website content.",
    allowClear: false,
});


jQuery('#token_save').click(function(){
    var tkn = jQuery('#token_key').val();
    if(tkn == ''){
        if(jQuery('#translator_key_err').length == 0){
            jQuery('<p id="translator_key_err" class="text-danger">Add Translator API key to activate the translation.</p>').insertAfter('#token_key');
        }
    }else{
        jQuery('#translator_key_err').remove();
        var data ={
            action: 'save_api_key',
            tokenkey: tkn
        };
        jQuery.ajax({
            type : "POST",
            url: ajaxurl,
            data: data,
            success: function(response) {
                if(response == 'success'){
                    jQuery('<p class="text-success" id="translator_key_success">API key saved successfully!</p>').insertAfter("#token_key");
                    jQuery('#translator_key_success').delay(3000).fadeOut();
                    window.setTimeout( function() {
                      window.location.reload();
                    }, 3000);
                }else{
                    jQuery('<p class="text-danger" id="translator_key_err">'+response+'</p>').insertAfter("#token_key");
                }
            }
        });
    }
});

jQuery('#token_key').keyup(function(){
    if(jQuery(this).val() == ''){
        if(jQuery('#translator_key_err').length == 0){
            jQuery('<p id="translator_key_err" class="text-danger">Add Translator API key to activate the translation.</p>').insertAfter('#token_key');
        }
    }else{
        jQuery('#translator_key_err').remove();
    }
});

jQuery('#alert_messages').delay(4000).fadeOut();
jQuery('#t_err_msg').delay(4000).fadeOut();

jQuery('#sitemap_xml').click(function(){
    var siteurl = translator_vars.siteurl;
    jQuery('#loader-div').show();
    var data ={
        action: 'generate_sitemap'
    };
    jQuery.ajax({
        type : "GET",
        url: ajaxurl,
        data: data,
        success: function(response) {
            if(response.message == 'success'){
                jQuery('#loader-div').hide();
                jQuery('#alert-div').html('<div class="alert alert-success alert-dismissible fade show translator-alert-box" id="translator_alert_success" role="alert">Sitemap XML file generated successfully. <a href="'+siteurl+'/sitemap.xml" target="_blank">Click here</a> to check generated xml file.</div>');
                jQuery('#translator_alert_success').delay(5000).fadeOut();
            }else{ 
                jQuery('#loader-div').hide();               
                jQuery('#alert-div').html('<div class="alert alert-danger alert-dismissible fade show translator-alert-box" id="translator_alert_err" role="alert">Something went wrong.</div>');
                jQuery('#translator_alert_err').delay(3000).fadeOut();
            }
        }
    });
});