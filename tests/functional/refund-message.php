<?php
/**
 * The message a store owner sees when a refund is asked for on the day of the sale.
 *
 * The gateway sends refunds from the day after the sale. On the day itself it refuses, and the
 * message must give the reason and what to do instead, without reading like a locked feature
 * (wordpress.org guideline 5): no "upgrade", at most one short mention of Moneris Pro.
 * The refusal itself must not change.
 *
 * Run with WP-CLI from the WordPress root (WooCommerce active; works with the free plugin
 * active or not, and with Moneris Pro active):
 *   wp eval-file wp-content/plugins/wc-moneris-payment-gateway/tests/functional/refund-message.php
 *
 * Nothing is sent to Moneris: the refusal happens before any request. The order it creates is
 * deleted at the end. Prints PASS or FAIL per case and exits with an error when any case fails.
 *
 * @package WPHEKA_Gateway_Moneris
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Payment_Gateway_CC' ) ) {
	WP_CLI::error( 'WooCommerce must be active.' );
}

// The free plugin skips loading when Moneris Pro is active: load its classes directly.
$wpheka_moneris_test_dir = dirname( __DIR__, 2 );
if ( ! defined( 'WPHEKA_MONERIS_PLUGIN_ICON' ) ) {
	define( 'WPHEKA_MONERIS_PLUGIN_ICON', plugins_url( '/assets/images/logo.png', $wpheka_moneris_test_dir . '/wc-moneris-payment-gateway.php' ) );
}
if ( ! defined( 'WPHEKA_MONERIS_VERSION' ) ) {
	define( 'WPHEKA_MONERIS_VERSION', get_file_data( $wpheka_moneris_test_dir . '/wc-moneris-payment-gateway.php', array( 'Version' => 'Version' ) )['Version'] );
}
if ( ! class_exists( 'WPHEKA_Moneris_Logger' ) ) {
	require_once $wpheka_moneris_test_dir . '/includes/class-wpheka-moneris-logger.php';
}
if ( ! class_exists( 'WPHEKA_Gateway_Moneris' ) ) {
	require_once $wpheka_moneris_test_dir . '/includes/class-wpheka-gateway-moneris.php';
}

$wpheka_moneris_results = array();
$wpheka_moneris_check   = static function ( $name, $ok, $detail = '' ) use ( &$wpheka_moneris_results ) {
	$wpheka_moneris_results[] = $ok;
	WP_CLI::log( ( $ok ? 'PASS  ' : 'FAIL  ' ) . $name . ( $ok || '' === $detail ? '' : ' -- ' . $detail ) );
};

// An order paid a moment ago with this gateway.
$wpheka_moneris_order = wc_create_order();
$wpheka_moneris_order->set_payment_method( 'moneris' );
$wpheka_moneris_order->set_total( '25.00' );
$wpheka_moneris_order->set_date_paid( time() );
$wpheka_moneris_order->set_status( 'processing' );
$wpheka_moneris_order->save();

$wpheka_moneris_gateway = new WPHEKA_Gateway_Moneris();
$wpheka_moneris_result  = $wpheka_moneris_gateway->process_refund( $wpheka_moneris_order->get_id(), 10 );
$wpheka_moneris_message = is_wp_error( $wpheka_moneris_result ) ? $wpheka_moneris_result->get_error_message() : '';

$wpheka_moneris_check( 'a refund on the day of the sale is still refused', is_wp_error( $wpheka_moneris_result ), wp_json_encode( $wpheka_moneris_result ) );
$wpheka_moneris_check( 'the message says to refund from tomorrow', false !== stripos( $wpheka_moneris_message, 'tomorrow' ), $wpheka_moneris_message );
$wpheka_moneris_check( 'the message names the free plugin', false !== strpos( $wpheka_moneris_message, 'WC Moneris Payment Gateway' ), $wpheka_moneris_message );
$wpheka_moneris_check( 'the message does not say "this plugin"', false === stripos( $wpheka_moneris_message, 'this plugin' ), $wpheka_moneris_message );
$wpheka_moneris_check( 'the message does not ask to upgrade', false === stripos( $wpheka_moneris_message, 'upgrade' ), $wpheka_moneris_message );
$wpheka_moneris_check( 'Moneris Pro is mentioned at most once', substr_count( $wpheka_moneris_message, 'Pro' ) <= 1, $wpheka_moneris_message );
$wpheka_moneris_check( 'the message does not call same-day refunds a part of Pro', false === stripos( $wpheka_moneris_message, 'part of Moneris Pro' ), $wpheka_moneris_message );

$wpheka_moneris_order->delete( true );

$wpheka_moneris_failed = count(
	array_filter(
		$wpheka_moneris_results,
		static function ( $ok ) {
			return ! $ok;
		}
	)
);
WP_CLI::log( sprintf( '%d passed, %d failed', count( $wpheka_moneris_results ) - $wpheka_moneris_failed, $wpheka_moneris_failed ) );
if ( $wpheka_moneris_failed ) {
	WP_CLI::error( 'Refund message cases failed.' );
}
