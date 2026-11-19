<?php
/**
 * Translations load under the plugin's slug text domain (3.10.0).
 *
 * A store's language is fixed when a request starts, so each stage runs as its own request,
 * the way a French store would load the plugin:
 *
 *   setup    writes a minimal core fr_CA pack (WordPress only accepts an installed language),
 *            the plugin pack named exactly as wordpress.org names it,
 *            WP_LANG_DIR/plugins/wc-moneris-payment-gateway-fr_CA.mo, and the block script's
 *            JSON, then sets the site language to fr_CA
 *   fr       checks every place 3.10.0 changed: a settings label, the default checkout
 *            description, the deactivation dialog (reasons, prompts and the "Processing..."
 *            text inside its inline script, with an apostrophe that must not break it) and the
 *            checkout block script
 *   old      swaps the pack for one under the old domain wpheka-gateway-moneris
 *   old-fr   checks that the old-domain pack is not used
 *   cleanup  restores the site language exactly and removes every file written
 *   en       checks the dialog reads as before in English
 *
 * Run by run.sh:  wp eval-file .../tests/functional/i18n.php <stage>
 *
 * @package WPHEKA_Gateway_Moneris
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/lib.php';

$wpheka_moneris_t_stage  = isset( $args[0] ) ? $args[0] : '';
$wpheka_moneris_t_dir    = WP_LANG_DIR . '/plugins';
$wpheka_moneris_t_core   = WP_LANG_DIR . '/fr_CA.mo';
$wpheka_moneris_t_mo     = $wpheka_moneris_t_dir . '/wc-moneris-payment-gateway-fr_CA.mo';
$wpheka_moneris_t_old_mo = $wpheka_moneris_t_dir . '/wpheka-gateway-moneris-fr_CA.mo';
$wpheka_moneris_t_json   = $wpheka_moneris_t_dir . '/wc-moneris-payment-gateway-fr_CA-' . md5( 'assets/js/block/frontend/payment-block.js' ) . '.json';
$wpheka_moneris_t_state  = 'wpheka_moneris_t_i18n_state';

$wpheka_moneris_t_fr = array(
	'Enable/Disable'                         => 'Activer/Désactiver',
	'Pay with your credit card via Moneris.' => 'Payez par carte de crédit avec Moneris.',
	'I found a better plugin'                => 'J’ai trouvé une meilleure extension',
	'Which plugin?'                          => 'Quelle extension?',
	// An apostrophe on purpose: it must not end the JavaScript string it is printed into.
	'Processing...'                          => "Traitement de l'envoi...",
);

/**
 * Writes a .mo file holding the given translations.
 *
 * @param string $path         Target file.
 * @param array  $translations Original => translation.
 * @return bool
 */
function wpheka_moneris_t_write_mo( $path, $translations ) {
	require_once ABSPATH . WPINC . '/pomo/mo.php';
	$mo = new MO();
	foreach ( $translations as $original => $translation ) {
		$mo->add_entry(
			new Translation_Entry(
				array(
					'singular'     => $original,
					'translations' => array( $translation ),
				)
			)
		);
	}
	return $mo->export_to_file( $path );
}

/**
 * Reads the stored WPLANG row. get_option() cannot tell a missing row from en_US, because
 * WordPress registers en_US as the default.
 *
 * @return string|null The stored value, or null when there is no row.
 */
function wpheka_moneris_t_get_wplang() {
	global $wpdb;
	return $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'WPLANG' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- test fixture.
}

/**
 * Writes the WPLANG row directly, bypassing sanitize_option(), so it is restored exactly.
 *
 * @param string|null $value New value, or null for no row.
 */
function wpheka_moneris_t_set_wplang( $value ) {
	global $wpdb;
	if ( null === $value ) {
		$wpdb->delete( $wpdb->options, array( 'option_name' => 'WPLANG' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- test fixture.
	} elseif ( null === wpheka_moneris_t_get_wplang() ) {
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- test fixture.
			$wpdb->options,
			array(
				'option_name'  => 'WPLANG',
				'option_value' => $value,
				'autoload'     => 'on',
			)
		);
	} else {
		$wpdb->update( $wpdb->options, array( 'option_value' => $value ), array( 'option_name' => 'WPLANG' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- test fixture.
	}
	wp_cache_delete( 'alloptions', 'options' );
	wp_cache_delete( 'notoptions', 'options' );
	wp_cache_delete( 'WPLANG', 'options' );
}

/**
 * Renders the deactivation dialog as on the Plugins screen.
 *
 * @return string
 */
function wpheka_moneris_t_dialog() {
	if ( ! class_exists( 'WPHEKA_Deactivation_Tracker' ) ) {
		require_once WPHEKA_MONERIS_PLUGIN_PATH . '/includes/admin/class-wpheka-gateway-moneris-deactivation.php';
	}
	$GLOBALS['pagenow'] = 'plugins.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- render as on the Plugins screen.
	ob_start();
	WPHEKA_Deactivation_Tracker::deactivate_scripts();
	return ob_get_clean();
}

switch ( $wpheka_moneris_t_stage ) {
	case 'setup':
		$wpheka_moneris_t_saved = wpheka_moneris_t_get_wplang();
		update_option(
			$wpheka_moneris_t_state,
			array(
				'wplang'    => $wpheka_moneris_t_saved,
				'made_lang' => ! is_dir( WP_LANG_DIR ),
				'made_dir'  => ! is_dir( $wpheka_moneris_t_dir ),
			),
			false
		);
		wp_mkdir_p( $wpheka_moneris_t_dir );
		$wpheka_moneris_t_ok = wpheka_moneris_t_write_mo( $wpheka_moneris_t_core, array( 'Settings' => 'Réglages' ) )
			&& wpheka_moneris_t_write_mo( $wpheka_moneris_t_mo, $wpheka_moneris_t_fr )
			&& false !== file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.
				$wpheka_moneris_t_json,
				wp_json_encode(
					array(
						'domain'      => 'messages',
						'locale_data' => array(
							'messages' => array(
								''            => array(
									'domain' => 'messages',
									'lang'   => 'fr_CA',
								),
								'Card Number' => array( 'Numéro de carte' ),
							),
						),
					)
				)
			);
		wpheka_moneris_t_set_wplang( 'fr_CA' );
		wpheka_moneris_t_check( 'i18n set-up: fr_CA packs written under the slug name, site language fr_CA', $wpheka_moneris_t_ok && 'fr_CA' === get_option( 'WPLANG' ) );
		break;

	case 'fr':
		wpheka_moneris_t_check( 'fr_CA: request runs in French', 'fr_CA' === determine_locale(), determine_locale() );
		$wpheka_moneris_t_gateway = new WPHEKA_Gateway_Moneris();
		$wpheka_moneris_t_gateway->init_form_fields();
		$wpheka_moneris_t_label = isset( $wpheka_moneris_t_gateway->form_fields['enabled']['title'] ) ? $wpheka_moneris_t_gateway->form_fields['enabled']['title'] : '';
		wpheka_moneris_t_check( 'fr_CA: the plugin\'s settings label translated from the wordpress.org-named pack', 'Activer/Désactiver' === $wpheka_moneris_t_label, $wpheka_moneris_t_label );

		$wpheka_moneris_t_default = isset( $wpheka_moneris_t_gateway->form_fields['description']['default'] ) ? $wpheka_moneris_t_gateway->form_fields['description']['default'] : '';
		wpheka_moneris_t_check( 'fr_CA: default checkout description translated', 'Payez par carte de crédit avec Moneris.' === $wpheka_moneris_t_default, $wpheka_moneris_t_default );

		$wpheka_moneris_t_html = wpheka_moneris_t_dialog();
		wpheka_moneris_t_check( 'fr_CA: dialog shows all 7 reasons', 7 === substr_count( $wpheka_moneris_t_html, 'name="deactivation_reason"' ), (string) substr_count( $wpheka_moneris_t_html, 'name="deactivation_reason"' ) );
		wpheka_moneris_t_check( 'fr_CA: dialog reason translated', false !== strpos( $wpheka_moneris_t_html, esc_html( 'J’ai trouvé une meilleure extension' ) ) );
		wpheka_moneris_t_check( 'fr_CA: dialog prompt translated', false !== strpos( $wpheka_moneris_t_html, 'data-placeholder="Quelle extension?"' ) );
		wpheka_moneris_t_check( 'fr_CA: button text translated and escaped for its JavaScript string', false !== strpos( $wpheka_moneris_t_html, "button.text( 'Traitement de l\\'envoi...' );" ) );
		wpheka_moneris_t_check( 'fr_CA: no raw apostrophe ends the button text string early', false === strpos( $wpheka_moneris_t_html, "'Traitement de l'envoi...'" ) );

		if ( class_exists( 'Woocommerce_Gateway_Moneris_Blocks_Support' ) ) {
			( new Woocommerce_Gateway_Moneris_Blocks_Support() )->get_payment_method_script_handles();
		}
		$wpheka_moneris_t_script = wp_scripts()->query( 'wpheka-gateway-moneris-payment-blocks', 'registered' );
		wpheka_moneris_t_check( 'block script uses the slug text domain', $wpheka_moneris_t_script && 'wc-moneris-payment-gateway' === $wpheka_moneris_t_script->textdomain, $wpheka_moneris_t_script ? (string) $wpheka_moneris_t_script->textdomain : 'not registered' );
		$wpheka_moneris_t_loaded = load_script_textdomain( 'wpheka-gateway-moneris-payment-blocks', 'wc-moneris-payment-gateway' );
		$wpheka_moneris_t_data = is_string( $wpheka_moneris_t_loaded ) ? json_decode( $wpheka_moneris_t_loaded, true ) : null;
		$wpheka_moneris_t_card = isset( $wpheka_moneris_t_data['locale_data']['messages']['Card Number'][0] ) ? $wpheka_moneris_t_data['locale_data']['messages']['Card Number'][0] : '';
		wpheka_moneris_t_check( 'fr_CA: block script translations found', 'Numéro de carte' === $wpheka_moneris_t_card, is_string( $wpheka_moneris_t_loaded ) ? $wpheka_moneris_t_card : 'none' );
		wpheka_moneris_t_check( 'i18n.php fr: ran to the end', true );
		break;

	case 'old':
		$wpheka_moneris_t_ok = unlink( $wpheka_moneris_t_mo ) && wpheka_moneris_t_write_mo( $wpheka_moneris_t_old_mo, $wpheka_moneris_t_fr ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture.
		wpheka_moneris_t_check( 'i18n: pack swapped for one under the old domain', $wpheka_moneris_t_ok );
		break;

	case 'old-fr':
		wpheka_moneris_t_check( 'fr_CA: a pack under the old domain is not used', 'fr_CA' === determine_locale() && 'Enable/Disable' === __( 'Enable/Disable', 'wc-moneris-payment-gateway' ) );
		break;

	case 'cleanup':
		$wpheka_moneris_t_saved = get_option( $wpheka_moneris_t_state );
		if ( is_array( $wpheka_moneris_t_saved ) ) {
			wpheka_moneris_t_set_wplang( $wpheka_moneris_t_saved['wplang'] );
		}
		foreach ( array( $wpheka_moneris_t_core, $wpheka_moneris_t_mo, $wpheka_moneris_t_old_mo, $wpheka_moneris_t_json ) as $wpheka_moneris_t_file ) {
			if ( file_exists( $wpheka_moneris_t_file ) ) {
				unlink( $wpheka_moneris_t_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture.
			}
		}
		if ( is_array( $wpheka_moneris_t_saved ) && $wpheka_moneris_t_saved['made_dir'] ) {
			rmdir( $wpheka_moneris_t_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test fixture.
		}
		if ( is_array( $wpheka_moneris_t_saved ) && $wpheka_moneris_t_saved['made_lang'] ) {
			rmdir( WP_LANG_DIR ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test fixture.
		}
		delete_option( $wpheka_moneris_t_state );
		wpheka_moneris_t_check(
			'i18n clean-up: site language restored and test packs removed',
			is_array( $wpheka_moneris_t_saved ) && wpheka_moneris_t_get_wplang() === $wpheka_moneris_t_saved['wplang'] && ! file_exists( $wpheka_moneris_t_core ) && ! file_exists( $wpheka_moneris_t_mo ) && ! file_exists( $wpheka_moneris_t_old_mo ) && ! file_exists( $wpheka_moneris_t_json )
		);
		break;

	case 'en':
		$wpheka_moneris_t_html = wpheka_moneris_t_dialog();
		wpheka_moneris_t_check( 'en_US: dialog reason and button text in English', false !== strpos( $wpheka_moneris_t_html, 'I found a better plugin' ) && false !== strpos( $wpheka_moneris_t_html, "button.text( 'Processing...' );" ) );
		break;

	default:
		wpheka_moneris_t_check( 'i18n.php: known stage', false, $wpheka_moneris_t_stage );
}
