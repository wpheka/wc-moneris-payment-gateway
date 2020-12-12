<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * WC_Admin_Pointers Class.
 */
class MPG_Moneris_Payment_Gateway_Deactivation_Popup {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'setup_popup_scripts' ) );
    }
    
    public function setup_popup_scripts() {
        global $MPG_Moneris_Payment_Gateway;
        $plugin_token = MPG_MONERIS_PAYMENT_GATEWAY_TEXT_DOMAIN;

        if ( ! $this->is_plugins_screen() ) {
            return;
        }

        wp_register_style( $plugin_token . 'deactivation_popup_css', $MPG_Moneris_Payment_Gateway->plugin_url . 'assets/admin/css/jquery.modal.min.css');
        wp_enqueue_style( $plugin_token . 'deactivation_popup_css' );
        wp_register_script( $plugin_token . 'deactivation_popup_js', $MPG_Moneris_Payment_Gateway->plugin_url . 'assets/admin/js/jquery.modal.min.js', array('jquery'), '0.9.1', true);
        wp_enqueue_script( $plugin_token . 'deactivation_popup_js');

        wp_register_script( $plugin_token . 'deactivation_loading_overlay_js', $MPG_Moneris_Payment_Gateway->plugin_url . 'assets/admin/js/loadingoverlay.min.js',array('jquery'), '2.1.7', true);
        wp_enqueue_script( $plugin_token . 'deactivation_loading_overlay_js');        

        add_action( 'admin_footer-'. $GLOBALS['hook_suffix'], array( $this,'wpheka_admin_deactivation_popup_footer'), PHP_INT_MAX );
    }
    
    public function wpheka_admin_deactivation_popup_footer() {
        global $MPG_Moneris_Payment_Gateway;
        $license_key = 'Free';
        $license_domain = get_site_url();
        $license_email = get_option( 'admin_email' );

        $plugin_token = MPG_MONERIS_PAYMENT_GATEWAY_TEXT_DOMAIN;
        $plugin_id = $MPG_Moneris_Payment_Gateway->plugin_id;

        $form_id = $plugin_token . '_form';
    ?>
<style>
    form#<?php echo $form_id; ?> h1{
        color: #c32d1b;
    }
    form#<?php echo $form_id; ?> {
        max-width: 505px;
    }
    form#<?php echo $form_id; ?> label{
        display: inline-block;
        padding-bottom: 10px;
    }
    form#<?php echo $form_id; ?> input[type="text"]{
        margin-bottom: 10px;
        border-radius: 3px;
        border: 1px solid #c32d1b;
        height: 30px;
        width: 100%;
    }
    form#<?php echo $form_id; ?> input[type="submit"]{
        background: #c32d1b;
        border: 0;
        font-size: 16px;
        line-height: 34px;
        padding: 0 30px;
        color: #fff;
        border-radius: 3px;
        cursor: pointer;
    }
</style>    
<form id="<?php echo $form_id; ?>" method="post" action="" class="modal">
    <?php
    wp_nonce_field( $plugin_token . 'deactivate_feedback_nonce' );
    ?>    
    <h1>Quick Feedback</h1>
    <p>If you have a moment, please let us know why you are deactivating this plugin:</p>
    <hr>

    <label for="deactivation_reason"><b>Deactivation Reason</b></label><br>
    <input type="radio" name="deactivation_reason" value="I couldn't understand how to make it work" required> I couldn't understand how to make it work.<br>
    <input type="radio" name="deactivation_reason" value="I found a better plugin"> I found a better plugin.<br>
    <input type="radio" name="deactivation_reason" value="The plugin is great, but I need specific feature that you don't support"> The plugin is great, but I need specific feature that you don't support.<br>
    <input type="radio" name="deactivation_reason" value="The plugin is not working"> The plugin is not working.<br>
    <input type="radio" name="deactivation_reason" value="It's not what I was looking for"> It's not what I was looking for.<br>
    <input type="radio" name="deactivation_reason" value="The plugin didn't work as expected"> The plugin didn't work as expected.<br>
    <input type="radio" name="deactivation_reason" value="It is a temporary deactivation"> It's a temporary deactivation<br>
    <input type="radio" name="deactivation_reason" value="Other"> Other<br><br>

    <input type="text" name="deactivation_reason_other" value="" placeholder="Kindly tell us the reason so that we can improve">
    
    <input type="hidden" name="deactivation_domain" value="<?php echo $license_domain; ?>">
    
    <input type="hidden" name="deactivation_license_key" value="<?php echo $license_key; ?>">
    
    <input type="hidden" name="email" value="<?php echo $license_email; ?>">

    <input type="hidden" name="action" value="<?php echo $plugin_token; ?>_deactivation_popup" />
    
    <input type="submit" value="Submit & Deactivate">
    <a id="<?php echo $plugin_id; ?>-skip-deactivate" href="javascript:void()" style="float: right;">Skip & Deactivate</a>
</form>

<script type="text/javascript">
<?php
$deactivation_reason_other_text_box = '$(\'#'.$form_id.' input[name="deactivation_reason_other"]\')';
$form_radio_var = $form_id.'radio';
$form_radio = '$(\'#'.$form_id.' input[type="radio"]\')';
$js_code = '/* <![CDATA[ */
( function($) {
'.$deactivation_reason_other_text_box.'.hide();
var '.$form_radio_var.' = '.$form_radio.';
'.$form_radio_var.'.on("change", function (event) {
    event.preventDefault();
    var radio_val = $( this ).val();

    if ( radio_val == "Other" ) {
        '.$deactivation_reason_other_text_box.'.show();
    } else {
        '.$deactivation_reason_other_text_box.'.hide();
    }
    return false;
});
} )(jQuery);
/* ]]> */';
echo $js_code;?>
</script>
<script type="text/javascript">
    /* <![CDATA[ */
    ( function($) {

        var deactivateLink = $('#the-list').find('[data-slug="<?php echo $plugin_id; ?>"] span.deactivate a');

        if(deactivateLink.length){
            $('#<?php echo $plugin_id; ?>-skip-deactivate').attr('href',deactivateLink.attr('href'));

            deactivateLink.on('click', function (event) {
                event.preventDefault();

                if(jQuery().modal) {
                    $('form#<?php echo $form_id; ?>').modal();
                }
            });        

            $('#<?php echo $form_id; ?>').submit(function(e) {
                e.preventDefault(); // don't submit multiple times

                formData = $(this).serialize();

                if(jQuery().LoadingOverlay) {
                    $('#<?php echo $form_id; ?>').LoadingOverlay("show");
                }

                $.post('<?php echo admin_url( 'admin-ajax.php' ); ?>', formData, function(response) {
                    if(response) {
                        $.modal.close();
                        if(jQuery().LoadingOverlay) {
                            $('#<?php echo $form_id; ?>').LoadingOverlay("hide", true);
                        }                    
                        location.href = deactivateLink.attr('href');
                    }
                });

            });
        }
    
    } )(jQuery);
    /* ]]> */
</script>
    <?php
    }

    /**
     * @since 2.3.0
     * @access private
     */
    private function is_plugins_screen() {
        return in_array( get_current_screen()->id, [ 'plugins', 'plugins-network' ] );
    }

}

new MPG_Moneris_Payment_Gateway_Deactivation_Popup();