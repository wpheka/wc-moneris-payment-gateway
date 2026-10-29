<?php
/**
 * The WPHEKA > Moneris settings page and its save, through the real handler.
 *
 * Every case renders the real page, posts its form the way a browser would and reads the
 * stored option back. The option is put back as it was at the end.
 *
 * Run by run.sh:  wp eval-file .../tests/functional/settings.php
 *
 * @package WPHEKA_Gateway_Moneris
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/lib.php';

$wpheka_moneris_t_before = wpheka_moneris_t_option();
$wpheka_moneris_t_admin  = get_user_by( 'login', 'admin' );
wp_set_current_user( $wpheka_moneris_t_admin->ID );

$wpheka_moneris_t_page = class_exists( 'WPHEKA_Gateway_Moneris_Admin_Page' );
wpheka_moneris_t_check( 'page class loaded on PHP ' . PHP_VERSION, $wpheka_moneris_t_page );
if ( ! $wpheka_moneris_t_page ) {
	return;
}

// 1. An unchanged save keeps every stored setting exactly as it was, for each kind of store.
$wpheka_moneris_t_stores = array(
	'live store with Pro keys'  => wpheka_moneris_t_live_settings(),
	'sandbox store'             => array_merge(
		wpheka_moneris_t_live_settings(),
		array(
			'sandbox'   => 'yes',
			'store_id'  => 'store5',
			'api_token' => 'yesguy',
			'logging'   => 'yes',
		)
	),
	'disabled store'            => array_merge( wpheka_moneris_t_live_settings(), array( 'enabled' => 'no' ) ),
	'store with no credentials' => array_merge(
		wpheka_moneris_t_live_settings(),
		array(
			'store_id'  => '',
			'api_token' => '',
		)
	),
	'store with no card logos'  => array_merge( wpheka_moneris_t_live_settings(), array( 'preferred_cards' => array() ) ),
);
foreach ( $wpheka_moneris_t_stores as $wpheka_moneris_t_label => $wpheka_moneris_t_settings ) {
	wpheka_moneris_t_set_option( $wpheka_moneris_t_settings );
	wpheka_moneris_t_render( 'settings' );
	wpheka_moneris_t_check( "viewing the page changes nothing ($wpheka_moneris_t_label)", wpheka_moneris_t_option() === $wpheka_moneris_t_settings, wpheka_moneris_t_dump( wpheka_moneris_t_option() ) );
	$wpheka_moneris_t_to = wpheka_moneris_t_round_trip();
	wpheka_moneris_t_check( "unchanged save redirects to Settings saved ($wpheka_moneris_t_label)", false !== strpos( $wpheka_moneris_t_to, 'page=wpheka-moneris' ) && false !== strpos( $wpheka_moneris_t_to, 'w-saved=1' ), $wpheka_moneris_t_to );
	wpheka_moneris_t_check( "unchanged save keeps the option identical ($wpheka_moneris_t_label)", wpheka_moneris_t_option() === $wpheka_moneris_t_settings, wpheka_moneris_t_dump( wpheka_moneris_t_option() ) );
}

// A store that cleared its logos on WooCommerce's screen in 3.8.1 has '' stored: it stays empty.
wpheka_moneris_t_set_option( array_merge( wpheka_moneris_t_live_settings(), array( 'preferred_cards' => '' ) ) );
wpheka_moneris_t_round_trip();
$wpheka_moneris_t_saved = wpheka_moneris_t_option();
wpheka_moneris_t_check( "logos cleared in 3.8.1 ('') stay empty after a save", array() === $wpheka_moneris_t_saved['preferred_cards'], wpheka_moneris_t_dump( $wpheka_moneris_t_saved['preferred_cards'] ) );
$wpheka_moneris_t_expect = array_merge( wpheka_moneris_t_live_settings(), array( 'preferred_cards' => array() ) );
wpheka_moneris_t_check( "logos cleared in 3.8.1: every other setting unchanged", $wpheka_moneris_t_saved === $wpheka_moneris_t_expect, wpheka_moneris_t_dump( $wpheka_moneris_t_saved ) );

// A store that never saved Moneris settings: the page shows the defaults and saves them.
wpheka_moneris_t_set_option( null );
$wpheka_moneris_t_html = wpheka_moneris_t_render( 'settings' );
wpheka_moneris_t_check( 'never-saved store: viewing the page stores nothing', null === wpheka_moneris_t_option() );
$wpheka_moneris_t_post = wpheka_moneris_t_form_post( $wpheka_moneris_t_html );
wpheka_moneris_t_check( 'never-saved store: page shows the default Visa logo, sandbox on, enabled', array( 'visa' ) === array_values( array_filter( $wpheka_moneris_t_post['woocommerce_moneris_preferred_cards'] ) ) && isset( $wpheka_moneris_t_post['woocommerce_moneris_sandbox'], $wpheka_moneris_t_post['woocommerce_moneris_enabled'] ) && ! isset( $wpheka_moneris_t_post['woocommerce_moneris_logging'] ), wpheka_moneris_t_dump( $wpheka_moneris_t_post ) );
wpheka_moneris_t_save( $wpheka_moneris_t_post );
$wpheka_moneris_t_saved = wpheka_moneris_t_option();
wpheka_moneris_t_check(
	'never-saved store: first save stores the same defaults WooCommerce would',
	is_array( $wpheka_moneris_t_saved ) && 'yes' === $wpheka_moneris_t_saved['enabled'] && 'yes' === $wpheka_moneris_t_saved['sandbox'] && 'no' === $wpheka_moneris_t_saved['logging'] && array( 'visa' ) === $wpheka_moneris_t_saved['preferred_cards'] && 'Moneris' === $wpheka_moneris_t_saved['title'] && 'Pay with your credit card via moneris.' === $wpheka_moneris_t_saved['description'] && '' === $wpheka_moneris_t_saved['store_id'],
	wpheka_moneris_t_dump( $wpheka_moneris_t_saved )
);

// 2. Each switch, off and back on. Unticked posts nothing, as WooCommerce expects.
foreach ( array( 'enabled', 'sandbox', 'logging' ) as $wpheka_moneris_t_key ) {
	$wpheka_moneris_t_start = array_merge( wpheka_moneris_t_live_settings(), array( $wpheka_moneris_t_key => 'yes' ) );
	wpheka_moneris_t_set_option( $wpheka_moneris_t_start );
	wpheka_moneris_t_round_trip(
		static function ( &$post ) use ( $wpheka_moneris_t_key ) {
			unset( $post[ 'woocommerce_moneris_' . $wpheka_moneris_t_key ] );
		}
	);
	$wpheka_moneris_t_saved = wpheka_moneris_t_option();
	wpheka_moneris_t_check( "switch $wpheka_moneris_t_key: on to off saves no", 'no' === $wpheka_moneris_t_saved[ $wpheka_moneris_t_key ], wpheka_moneris_t_dump( $wpheka_moneris_t_saved[ $wpheka_moneris_t_key ] ) );
	wpheka_moneris_t_check( "switch $wpheka_moneris_t_key: nothing else changed", array_merge( $wpheka_moneris_t_start, array( $wpheka_moneris_t_key => 'no' ) ) === $wpheka_moneris_t_saved, wpheka_moneris_t_dump( $wpheka_moneris_t_saved ) );
	$wpheka_moneris_t_html = wpheka_moneris_t_render( 'settings' );
	wpheka_moneris_t_check( "switch $wpheka_moneris_t_key: shown off after saving off", ! isset( wpheka_moneris_t_form_post( $wpheka_moneris_t_html )[ 'woocommerce_moneris_' . $wpheka_moneris_t_key ] ) );
	wpheka_moneris_t_round_trip(
		static function ( &$post ) use ( $wpheka_moneris_t_key ) {
			$post[ 'woocommerce_moneris_' . $wpheka_moneris_t_key ] = 'yes';
		}
	);
	$wpheka_moneris_t_saved = wpheka_moneris_t_option();
	wpheka_moneris_t_check( "switch $wpheka_moneris_t_key: off to on saves yes", 'yes' === $wpheka_moneris_t_saved[ $wpheka_moneris_t_key ], wpheka_moneris_t_dump( $wpheka_moneris_t_saved[ $wpheka_moneris_t_key ] ) );
	wpheka_moneris_t_check( "switch $wpheka_moneris_t_key: back to the start exactly", $wpheka_moneris_t_start === $wpheka_moneris_t_saved, wpheka_moneris_t_dump( $wpheka_moneris_t_saved ) );
}

// The page never posts a hidden "no" for a switch (WooCommerce would save it as yes).
wpheka_moneris_t_set_option( wpheka_moneris_t_live_settings() );
$wpheka_moneris_t_html = wpheka_moneris_t_render( 'settings' );
wpheka_moneris_t_check( 'no hidden off value for any switch', 0 === preg_match( '/<input type="hidden" name="woocommerce_moneris_(enabled|sandbox|logging)"/', $wpheka_moneris_t_html ) );

// 3. Text fields: edits, quotes and HTML are handled as WooCommerce's screen handles them.
wpheka_moneris_t_round_trip(
	static function ( &$post ) {
		$post['woocommerce_moneris_title']     = wp_slash( "Card (O'Reilly \"test\")" );
		$post['woocommerce_moneris_store_id']  = wp_slash( '  monca99999  ' );
		$post['woocommerce_moneris_api_token'] = wp_slash( 'New\\Token' );
		$post['woocommerce_moneris_description'] = wp_slash( 'Pay <script>alert(1)</script><em>now</em>' );
	}
);
$wpheka_moneris_t_saved = wpheka_moneris_t_option();
wpheka_moneris_t_check( 'title keeps quotes and apostrophes', "Card (O'Reilly \"test\")" === $wpheka_moneris_t_saved['title'], wpheka_moneris_t_dump( $wpheka_moneris_t_saved['title'] ) );
wpheka_moneris_t_check( 'store ID is trimmed as WooCommerce trims it', 'monca99999' === $wpheka_moneris_t_saved['store_id'], wpheka_moneris_t_dump( $wpheka_moneris_t_saved['store_id'] ) );
wpheka_moneris_t_check( 'API token keeps a backslash', 'New\\Token' === $wpheka_moneris_t_saved['api_token'], wpheka_moneris_t_dump( $wpheka_moneris_t_saved['api_token'] ) );
wpheka_moneris_t_check( 'description: script removed, allowed HTML kept', 'Pay alert(1)<em>now</em>' === $wpheka_moneris_t_saved['description'], wpheka_moneris_t_dump( $wpheka_moneris_t_saved['description'] ) );
wpheka_moneris_t_check( 'Pro keys untouched by a free save', 'pro-cad-store' === $wpheka_moneris_t_saved['store_id_cad'] && 'pro-cad-token' === $wpheka_moneris_t_saved['api_token_cad'] );
// What was saved is shown back unchanged, and saving it again changes nothing.
wpheka_moneris_t_round_trip();
wpheka_moneris_t_check( 'edited values survive a second unchanged save', wpheka_moneris_t_option() === $wpheka_moneris_t_saved, wpheka_moneris_t_dump( wpheka_moneris_t_option() ) );

// 4. Card logos.
wpheka_moneris_t_set_option( wpheka_moneris_t_live_settings() );
wpheka_moneris_t_round_trip(
	static function ( &$post ) {
		$post['woocommerce_moneris_preferred_cards'] = array( '' );
	}
);
$wpheka_moneris_t_saved = wpheka_moneris_t_option();
wpheka_moneris_t_check( 'clearing every logo saves an empty list', array() === $wpheka_moneris_t_saved['preferred_cards'], wpheka_moneris_t_dump( $wpheka_moneris_t_saved['preferred_cards'] ) );
$wpheka_moneris_t_gw = wpheka_moneris_t_gateway();
wpheka_moneris_t_check( 'no logos: checkout icon empty, no warning', '' === $wpheka_moneris_t_gw->get_icon() && array() === $wpheka_moneris_t_gw->get_preferred_cards() );
wpheka_moneris_t_round_trip(
	static function ( &$post ) {
		$post['woocommerce_moneris_preferred_cards'] = array( '', 'jcb', 'evil<b>', 'discover' );
	}
);
$wpheka_moneris_t_saved = wpheka_moneris_t_option();
wpheka_moneris_t_check( 'logos keep known cards only, in the posted order', array( 'jcb', 'discover' ) === $wpheka_moneris_t_saved['preferred_cards'], wpheka_moneris_t_dump( $wpheka_moneris_t_saved['preferred_cards'] ) );
$wpheka_moneris_t_gw = wpheka_moneris_t_gateway();
wpheka_moneris_t_check( 'checkout shows the chosen logos', false !== strpos( $wpheka_moneris_t_gw->get_icon(), 'jcb.svg' ) && false !== strpos( $wpheka_moneris_t_gw->get_icon(), 'discover.svg' ) && false === strpos( $wpheka_moneris_t_gw->get_icon(), 'visa.svg' ) );

// 5. A post cut short never blanks anything (and the title change in it is not saved either).
foreach ( array( 'store_id', 'api_token', 'title', 'description', 'preferred_cards' ) as $wpheka_moneris_t_key ) {
	wpheka_moneris_t_set_option( wpheka_moneris_t_live_settings() );
	$wpheka_moneris_t_to = wpheka_moneris_t_round_trip(
		static function ( &$post ) use ( $wpheka_moneris_t_key ) {
			if ( 'title' !== $wpheka_moneris_t_key ) {
				$post['woocommerce_moneris_title'] = 'Changed';
			}
			unset( $post[ 'woocommerce_moneris_' . $wpheka_moneris_t_key ] );
		}
	);
	wpheka_moneris_t_check( "post missing $wpheka_moneris_t_key: nothing saved", wpheka_moneris_t_option() === wpheka_moneris_t_live_settings(), wpheka_moneris_t_dump( wpheka_moneris_t_option() ) );
	wpheka_moneris_t_check( "post missing $wpheka_moneris_t_key: back to the page with an error", false !== strpos( $wpheka_moneris_t_to, 'w-error=1' ), $wpheka_moneris_t_to );
}
// A post cut off at its end (the end marker missing) saves nothing, even a switch change.
wpheka_moneris_t_set_option( wpheka_moneris_t_live_settings() );
$wpheka_moneris_t_to = wpheka_moneris_t_round_trip(
	static function ( &$post ) {
		$post['woocommerce_moneris_logging'] = 'yes';
		unset( $post[ WPHEKA_Gateway_Moneris_Admin_Page::FORM_END ] );
	}
);
wpheka_moneris_t_check( 'post without the end marker: nothing saved', wpheka_moneris_t_option() === wpheka_moneris_t_live_settings() && false !== strpos( $wpheka_moneris_t_to, 'w-error=1' ), $wpheka_moneris_t_to );
$wpheka_moneris_t_html = wpheka_moneris_t_render( 'settings' );
wpheka_moneris_t_check( 'the end marker is the last field in the form', (bool) preg_match( '/name="' . WPHEKA_Gateway_Moneris_Admin_Page::FORM_END . '" value="1">(?:(?!<input|<select|<textarea).)*<\/form>/s', $wpheka_moneris_t_html ) );

// The redirect after that failure shows the reason in a red toast, never "Settings saved".
$_GET = array(
	'page'    => WPHEKA_Gateway_Moneris_Admin_Page::PAGE,
	'tab'     => 'settings',
	'w-error' => '1',
);
ob_start();
WPHEKA_Gateway_Moneris_Admin_Page::render();
$wpheka_moneris_t_html = ob_get_clean();
$_GET                  = array();
wpheka_moneris_t_check( 'the error shows in a red toast, never "Settings saved"', false !== strpos( $wpheka_moneris_t_html, 'w-toast--error is-shown' ) && false !== strpos( $wpheka_moneris_t_html, 'did not arrive complete' ) && false === strpos( $wpheka_moneris_t_html, 'Settings saved' ) );

// 6. Fields other code adds to the gateway keep their values.
$wpheka_moneris_t_extra = static function ( $fields ) {
	$fields['zz_extra_note'] = array(
		'title' => 'Extra note',
		'type'  => 'text',
	);
	$fields['zz_extra_flag'] = array(
		'title' => 'Extra flag',
		'type'  => 'checkbox',
	);
	$fields['zz_extra_list'] = array(
		'title'   => 'Extra list',
		'type'    => 'multiselect',
		'options' => array(
			'a' => 'A',
			'b' => 'B',
		),
	);
	return $fields;
};
add_filter( 'woocommerce_settings_api_form_fields_moneris', $wpheka_moneris_t_extra );
$wpheka_moneris_t_start = array_merge(
	wpheka_moneris_t_live_settings(),
	array(
		'zz_extra_note' => "Kept 'as is'",
		'zz_extra_flag' => 'yes',
		'zz_extra_list' => array( 'b' ),
	)
);
wpheka_moneris_t_set_option( $wpheka_moneris_t_start );
wpheka_moneris_t_round_trip();
wpheka_moneris_t_check( 'fields added by other code keep their saved values', wpheka_moneris_t_option() === $wpheka_moneris_t_start, wpheka_moneris_t_dump( wpheka_moneris_t_option() ) );
remove_filter( 'woocommerce_settings_api_form_fields_moneris', $wpheka_moneris_t_extra );
wpheka_moneris_t_gateway();

// Other code listening on WooCommerce's gateway save hook runs, and sees complete values.
wpheka_moneris_t_set_option( $wpheka_moneris_t_start );
add_filter( 'woocommerce_settings_api_form_fields_moneris', $wpheka_moneris_t_extra );
$GLOBALS['wpheka_moneris_t_heard'] = null;
$wpheka_moneris_t_listener         = static function () {
	$GLOBALS['wpheka_moneris_t_heard'] = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- test listener.
};
add_action( 'woocommerce_update_options_payment_gateways_moneris', $wpheka_moneris_t_listener, 20 );
wpheka_moneris_t_round_trip(
	static function ( &$post ) {
		$post['woocommerce_moneris_title'] = 'Heard';
	}
);
remove_action( 'woocommerce_update_options_payment_gateways_moneris', $wpheka_moneris_t_listener, 20 );
$wpheka_moneris_t_heard = $GLOBALS['wpheka_moneris_t_heard'];
wpheka_moneris_t_check( "WooCommerce's gateway save hook fires on a save from the page", is_array( $wpheka_moneris_t_heard ) );
wpheka_moneris_t_check( 'listeners see complete values, other code\'s fields included', is_array( $wpheka_moneris_t_heard ) && 'Heard' === $wpheka_moneris_t_heard['woocommerce_moneris_title'] && "Kept 'as is'" === $wpheka_moneris_t_heard['woocommerce_moneris_zz_extra_note'] && 'monca01234' === $wpheka_moneris_t_heard['woocommerce_moneris_store_id'], wpheka_moneris_t_dump( $wpheka_moneris_t_heard ) );
wpheka_moneris_t_check( 'saved once, other code\'s fields kept', array_merge( $wpheka_moneris_t_start, array( 'title' => 'Heard' ) ) === wpheka_moneris_t_option(), wpheka_moneris_t_dump( wpheka_moneris_t_option() ) );
wpheka_moneris_t_check( '$_POST is put back after the save', array() === $_POST );
remove_filter( 'woocommerce_settings_api_form_fields_moneris', $wpheka_moneris_t_extra );
wpheka_moneris_t_gateway();

// A second copy of the gateway (built by other code) cannot blank anything either.
wpheka_moneris_t_set_option( $wpheka_moneris_t_start );
add_filter( 'woocommerce_settings_api_form_fields_moneris', $wpheka_moneris_t_extra );
// Built after the page rendered, so the copy is hooked after the live gateway and saves last.
$GLOBALS['wpheka_moneris_t_copy'] = null;
wpheka_moneris_t_round_trip(
	static function () {
		$GLOBALS['wpheka_moneris_t_copy'] = new WPHEKA_Gateway_Moneris();
	}
);
remove_action( 'woocommerce_update_options_payment_gateways_moneris', array( $GLOBALS['wpheka_moneris_t_copy'], 'process_admin_options' ) );
wpheka_moneris_t_check( 'a second gateway copy hooked to the save keeps every value', wpheka_moneris_t_option() === $wpheka_moneris_t_start, wpheka_moneris_t_dump( wpheka_moneris_t_option() ) );
remove_filter( 'woocommerce_settings_api_form_fields_moneris', $wpheka_moneris_t_extra );
wpheka_moneris_t_gateway();

// With the WPHEKA menu hidden by a site, the page still exists, under WooCommerce.
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$GLOBALS['admin_page_hooks'] = isset( $GLOBALS['admin_page_hooks'] ) ? $GLOBALS['admin_page_hooks'] : array();
$GLOBALS['admin_page_hooks']['woocommerce'] = 'woocommerce';
add_filter( 'wpheka_plugin_panel_menu_page_show', '__return_false' );
WPHEKA_Gateway_Moneris_Admin_Page::register_page();
remove_filter( 'wpheka_plugin_panel_menu_page_show', '__return_false' );
$wpheka_moneris_t_under = isset( $GLOBALS['submenu']['woocommerce'] ) ? wp_list_pluck( $GLOBALS['submenu']['woocommerce'], 2 ) : array();
wpheka_moneris_t_check( 'WPHEKA menu hidden: the page is registered under WooCommerce', in_array( WPHEKA_Gateway_Moneris_Admin_Page::PAGE, $wpheka_moneris_t_under, true ), wpheka_moneris_t_dump( $wpheka_moneris_t_under ) );

// 7. Nonce and capability.
wpheka_moneris_t_set_option( wpheka_moneris_t_live_settings() );
$wpheka_moneris_t_post = wpheka_moneris_t_form_post( wpheka_moneris_t_render( 'settings' ) );
$wpheka_moneris_t_post['woocommerce_moneris_sandbox'] = 'yes';
$wpheka_moneris_t_bad  = array_merge( $wpheka_moneris_t_post, array( '_wpnonce' => 'nope' ) );
$wpheka_moneris_t_to   = wpheka_moneris_t_save( $wpheka_moneris_t_bad );
wpheka_moneris_t_check( 'a bad nonce saves nothing', wpheka_moneris_t_option() === wpheka_moneris_t_live_settings() && 0 === strpos( $wpheka_moneris_t_to, 'died:' ), $wpheka_moneris_t_to );

$wpheka_moneris_t_users = array();
foreach ( array( 'shop_manager', 'subscriber', 'editor' ) as $wpheka_moneris_t_role ) {
	$wpheka_moneris_t_login = 'zz_mon_' . $wpheka_moneris_t_role;
	$wpheka_moneris_t_old   = get_user_by( 'login', $wpheka_moneris_t_login );
	if ( $wpheka_moneris_t_old ) {
		wp_delete_user( $wpheka_moneris_t_old->ID );
	}
	$wpheka_moneris_t_users[ $wpheka_moneris_t_role ] = wp_insert_user(
		array(
			'user_login' => $wpheka_moneris_t_login,
			'user_pass'  => wp_generate_password(),
			'user_email' => $wpheka_moneris_t_login . '@example.com',
			'role'       => $wpheka_moneris_t_role,
		)
	);
}
foreach ( array( 'subscriber', 'editor' ) as $wpheka_moneris_t_role ) {
	wp_set_current_user( $wpheka_moneris_t_users[ $wpheka_moneris_t_role ] );
	$wpheka_moneris_t_post['_wpnonce'] = wp_create_nonce( WPHEKA_Gateway_Moneris_Admin_Page::SAVE_ACTION );
	$wpheka_moneris_t_to               = wpheka_moneris_t_save( $wpheka_moneris_t_post );
	wpheka_moneris_t_check( "$wpheka_moneris_t_role cannot save", wpheka_moneris_t_option() === wpheka_moneris_t_live_settings() && 0 === strpos( $wpheka_moneris_t_to, 'died:' ), $wpheka_moneris_t_to );
	wpheka_moneris_t_check( "$wpheka_moneris_t_role sees no settings", '' === wpheka_moneris_t_render( 'settings' ) );
}
wp_set_current_user( $wpheka_moneris_t_users['shop_manager'] );
$wpheka_moneris_t_post['_wpnonce'] = wp_create_nonce( WPHEKA_Gateway_Moneris_Admin_Page::SAVE_ACTION );
wpheka_moneris_t_save( $wpheka_moneris_t_post );
wpheka_moneris_t_check( 'a shop manager can save, as on WooCommerce\'s screen', 'yes' === wpheka_moneris_t_option()['sandbox'] );
wpheka_moneris_t_check( 'a shop manager sees the settings form', false !== strpos( wpheka_moneris_t_render( 'settings' ), 'id="' . WPHEKA_Gateway_Moneris_Admin_Page::FORM . '"' ) );
wp_set_current_user( $wpheka_moneris_t_admin->ID );
foreach ( $wpheka_moneris_t_users as $wpheka_moneris_t_id ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $wpheka_moneris_t_id );
}

// 8. WooCommerce's own Moneris screen (PHP below 7.4, or a tab left open) still saves.
wpheka_moneris_t_set_option( wpheka_moneris_t_live_settings() );
$wpheka_moneris_t_gw = wpheka_moneris_t_gateway();
ob_start();
$wpheka_moneris_t_gw->admin_options();
$wpheka_moneris_t_html = '<form id="mainform">' . ob_get_clean() . '</form>';
$wpheka_moneris_t_post = wpheka_moneris_t_form_post( $wpheka_moneris_t_html, '//form[@id="mainform"]' );
// Sandbox and logging are off in these settings, so their unticked boxes post nothing.
wpheka_moneris_t_check( 'WooCommerce screen still draws every field', 6 === count( array_intersect( array_keys( $wpheka_moneris_t_post ), array( 'woocommerce_moneris_enabled', 'woocommerce_moneris_title', 'woocommerce_moneris_description', 'woocommerce_moneris_store_id', 'woocommerce_moneris_api_token', 'woocommerce_moneris_preferred_cards' ) ) ) && ! isset( $wpheka_moneris_t_post['woocommerce_moneris_sandbox'], $wpheka_moneris_t_post['woocommerce_moneris_logging'] ), wpheka_moneris_t_dump( array_keys( $wpheka_moneris_t_post ) ) );
$_POST = $wpheka_moneris_t_post;
do_action( 'woocommerce_update_options_payment_gateways_moneris' );
$_POST = array();
wpheka_moneris_t_check( 'WooCommerce screen: unchanged save keeps the option identical', wpheka_moneris_t_option() === wpheka_moneris_t_live_settings(), wpheka_moneris_t_dump( wpheka_moneris_t_option() ) );
$wpheka_moneris_t_post['woocommerce_moneris_title'] = 'Saved on the old screen';
$_POST = $wpheka_moneris_t_post;
do_action( 'woocommerce_update_options_payment_gateways_moneris' );
$_POST = array();
wpheka_moneris_t_check( 'WooCommerce screen: a change still saves', 'Saved on the old screen' === wpheka_moneris_t_option()['title'] );

// The same text typed on either screen is stored the same way.
foreach ( array( 'A & B "quoted" <b>bold</b> <script>x</script>', "It's 50% off \\ today" ) as $wpheka_moneris_t_text ) {
	wpheka_moneris_t_set_option( wpheka_moneris_t_live_settings() );
	$_POST                                    = $wpheka_moneris_t_post;
	$_POST['woocommerce_moneris_description'] = wp_slash( $wpheka_moneris_t_text );
	do_action( 'woocommerce_update_options_payment_gateways_moneris' );
	$_POST                  = array();
	$wpheka_moneris_t_woo   = wpheka_moneris_t_option()['description'];
	wpheka_moneris_t_round_trip(
		static function ( &$post ) use ( $wpheka_moneris_t_text ) {
			$post['woocommerce_moneris_description'] = wp_slash( $wpheka_moneris_t_text );
		}
	);
	$wpheka_moneris_t_ours = wpheka_moneris_t_option()['description'];
	wpheka_moneris_t_check( 'description stored the same as on WooCommerce\'s screen: ' . $wpheka_moneris_t_text, $wpheka_moneris_t_woo === $wpheka_moneris_t_ours, $wpheka_moneris_t_woo . ' vs ' . $wpheka_moneris_t_ours );
}

// 9. Where links point, and the old screen's redirect (page views only).
wpheka_moneris_t_set_option( wpheka_moneris_t_live_settings() );
$wpheka_moneris_t_gw = wpheka_moneris_t_gateway();
wpheka_moneris_t_check( 'get_settings_url() is the new page', WPHEKA_Gateway_Moneris_Admin_Page::url() === $wpheka_moneris_t_gw->get_settings_url(), $wpheka_moneris_t_gw->get_settings_url() );
$wpheka_moneris_t_links = implode( ' ', WPHEKA_Moneris::get_instance()->plugin_action_links( array() ) );
wpheka_moneris_t_check( 'plugins screen Settings link is the new page', false !== strpos( $wpheka_moneris_t_links, 'page=wpheka-moneris' ), $wpheka_moneris_t_links );
if ( class_exists( '\Automattic\WooCommerce\Internal\Admin\Settings\PaymentsProviders' ) ) {
	$wpheka_moneris_t_provider = wc_get_container()->get( \Automattic\WooCommerce\Internal\Admin\Settings\PaymentsProviders::class )->get_payment_gateway_provider_instance( 'moneris' );
	// WooCommerce adds its own from= argument.
	wpheka_moneris_t_check( "WooCommerce Payments screen's Manage link is the new page", 0 === strpos( $wpheka_moneris_t_provider->get_settings_url( $wpheka_moneris_t_gw ), WPHEKA_Gateway_Moneris_Admin_Page::url() ), $wpheka_moneris_t_provider->get_settings_url( $wpheka_moneris_t_gw ) );
}

$wpheka_moneris_t_redirect = static function ( $method, $get ) {
	$_SERVER['REQUEST_METHOD'] = $method;
	$_GET                      = $get;
	$catch                     = static function ( $location ) {
		throw new RuntimeException( $location );
	};
	add_filter( 'wp_redirect', $catch, 1 );
	try {
		WPHEKA_Gateway_Moneris_Admin_Page::redirect_old_section();
		$out = '';
	} catch ( RuntimeException $e ) {
		$out = $e->getMessage();
	}
	remove_filter( 'wp_redirect', $catch, 1 );
	$_GET = array();
	unset( $_SERVER['REQUEST_METHOD'] );
	return $out;
};
foreach ( array( 'moneris', 'wpheka_gateway_moneris' ) as $wpheka_moneris_t_section ) {
	$wpheka_moneris_t_get = array(
		'page'    => 'wc-settings',
		'tab'     => 'checkout',
		'section' => $wpheka_moneris_t_section,
	);
	wpheka_moneris_t_check( "old section=$wpheka_moneris_t_section: a page view goes to the new page", WPHEKA_Gateway_Moneris_Admin_Page::url() === $wpheka_moneris_t_redirect( 'GET', $wpheka_moneris_t_get ) );
	wpheka_moneris_t_check( "old section=$wpheka_moneris_t_section: a post is left to WooCommerce", '' === $wpheka_moneris_t_redirect( 'POST', $wpheka_moneris_t_get ) );
}
wpheka_moneris_t_check(
	'other payment sections are not redirected',
	'' === $wpheka_moneris_t_redirect(
		'GET',
		array(
			'page'    => 'wc-settings',
			'tab'     => 'checkout',
			'section' => 'cod',
		)
	)
);
wp_set_current_user( 0 );
wpheka_moneris_t_check(
	'no redirect for a user without access',
	'' === $wpheka_moneris_t_redirect(
		'GET',
		array(
			'page'    => 'wc-settings',
			'tab'     => 'checkout',
			'section' => 'moneris',
		)
	)
);
wp_set_current_user( $wpheka_moneris_t_admin->ID );

// Put the store back as it was.
wpheka_moneris_t_set_option( $wpheka_moneris_t_before );
wpheka_moneris_t_check( 'option restored', wpheka_moneris_t_option() === $wpheka_moneris_t_before );
wpheka_moneris_t_check( 'settings.php: ran to the end', true );
