<?php
class MPG_Moneris_Payment_Gateway_Init {

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

	public function __construct($file) {

		$this->file = $file;
		$this->plugin_url = trailingslashit(plugins_url('', $plugin = $file));
		$this->plugin_path = trailingslashit(dirname($file));
		$this->token = MPG_MONERIS_PAYMENT_GATEWAY_PLUGIN_TOKEN;
		$this->text_domain = MPG_MONERIS_PAYMENT_GATEWAY_TEXT_DOMAIN;
		$this->version = MPG_MONERIS_PAYMENT_GATEWAY_PLUGIN_VERSION;
		$this->icon = MPG_MONERIS_PAYMENT_GATEWAY_PLUGIN_ICON;

		add_action('init', array(&$this, 'init'), 0);
		add_action( 'plugins_loaded', array(&$this,'init_moneris_gateway_class') );
		add_filter( 'woocommerce_payment_gateways', array(&$this,'mpg_add_moneris_payment_gateway') );
		add_filter( 'plugin_action_links_' . plugin_basename($file), array($this, 'mpg_action_links' ) );
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
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', $this->text_domain ) . '</a>',
		);
		return array_merge( $plugin_links, $links );
	}
	/**
	 * initilize plugin on WP init
	 */
	function init() {
		
		// Init Text Domain
		$this->load_plugin_textdomain();

		// Init ajax
		if(defined('DOING_AJAX')) {
			$this->load_class('ajax');
			$this->ajax = new  MPG_Moneris_Payment_Gateway_Ajax();
		}

		if (!is_admin() || defined('DOING_AJAX')) {
			$this->load_class('frontend');
			$this->frontend = new MPG_Moneris_Payment_Gateway_Frontend();
		}

	}

	/**
	 * Moneris Gateway Class
	 */
	function init_moneris_gateway_class() {
		$this->load_class('woo');
		$this->admin = new mpg_WOO_Moneris_Payment_Gateway();
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
		$locale = apply_filters( 'plugin_locale', get_locale(), $this->token );

		load_textdomain( $this->text_domain, WP_LANG_DIR . "/mpg-Moneris-Payment-Gateway/mpg-Moneris-Payment-Gateway-$locale.mo" );
		load_textdomain( $this->text_domain, $this->plugin_path . "/languages/mpg-Moneris-Payment-Gateway-$locale.mo" );
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
	
	/** Cache Helpers *********************************************************/

	/**
	 * Sets a constant preventing some caching plugins from caching a page. Used on dynamic pages
	 *
	 * @access public
	 * @return void
	 */
	function nocache() {
		if (!defined('DONOTCACHEPAGE'))
			define("DONOTCACHEPAGE", "true");
		// WP Super Cache constant
	}

}
