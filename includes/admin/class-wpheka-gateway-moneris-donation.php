<?php
/**
 * WPHEKA Plugin Donation
 *
 * @class       WPHEKA_Gateway_Moneris_Donation
 * @version     2.0
 * @category    Class
 * @author      WPHEKA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPHEKA_Gateway_Moneris_Donation' ) ) {

	class WPHEKA_Gateway_Moneris_Donation {

		const DONATION_URL = 'https://www.paypal.me/AKSHAYASWAROOP';

		private static $version_meta = 'wpheka_gateway_moneris_donation_version';

		private static $action_nonce = 'wpheka-gateway-moneris-plugin-donation-ajax-nonce';

		public static function init() {
			self::hooks();
		}

		private static function hooks() {
			if ( self::show() ) {
				add_action( 'admin_notices', array( __CLASS__, 'donation_notice' ) );
				add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
				add_action( 'wp_ajax_wpheka_gateway_moneris_donation_dismiss_notice', array( __CLASS__, 'dismiss_notice' ) );
			}
			add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 3 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_styles' ) );
		}

		public static function admin_enqueue_styles() {
			wp_enqueue_style( 'wpheka_gateway_moneris_donation_css', plugins_url( 'assets/css/wpheka-plugin-donation-donation.css', WPHEKA_MONERIS_MAIN_FILE ), array(), WPHEKA_MONERIS_VERSION );
		}

		public static function admin_enqueue_scripts() {
			wp_enqueue_script( 'wpheka_gateway_moneris_donation_js', plugins_url( 'assets/js/wpheka-plugin-donation-donation.js', WPHEKA_MONERIS_MAIN_FILE ), array( 'jquery' ), WPHEKA_MONERIS_VERSION, true );
			wp_localize_script(
				'wpheka_gateway_moneris_donation_js',
				'wpheka_gateway_moneris_donation_js',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( self::$action_nonce ),
				)
			);
		}

		public static function donation_notice() {
			?>
			<div class="notice wpheka-plugin-donation-notice">
				<p><span class="wpheka-plugin-donation-plugin-name">{ <?php esc_html_e( 'WC Moneris Payment Gateway', 'wpheka-gateway-moneris' ); ?> }</span> <a href="<?php echo esc_url( self::DONATION_URL ); ?>" target="_blank" class="wpheka-plugin-donation-button"> <span class="dashicons dashicons-heart"></span> <?php echo esc_html_e( 'Make a donation', 'wpheka-gateway-moneris' ); ?></a></p>
				<button type="button" class="notice-dismiss wpheka-plugin-donation-button-notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
			</div>
			<?php
		}

		public static function dismiss_notice() {
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], self::$action_nonce ) ) {
				wp_die();
			}

			$user_id = get_current_user_id();
			if ( $user_id ) {
				update_user_meta( $user_id, self::$version_meta, WPHEKA_MONERIS_VERSION );
			}

			exit;
		}

		private static function show() {
			$version = null;
			$user_id = get_current_user_id();

			if ( $user_id ) {
				$version = get_user_meta( $user_id, self::$version_meta, true );

				// delete_user_meta( $user_id, self::$version_meta ); // to test the feature.
			}

			return is_admin() && $version !== WPHEKA_MONERIS_VERSION;
		}

		public static function plugin_row_meta( $plugin_meta, $plugin_file, $plugin_data ) {
			if ( strpos( $plugin_file, basename( WPHEKA_MONERIS_MAIN_FILE ) ) ) {
				$plugin_meta['wpheka_gateway_moneris_donation'] = sprintf( '<a href="%s" target="_blank" class="wpheka-plugin-donation-button"><span class="dashicons dashicons-heart"></span> %s</a>', self::DONATION_URL, esc_html__( 'Make a donation', 'wpheka-gateway-moneris' ) );
			}

			return $plugin_meta;
		}

	}

	add_action( 'admin_init', array( 'WPHEKA_Gateway_Moneris_Donation', 'init' ) );

}
