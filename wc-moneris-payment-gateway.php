<?php
/**
 * Plugin Name: WC Moneris Payment Gateway
 * Plugin URI: https://wpheka.com/product/wc-moneris-payment-gateway
 * Description: <code><strong>WooCommerce Moneris Payment Gateway</strong></code>
 * Version: 1.7
 * Author: WPHEKA
 * Author URI: https://wpheka.com
 * Requires at least: 4.9
 * Tested up to: 5.4
 * Text Domain: wc_moneris_payment_gateway
 * Domain Path: /languages/
 * License: GPLv3 or later
 * WC requires at least: 3.0
 * WC tested up to: 4.0.1
 */

if ( ! class_exists( 'MPG_Dependencies' ) )
	require_once trailingslashit(dirname(__FILE__)).'includes/class-mpg-dependencies.php';

require_once trailingslashit(dirname(__FILE__)).'config.php';
if(!defined('ABSPATH')) exit; // Exit if accessed directly
if(!defined('MPG_MONERIS_PAYMENT_GATEWAY_PLUGIN_TOKEN')) exit;
if(!defined('MPG_MONERIS_PAYMENT_GATEWAY_TEXT_DOMAIN')) exit;

if(!class_exists('MPG_Moneris_Payment_Gateway') && MPG_Dependencies::is_woocommerce_active()) {
	require_once( trailingslashit(dirname(__FILE__)).'classes/class-mpg-moneris-payment-gateway-init.php' );
	global $MPG_Moneris_Payment_Gateway;
	$MPG_Moneris_Payment_Gateway = new MPG_Moneris_Payment_Gateway_Init( __FILE__ );
	$GLOBALS['MPG_Moneris_Payment_Gateway'] = $MPG_Moneris_Payment_Gateway;
	require_once( trailingslashit(dirname(__FILE__)).'classes/class-mpg-moneris-payment-gateway-deactivation-popup.php' );
}else {
	add_action('admin_notices', 'mpg_admin_notice');
	if (!function_exists('mpg_admin_notice')) {
		function mpg_admin_notice() {
			?>
			<div class="error">
				<p><?php _e('Moneris Payment Gateway plugin requires <a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a> plugin to be active!', MPG_MONERIS_PAYMENT_GATEWAY_TEXT_DOMAIN); ?></p>
			</div>
			<?php
		}
	}
}