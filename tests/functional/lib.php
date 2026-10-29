<?php
/**
 * Helpers shared by the functional checks (run by run.sh through WP-CLI on the test store).
 *
 * Each check prints "PASS|name" or "FAIL|name -- detail" and appends the same line to the
 * results file run.sh reads, so every case is counted once.
 *
 * @package WPHEKA_Gateway_Moneris
 */

defined( 'ABSPATH' ) || exit;

// WP-CLI is not an admin request, so the plugin did not load the admin UI kit.
if ( class_exists( 'WPHEKA_Gateway_Moneris_Admin_Page' ) && ! class_exists( 'WPHEKA_Gateway_Moneris\\AdminUI\\Page' ) ) {
	require_once dirname( __DIR__, 2 ) . '/includes/wpheka-ui/load.php';
}

// The test store sends no mail.
add_filter( 'pre_wp_mail', '__return_true' );

/**
 * Record one case.
 *
 * @param string $name   Case name.
 * @param bool   $ok     Passed.
 * @param string $detail Why it failed.
 */
function wpheka_moneris_t_check( $name, $ok, $detail = '' ) {
	$line = ( $ok ? 'PASS|' : 'FAIL|' ) . $name . ( $ok || '' === $detail ? '' : ' -- ' . $detail );
	echo $line . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
	$file = getenv( 'MONERIS_RESULTS' );
	if ( $file ) {
		file_put_contents( $file, $line . "\n", FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test results file.
	}
}

/**
 * A short readable form of a value for failure details.
 *
 * @param mixed $value Value.
 * @return string
 */
function wpheka_moneris_t_dump( $value ) {
	return wp_json_encode( $value );
}

/**
 * The live gateway, rebuilt so it reads the option as it is now.
 *
 * @return WPHEKA_Gateway_Moneris
 */
function wpheka_moneris_t_gateway() {
	WC()->payment_gateways()->init();
	$gateways = WC()->payment_gateways()->payment_gateways();

	return $gateways['moneris'];
}

/**
 * The settings option exactly as stored (null when missing).
 *
 * @return mixed
 */
function wpheka_moneris_t_option() {
	wp_cache_delete( 'woocommerce_moneris_settings', 'options' );
	wp_cache_delete( 'alloptions', 'options' );

	return get_option( 'woocommerce_moneris_settings', null );
}

/**
 * Store the settings option (null deletes it) and rebuild the gateway.
 *
 * @param array|null $value Option value.
 */
function wpheka_moneris_t_set_option( $value ) {
	if ( null === $value ) {
		delete_option( 'woocommerce_moneris_settings' );
	} else {
		update_option( 'woocommerce_moneris_settings', $value );
	}
	wpheka_moneris_t_gateway();
}

/**
 * Render a tab of the WPHEKA > Moneris page as the current user and return the HTML.
 *
 * @param string $tab Tab.
 * @return string
 */
function wpheka_moneris_t_render( $tab = 'settings' ) {
	$_GET = array(
		'page' => WPHEKA_Gateway_Moneris_Admin_Page::PAGE,
		'tab'  => $tab,
	);
	wpheka_moneris_t_gateway();
	ob_start();
	WPHEKA_Gateway_Moneris_Admin_Page::render();
	$html = ob_get_clean();
	$_GET = array();

	return $html;
}

/**
 * What a browser would post for the settings form in some HTML: every named control in
 * the form, unticked checkboxes left out, "name[]" collected into a list. Values are
 * slashed, as WordPress hands $_POST to a handler.
 *
 * @param string $html Page HTML.
 * @param string $form XPath of the form; the page's settings form when empty.
 * @return array
 */
function wpheka_moneris_t_form_post( $html, $form = '' ) {
	$form = '' !== $form ? $form : '//form[@id="' . WPHEKA_Gateway_Moneris_Admin_Page::FORM . '"]';
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
	libxml_clear_errors();
	$xpath = new DOMXPath( $doc );
	$post  = array();

	foreach ( $xpath->query( $form . '//input[@name] | ' . $form . '//select[@name] | ' . $form . '//textarea[@name]' ) as $el ) {
		$name = $el->getAttribute( 'name' );
		$type = strtolower( $el->getAttribute( 'type' ) );
		if ( in_array( $type, array( 'checkbox', 'radio' ), true ) && ! $el->hasAttribute( 'checked' ) ) {
			continue;
		}
		if ( 'select' === $el->nodeName && '[]' === substr( $name, -2 ) ) {
			foreach ( $el->getElementsByTagName( 'option' ) as $opt ) {
				if ( $opt->hasAttribute( 'selected' ) ) {
					$post[ substr( $name, 0, -2 ) ][] = $opt->getAttribute( 'value' );
				}
			}
			continue;
		} elseif ( 'select' === $el->nodeName ) {
			$value = '';
			foreach ( $el->getElementsByTagName( 'option' ) as $opt ) {
				if ( $opt->hasAttribute( 'selected' ) ) {
					$value = $opt->getAttribute( 'value' );
				}
			}
		} elseif ( 'textarea' === $el->nodeName ) {
			$value = $el->textContent;
		} else {
			$value = $el->getAttribute( 'value' );
		}
		if ( '[]' === substr( $name, -2 ) ) {
			$post[ substr( $name, 0, -2 ) ][] = $value;
		} else {
			$post[ $name ] = $value;
		}
	}

	return wp_slash( $post );
}

/**
 * Run the page's real save handler with a post, as the current user. The redirect at the
 * end is caught instead of sent.
 *
 * @param array $post Slashed post, with action and nonce.
 * @return string The URL it redirected to, or a message starting "died:".
 */
function wpheka_moneris_t_save( array $post ) {
	$_POST    = $post;
	$_REQUEST = $post;
	$catch    = static function ( $location ) {
		throw new RuntimeException( 'redirect:' . $location );
	};
	$die      = static function () {
		return static function ( $message ) {
			throw new RuntimeException( 'died:' . ( is_wp_error( $message ) ? $message->get_error_message() : (string) $message ) );
		};
	};
	add_filter( 'wp_redirect', $catch, 1 );
	// Last, so it wins over WP-CLI's own handler.
	add_filter( 'wp_die_handler', $die, PHP_INT_MAX );
	try {
		WPHEKA_Gateway_Moneris_Admin_Page::handle_save();
		$out = 'returned';
	} catch ( RuntimeException $e ) {
		$out = $e->getMessage();
	}
	remove_filter( 'wp_redirect', $catch, 1 );
	remove_filter( 'wp_die_handler', $die, PHP_INT_MAX );
	$_POST    = array();
	$_REQUEST = array();

	return 0 === strpos( $out, 'redirect:' ) ? substr( $out, 9 ) : $out;
}

/**
 * Render the Settings tab, then post it back unchanged (or with changes) through the
 * real handler.
 *
 * @param callable|null $change Gets the post array by reference before it is sent.
 * @return string Where the handler redirected.
 */
function wpheka_moneris_t_round_trip( $change = null ) {
	$post = wpheka_moneris_t_form_post( wpheka_moneris_t_render( 'settings' ) );
	if ( $change ) {
		$change( $post );
	}
	$out = wpheka_moneris_t_save( $post );
	wpheka_moneris_t_gateway();

	return $out;
}

/**
 * Settings as 3.8.1 saved them for a live store, plus keys Moneris Pro leaves behind.
 *
 * @return array
 */
function wpheka_moneris_t_live_settings() {
	return array(
		'enabled'         => 'yes',
		'title'           => 'Credit card (Moneris)',
		// As WooCommerce's screen stores it: wp_kses_post() saves & as &amp;.
		'description'     => 'Pay securely with Visa, MasterCard or Amex. "Quotes" &amp; <b>bold</b>',
		'store_id'        => 'monca01234',
		'api_token'       => 'Live1Token',
		'preferred_cards' => array( 'visa', 'mastercard', 'amex' ),
		'sandbox'         => 'no',
		'logging'         => 'no',
		'store_id_cad'    => 'pro-cad-store',
		'api_token_cad'   => 'pro-cad-token',
	);
}
