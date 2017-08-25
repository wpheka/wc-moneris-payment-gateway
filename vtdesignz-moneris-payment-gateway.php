<?php
/*
Plugin Name: Moneris - WooCommerce Gateway
Plugin URI: http://vtdesignz.com/
Description: Extends WooCommerce by Adding the Moneris Gateway.
Version: 1.0
Author: Tonmoy Malik
Author URI: http://vtdesignz.com/
*/
add_action( 'plugins_loaded', 'vtd_moneris_gateway_init', 0 );
function vtd_moneris_gateway_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;	
	include_once( 'api/mpgClasses.php' );
	include_once( 'woocommerce-moneris-gateway.php' );
	add_filter( 'woocommerce_payment_gateways', 'vtd_add_moneris_gateway' );	
	function vtd_add_moneris_gateway( $methods ) {
		$methods[] = 'VTD_MONERIS_GATEWAY';
		return $methods;
	}
}
// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'vtd_moneris_gateway_action_links' );
function vtd_moneris_gateway_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'vtd-moneris-gateway' ) . '</a>',
	);
	// Merge our new link with the default ones
	return array_merge( $plugin_links, $links );	
}