<?php
class MPG_Moneris_Payment_Gateway {

	public $plugin_id;

	public $plugin_url;

	public $plugin_path;

	public $version;

	public $token;

	public $icon;
	
	public $text_domain;
	
	public $library;

	public $shortcode;

	public $admin;

	public $frontend;

	public $template;

	public $ajax;

	private $file;
	
	public $settings;
	
	public $dc_wp_fields;

	public $api_feedback_url;

	public function __construct($file) {

		$this->file = $file;
		$this->plugin_url = trailingslashit(plugins_url('', $plugin = $file));
		$this->plugin_path = trailingslashit(dirname($file));
		$this->token = MPG_MONERIS_PAYMENT_GATEWAY_PLUGIN_TOKEN;
		$this->text_domain = MPG_MONERIS_PAYMENT_GATEWAY_TEXT_DOMAIN;
		$this->version = MPG_MONERIS_PAYMENT_GATEWAY_PLUGIN_VERSION;
		$this->icon = MPG_MONERIS_PAYMENT_GATEWAY_PLUGIN_ICON;
		$this->plugin_id = str_replace("_", "-", MPG_MONERIS_PAYMENT_GATEWAY_TEXT_DOMAIN);
		$this->api_feedback_url = 'https://wpheka.com/wp-json/wpheka/v1/plugins/feedback';

		add_action('init', array(&$this, 'load_plugin_textdomain'), 0);
		add_action( 'plugins_loaded', array(&$this,'init_moneris_gateway_class') );
		add_filter( 'woocommerce_payment_gateways', array(&$this,'mpg_add_moneris_payment_gateway') );
		add_filter( 'plugin_action_links_' . plugin_basename($file), array($this, 'mpg_action_links' ) );
		add_action( 'wp_ajax_' . $this->text_domain .'_deactivation_popup', array( $this, 'action_save_moneris_deactivation_popup_data' ) );
	}

	/**
	 * Add Moneris Payment Gateway to woocommerce
	 * @param $methods
	 *
	 * @return array
	 */
	function mpg_add_moneris_payment_gateway( $methods ) {
		$methods[] = 'mpg_WOO_Moneris_Payment_Gateway';
		return $methods;
	}

	/**
	 * Add Plugin action links
	 * @param $links
	 *
	 * @return array
	 */
	function mpg_action_links($links) {
		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=moneris' ) . '">' . __( 'Settings', $this->text_domain ) . '</a>',
		);
		return array_merge( $plugin_links, $links );
	}

	/**
	 * Moneris Gateway Class
	 */
	function init_moneris_gateway_class() {
		if(class_exists('WC_Payment_Gateway')) {
			$this->load_class('woo');
			$this->admin = new mpg_WOO_Moneris_Payment_Gateway();
		}
	}

	/**
	 * Load Localisation files.
	 *
	 * Note: the first-loaded translation file overrides any following ones if the same translation is present
	 *
	 * @access public
	 * @return void
	 */
	public function load_plugin_textdomain() {
		$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
		$locale = apply_filters( 'plugin_locale', $locale, $this->token );

		unload_textdomain( $this->text_domain );
		load_textdomain( $this->text_domain, WP_LANG_DIR . '/wc-moneris-payment-gateway/'.$this->text_domain.'-' . $locale . '.mo' );
		load_plugin_textdomain( $this->text_domain, false, plugin_basename( dirname( $this->file ) ) . '/languages' );
	}


	/**
	 * Plugin class loader
	 * @param string $class_name
	 */
	public function load_class($class_name = '') {
		if ('' != $class_name && '' != $this->token) {
			require_once ('class-' . esc_attr($this->token) . '-' . esc_attr($class_name) . '.php');
		} // End If Statement
	}// End load_class()

    /**
     * AJAX Action to save deactivation popup data
     *
     * @return void
     */
    public function action_save_moneris_deactivation_popup_data() {
        
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], $this->text_domain . 'deactivate_feedback_nonce' ) ) {
            wp_send_json_error();
        }

        $feedback_url = $this->api_feedback_url;

        $deactivation_reason = '';
        $deactivation_domain = '';
        $deactivation_license_key = '';

        if ( ! empty( $_POST['deactivation_reason'] ) ) {
            $deactivation_reason = $_POST['deactivation_reason'];

            if( $deactivation_reason == 'Other') {
                if ( ! empty( $_POST['deactivation_reason_other'] ) ) {
                    $deactivation_reason = $_POST['deactivation_reason_other'];
                }
            }
        }   

        if ( ! empty( $_POST['deactivation_domain'] ) ) {
            $deactivation_domain = $_POST['deactivation_domain'];
        }

        if ( ! empty( $_POST['deactivation_license_key'] ) ) {
            $deactivation_license_key = $_POST['deactivation_license_key'];
        }

        if ( ! empty( $_POST['email'] ) ) {
            $email = $_POST['email'];
        }   

        wp_remote_post($feedback_url, [
            'timeout' => 30,
            'body' => [
                'plugin' => MPG_MONERIS_PAYMENT_GATEWAY_PLUGIN_NAME,
                'deactivation_reason' => $deactivation_reason,
                'deactivation_domain' => $deactivation_domain,
                'deactivation_license_key' => $deactivation_license_key,
                'email' => $email
            ],
        ] );

        wp_send_json_success();

        wp_die();
    }

}
