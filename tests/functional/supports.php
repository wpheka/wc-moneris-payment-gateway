<?php
/**
 * What the free Moneris gateway tells WooCommerce it supports.
 *
 * The gateway never charged WooCommerce Pre-Orders on release, so it must not claim
 * `pre-orders`: the Pre-Orders extension would then offer "charge upon release" orders that
 * the gateway leaves unpaid. Card payments and refunds stay as they are.
 *
 * Run with WP-CLI from the WordPress root (WooCommerce active; works with the free plugin
 * active or not, and with Moneris Pro active):
 *   wp eval-file wp-content/plugins/wc-moneris-payment-gateway/tests/functional/supports.php
 *
 * Prints PASS or FAIL per case and exits with an error when any case fails.
 *
 * @package WPHEKA_Gateway_Moneris
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Payment_Gateway_CC' ) ) {
	WP_CLI::error( 'WooCommerce must be active.' );
}

// The free plugin skips loading when Moneris Pro is active: load its gateway class directly.
$wpheka_moneris_test_dir = dirname( __DIR__, 2 );
if ( ! defined( 'WPHEKA_MONERIS_PLUGIN_ICON' ) ) {
	define( 'WPHEKA_MONERIS_PLUGIN_ICON', plugins_url( '/assets/images/logo.png', $wpheka_moneris_test_dir . '/wc-moneris-payment-gateway.php' ) );
}
if ( ! class_exists( 'WPHEKA_Gateway_Moneris' ) ) {
	require_once $wpheka_moneris_test_dir . '/includes/class-wpheka-gateway-moneris.php';
}

$wpheka_moneris_results = array();
$wpheka_moneris_check   = static function ( $name, $ok, $detail = '' ) use ( &$wpheka_moneris_results ) {
	$wpheka_moneris_results[] = $ok;
	WP_CLI::log( ( $ok ? 'PASS  ' : 'FAIL  ' ) . $name . ( $ok || '' === $detail ? '' : ' -- ' . $detail ) );
};

$wpheka_moneris_gateway = new WPHEKA_Gateway_Moneris();

$wpheka_moneris_check( 'the gateway does not claim WooCommerce Pre-Orders support', ! $wpheka_moneris_gateway->supports( 'pre-orders' ), wp_json_encode( $wpheka_moneris_gateway->supports ) );
$wpheka_moneris_check( 'it still supports card payments', $wpheka_moneris_gateway->supports( 'products' ) && $wpheka_moneris_gateway->supports( 'default_credit_card_form' ) );
$wpheka_moneris_check( 'it still supports refunds', $wpheka_moneris_gateway->supports( 'refunds' ) );

// The block checkout gets its feature list from the same property.
$wpheka_moneris_block_features = array_values( array_filter( $wpheka_moneris_gateway->supports, array( $wpheka_moneris_gateway, 'supports' ) ) );
$wpheka_moneris_check( 'the block checkout feature list has no pre-orders', ! in_array( 'pre-orders', $wpheka_moneris_block_features, true ), wp_json_encode( $wpheka_moneris_block_features ) );

$wpheka_moneris_failed = count( array_filter( $wpheka_moneris_results, 'wpheka_moneris_test_is_failure' ) );

/**
 * A failed case.
 *
 * @param bool $ok Case result.
 * @return bool
 */
function wpheka_moneris_test_is_failure( $ok ) {
	return ! $ok;
}

WP_CLI::log( sprintf( '%d passed, %d failed', count( $wpheka_moneris_results ) - $wpheka_moneris_failed, $wpheka_moneris_failed ) );
if ( $wpheka_moneris_failed ) {
	WP_CLI::error( 'Gateway supports cases failed.' );
}
