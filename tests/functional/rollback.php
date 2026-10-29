<?php
/**
 * Rolling back to 3.8.1 after saving on the 3.9.0 page: 3.8.1 reads every setting the same.
 *
 * Two steps, run by run.sh for each variant:
 *   wp eval-file rollback.php save <variant>      (3.9.0 active) saves through the page
 *   wp --skip-plugins=wc-moneris-payment-gateway eval-file rollback.php read <variant> <old-class-file>
 *        loads the released 3.8.1 gateway class and checks what it reads.
 * The expected values travel in the option zz_mon_rollback_expected, deleted at the end.
 *
 * @package WPHEKA_Gateway_Moneris
 */

defined( 'ABSPATH' ) || exit;

$wpheka_moneris_t_step    = isset( $args[0] ) ? $args[0] : '';
$wpheka_moneris_t_variant = isset( $args[1] ) ? $args[1] : 'live';

if ( 'save' === $wpheka_moneris_t_step ) {
	require_once __DIR__ . '/lib.php';
	wp_set_current_user( get_user_by( 'login', 'admin' )->ID );
	update_option( 'zz_mon_rollback_before', wpheka_moneris_t_option() );

	$wpheka_moneris_t_start = wpheka_moneris_t_live_settings();
	if ( 'sandbox-no-logos' === $wpheka_moneris_t_variant ) {
		$wpheka_moneris_t_start = array_merge(
			$wpheka_moneris_t_start,
			array(
				'sandbox'   => 'yes',
				'store_id'  => 'store5',
				'api_token' => 'yesguy',
				'logging'   => 'yes',
			)
		);
	}
	wpheka_moneris_t_set_option( $wpheka_moneris_t_start );
	wpheka_moneris_t_round_trip(
		static function ( &$post ) use ( $wpheka_moneris_t_variant ) {
			$post['woocommerce_moneris_title'] = 'Saved on 3.9.0';
			if ( 'sandbox-no-logos' === $wpheka_moneris_t_variant ) {
				$post['woocommerce_moneris_preferred_cards'] = array( '' );
				unset( $post['woocommerce_moneris_enabled'] );
			}
		}
	);
	$wpheka_moneris_t_saved = wpheka_moneris_t_option();
	update_option( 'zz_mon_rollback_expected', $wpheka_moneris_t_saved );
	wpheka_moneris_t_check( "rollback ($wpheka_moneris_t_variant): 3.9.0 saved", 'Saved on 3.9.0' === $wpheka_moneris_t_saved['title'], wpheka_moneris_t_dump( $wpheka_moneris_t_saved ) );
	return;
}

// Read step: the plugin is skipped, so load the 3.8.1 gateway class it shipped.
require_once __DIR__ . '/lib.php';
$wpheka_moneris_t_old = isset( $args[2] ) ? $args[2] : '';
$wpheka_moneris_t_dir = dirname( __DIR__, 2 );
foreach (
	array(
		'WPHEKA_MONERIS_VERSION'     => '3.8.1',
		'WPHEKA_MONERIS_MAIN_FILE'   => $wpheka_moneris_t_dir . '/wc-moneris-payment-gateway.php',
		'WPHEKA_MONERIS_PLUGIN_ICON' => '',
		'WPHEKA_MONERIS_PLUGIN_LOGO' => '',
	) as $wpheka_moneris_t_const => $wpheka_moneris_t_value
) {
	if ( ! defined( $wpheka_moneris_t_const ) ) {
		define( $wpheka_moneris_t_const, $wpheka_moneris_t_value );
	}
}
$wpheka_moneris_t_loaded = ! class_exists( 'WPHEKA_Gateway_Moneris' ) && is_readable( $wpheka_moneris_t_old );
wpheka_moneris_t_check( "rollback ($wpheka_moneris_t_variant): 3.8.1 gateway loaded alone", $wpheka_moneris_t_loaded, $wpheka_moneris_t_old );
if ( $wpheka_moneris_t_loaded ) {
	require_once $wpheka_moneris_t_dir . '/includes/class-wpheka-moneris-logger.php';
	require_once $wpheka_moneris_t_dir . '/includes/wpheka-moneris-api/vendor/autoload.php';
	require_once $wpheka_moneris_t_old;

	$wpheka_moneris_t_expected = get_option( 'zz_mon_rollback_expected' );
	$wpheka_moneris_t_errors   = array();
	set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- catch warnings from the old code.
		static function ( $no, $str ) use ( &$wpheka_moneris_t_errors ) {
			$wpheka_moneris_t_errors[] = $str;
			return true;
		}
	);
	$wpheka_moneris_t_gw   = new WPHEKA_Gateway_Moneris();
	$wpheka_moneris_t_icon = $wpheka_moneris_t_gw->get_icon();
	$wpheka_moneris_t_gw->get_preferred_cards();
	restore_error_handler();

	foreach ( array( 'enabled', 'title', 'description', 'sandbox', 'store_id', 'api_token', 'logging' ) as $wpheka_moneris_t_key ) {
		wpheka_moneris_t_check( "rollback ($wpheka_moneris_t_variant): 3.8.1 reads $wpheka_moneris_t_key as saved", $wpheka_moneris_t_expected[ $wpheka_moneris_t_key ] === $wpheka_moneris_t_gw->$wpheka_moneris_t_key, wpheka_moneris_t_dump( $wpheka_moneris_t_gw->$wpheka_moneris_t_key ) );
	}
	wpheka_moneris_t_check( "rollback ($wpheka_moneris_t_variant): 3.8.1 reads the card logos as saved", $wpheka_moneris_t_expected['preferred_cards'] === $wpheka_moneris_t_gw->preferred_cards, wpheka_moneris_t_dump( $wpheka_moneris_t_gw->preferred_cards ) );
	wpheka_moneris_t_check( "rollback ($wpheka_moneris_t_variant): 3.8.1 checkout icon has no PHP warning", array() === $wpheka_moneris_t_errors, wpheka_moneris_t_dump( $wpheka_moneris_t_errors ) );
	wpheka_moneris_t_check( "rollback ($wpheka_moneris_t_variant): 3.8.1 shows the saved logos", count( $wpheka_moneris_t_expected['preferred_cards'] ) === substr_count( $wpheka_moneris_t_icon, '<img' ), $wpheka_moneris_t_icon );
	wpheka_moneris_t_check( "rollback ($wpheka_moneris_t_variant): 3.8.1 is available at checkout as saved", ( 'yes' === $wpheka_moneris_t_expected['enabled'] ) === $wpheka_moneris_t_gw->is_available() );
}

$wpheka_moneris_t_before = get_option( 'zz_mon_rollback_before' );
if ( null === $wpheka_moneris_t_before || false === $wpheka_moneris_t_before ) {
	delete_option( 'woocommerce_moneris_settings' );
} else {
	update_option( 'woocommerce_moneris_settings', $wpheka_moneris_t_before );
}
delete_option( 'zz_mon_rollback_before' );
delete_option( 'zz_mon_rollback_expected' );
wpheka_moneris_t_check( "rollback.php ($wpheka_moneris_t_variant): ran to the end", true );
