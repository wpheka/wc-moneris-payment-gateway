<?php
/**
 * Prepares the test store for the browser cases, and puts it back afterwards.
 *
 *   wp eval-file setup.php setup <state-dir>    saves what it changes, writes ids.json
 *   wp eval-file setup.php cleanup <state-dir>  deletes the test data and restores the rest
 *
 * Test data uses the "ZZ MON" prefix. Moneris runs in sandbox mode with the public test
 * store (store5 / yesguy), so no real money moves.
 *
 * @package WPHEKA_Gateway_Moneris
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/lib.php';

$wpheka_moneris_t_step  = isset( $args[0] ) ? $args[0] : '';
$wpheka_moneris_t_state = isset( $args[1] ) ? rtrim( $args[1], '/' ) : '';
$wpheka_moneris_t_saved = 'zz_mon_suite_saved';
$wpheka_moneris_t_opts  = array(
	'woocommerce_currency'                 => 'CAD',
	'woocommerce_default_country'          => 'CA:MB',
	'woocommerce_enable_guest_checkout'    => 'yes',
	'woocommerce_coming_soon'              => 'no',
	'woocommerce_calc_taxes'               => 'no',
	'woocommerce_enable_checkout_login_reminder' => 'no',
	'woocommerce_checkout_page_id'         => null,
	'woocommerce_moneris_settings'         => null,
	'users_can_register'                   => null,
);

/**
 * Path of the must-use plugin that plays "another plugin" printing admin notices.
 *
 * @return string
 */
function wpheka_moneris_t_foreign_notices_file() {
	return WPMU_PLUGIN_DIR . '/zz-mon-foreign-notices.php';
}

/**
 * Delete everything with the ZZ MON prefix.
 */
function wpheka_moneris_t_delete_test_data() {
	foreach ( get_posts( array( 'post_type' => array( 'product', 'page' ), 'post_status' => 'any', 'numberposts' => -1, 's' => 'ZZ MON' ) ) as $post ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- short.
		wp_delete_post( $post->ID, true );
	}
	$orders = wc_get_orders(
		array(
			'billing_email' => 'zz-mon-guest@example.com',
			'limit'         => -1,
			'status'        => 'any',
		)
	);
	foreach ( $orders as $order ) {
		$order->delete( true );
	}
	if ( file_exists( wpheka_moneris_t_foreign_notices_file() ) ) {
		wp_delete_file( wpheka_moneris_t_foreign_notices_file() );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( array( 'zz_mon_manager', 'zz_mon_customer' ) as $login ) {
		$user = get_user_by( 'login', $login );
		if ( $user ) {
			wp_delete_user( $user->ID );
		}
	}
}

if ( 'setup' === $wpheka_moneris_t_step ) {
	// Save only once, so a run stopped halfway still restores the store as it was before.
	if ( false === get_option( $wpheka_moneris_t_saved ) ) {
		$wpheka_moneris_t_keep = array();
		foreach ( array_keys( $wpheka_moneris_t_opts ) as $wpheka_moneris_t_name ) {
			$wpheka_moneris_t_keep[ $wpheka_moneris_t_name ] = get_option( $wpheka_moneris_t_name, null );
		}
		update_option( $wpheka_moneris_t_saved, $wpheka_moneris_t_keep, false );
	}
	wpheka_moneris_t_delete_test_data();
	delete_user_meta( get_user_by( 'login', 'admin' )->ID, 'wpheka_gateway_moneris_donation_version' );

	foreach ( $wpheka_moneris_t_opts as $wpheka_moneris_t_name => $wpheka_moneris_t_value ) {
		if ( null !== $wpheka_moneris_t_value ) {
			update_option( $wpheka_moneris_t_name, $wpheka_moneris_t_value );
		}
	}

	$wpheka_moneris_t_product = new WC_Product_Simple();
	$wpheka_moneris_t_product->set_name( 'ZZ MON Card test' );
	$wpheka_moneris_t_product->set_regular_price( '12.34' );
	$wpheka_moneris_t_product->set_virtual( true );
	$wpheka_moneris_t_product->set_status( 'publish' );
	$wpheka_moneris_t_product->save();

	$wpheka_moneris_t_classic = wp_insert_post(
		array(
			'post_title'   => 'ZZ MON Classic checkout',
			'post_content' => '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		)
	);
	$wpheka_moneris_t_block   = wp_insert_post(
		array(
			'post_title'   => 'ZZ MON Block checkout',
			'post_content' => '<!-- wp:woocommerce/checkout --><div class="wp-block-woocommerce-checkout alignwide wc-block-checkout is-loading"></div><!-- /wp:woocommerce/checkout -->',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		)
	);
	// WooCommerce processes a classic checkout only on its checkout page.
	update_option( 'woocommerce_checkout_page_id', $wpheka_moneris_t_classic );

	update_option(
		'woocommerce_moneris_settings',
		array(
			'enabled'         => 'yes',
			'title'           => 'Moneris',
			'description'     => 'Pay with your credit card via moneris.',
			'store_id'        => 'store5',
			'api_token'       => 'yesguy',
			'preferred_cards' => array( 'visa', 'mastercard' ),
			'sandbox'         => 'yes',
			'logging'         => 'yes',
		)
	);

	$wpheka_moneris_t_manager = wp_insert_user(
		array(
			'user_login' => 'zz_mon_manager',
			'user_pass'  => 'zz-mon-Manager-1',
			'user_email' => 'zz-mon-manager@example.com',
			'role'       => 'shop_manager',
		)
	);
	$wpheka_moneris_t_customer = wp_insert_user(
		array(
			'user_login' => 'zz_mon_customer',
			'user_pass'  => 'zz-mon-Customer-1',
			'user_email' => 'zz-mon-customer@example.com',
			'role'       => 'customer',
		)
	);

	// "Another plugin" with notices hooked three ways: a function, a closure and a method.
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.
		wpheka_moneris_t_foreign_notices_file(),
		'<?php
// Test fixture written by the Moneris functional suite; deleted at the end of the run.
function zz_mon_foreign_notice() { echo \'<div class="notice notice-warning zz-mon-foreign"><p>ZZ MON function notice</p></div>\'; }
class ZZ_Mon_Foreign { public static function notice() { echo \'<div class="notice notice-info zz-mon-foreign"><p>ZZ MON method notice</p></div>\'; } }
add_action( \'admin_notices\', \'zz_mon_foreign_notice\' );
add_action( \'admin_notices\', array( \'ZZ_Mon_Foreign\', \'notice\' ) );
add_action( \'all_admin_notices\', function () { echo \'<div class="notice notice-error zz-mon-foreign"><p>ZZ MON closure notice</p></div>\'; } );
// One printed outside the notice hooks, as some plugins do.
add_action( \'in_admin_header\', function () { echo \'<div class="notice notice-warning zz-mon-stray"><p>ZZ MON stray notice</p></div>\'; }, 1 );
'
	);

	$wpheka_moneris_t_ids = array(
		'home'     => home_url( '/' ),
		'admin'    => admin_url(),
		'product'  => $wpheka_moneris_t_product->get_id(),
		'classic'  => get_permalink( $wpheka_moneris_t_classic ),
		'block'    => get_permalink( $wpheka_moneris_t_block ),
		'manager'  => array( 'zz_mon_manager', 'zz-mon-Manager-1' ),
		'customer' => array( 'zz_mon_customer', 'zz-mon-Customer-1' ),
	);
	file_put_contents( $wpheka_moneris_t_state . '/ids.json', wp_json_encode( $wpheka_moneris_t_ids ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test state.
	wpheka_moneris_t_check( 'setup: store ready (CAD, guest checkout, sandbox store5, classic and block checkout pages)', $wpheka_moneris_t_product->get_id() > 0 && $wpheka_moneris_t_classic > 0 && $wpheka_moneris_t_block > 0 && ! is_wp_error( $wpheka_moneris_t_manager ) && ! is_wp_error( $wpheka_moneris_t_customer ) );
	return;
}

if ( 'cleanup' === $wpheka_moneris_t_step ) {
	wpheka_moneris_t_delete_test_data();
	delete_user_meta( get_user_by( 'login', 'admin' )->ID, 'wpheka_gateway_moneris_donation_version' );
	$wpheka_moneris_t_keep = get_option( $wpheka_moneris_t_saved );
	if ( is_array( $wpheka_moneris_t_keep ) ) {
		foreach ( $wpheka_moneris_t_keep as $wpheka_moneris_t_name => $wpheka_moneris_t_value ) {
			if ( null === $wpheka_moneris_t_value ) {
				delete_option( $wpheka_moneris_t_name );
			} else {
				update_option( $wpheka_moneris_t_name, $wpheka_moneris_t_value );
			}
		}
	}
	delete_option( $wpheka_moneris_t_saved );
	delete_transient( WPHEKA_Gateway_Moneris_Admin_Page::STATS_TRANSIENT );
	wpheka_moneris_t_check( 'cleanup: test data deleted and the store restored', false === get_option( $wpheka_moneris_t_saved ) && ! get_user_by( 'login', 'zz_mon_manager' ) );
}
