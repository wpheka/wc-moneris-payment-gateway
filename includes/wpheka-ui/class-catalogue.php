<?php
/**
 * WPHEKA admin UI kit: the "More from WPHEKA" plugin list.
 *
 * The list ships inside the plugin and is updated with each release: the screen
 * makes no remote request. Each card's button comes from what is installed here.
 *
 * @package WPHEKA_Gateway_Moneris\AdminUI
 */

namespace WPHEKA_Gateway_Moneris\AdminUI;

defined( 'ABSPATH' ) || exit;

/**
 * Catalogue.
 */
class Catalogue {

	/**
	 * The plugins, in display order. `file` is the main file name, which is the same
	 * whatever folder the plugin was installed into.
	 *
	 * @return array[]
	 */
	public static function items() {
		return array(
			array(
				'slug' => 'wpheka-edit-orders-for-woocommerce',
				'file' => 'wpheka-edit-orders-for-woocommerce.php',
				'name' => __( 'Edit Orders for WooCommerce', 'wc-moneris-payment-gateway' ),
				'cat'  => __( 'Order management', 'wc-moneris-payment-gateway' ),
				'text' => __( 'Edit paid orders and settle the difference with automatic refunds and pay links.', 'wc-moneris-payment-gateway' ),
			),
			array(
				'slug' => 'wc-moneris-payment-gateway-pro',
				'file' => 'wc-moneris-payment-gateway-pro.php',
				'icon' => 'wc-moneris-payment-gateway',
				'name' => __( 'Moneris Payment Gateway Pro', 'wc-moneris-payment-gateway' ),
				'cat'  => __( 'Payments · Canada', 'wc-moneris-payment-gateway' ),
				'text' => __( 'Subscriptions, saved cards, Moneris Checkout and 3-D Secure for Canadian stores.', 'wc-moneris-payment-gateway' ),
				/* translators: %s: price. */
				'paid' => sprintf( __( 'From %s/year', 'wc-moneris-payment-gateway' ), '$79' ),
				'url'  => 'https://www.wpheka.com/product/wc-moneris-payment-gateway-pro/',
			),
			array(
				'slug' => 'wc-search-orders-by-product',
				'file' => 'wc-search-orders-by-product.php',
				'name' => __( 'Search Orders By Product', 'wc-moneris-payment-gateway' ),
				'cat'  => __( 'Order management', 'wc-moneris-payment-gateway' ),
				'text' => __( 'Find every order that contains a product, variation, SKU or category.', 'wc-moneris-payment-gateway' ),
			),
			array(
				'slug' => 'wc-backorder-split',
				'file' => 'wc-backorder-split.php',
				'name' => __( 'WC Backorder Split', 'wc-moneris-payment-gateway' ),
				'cat'  => __( 'Order management', 'wc-moneris-payment-gateway' ),
				'text' => __( 'Ship what is in stock now and move backordered items to their own order.', 'wc-moneris-payment-gateway' ),
			),
			array(
				'slug' => 'wpheka-request-for-quote',
				'file' => 'wpheka-request-for-quote.php',
				'name' => __( 'Request For Quote', 'wc-moneris-payment-gateway' ),
				'cat'  => __( 'Sales', 'wc-moneris-payment-gateway' ),
				'text' => __( 'Let customers ask for a price with a quote list. Hide prices when you need to.', 'wc-moneris-payment-gateway' ),
			),
			array(
				'slug' => 'wpheka-web-server-information',
				'file' => 'wpheka-web-server-information.php',
				'name' => __( 'Web Server Information', 'wc-moneris-payment-gateway' ),
				'cat'  => __( 'Tools', 'wc-moneris-payment-gateway' ),
				'text' => __( 'Your server, PHP and database details on one screen, ready for support.', 'wc-moneris-payment-gateway' ),
			),
			array(
				'slug' => 'wc-moneris-payment-gateway',
				'file' => 'wc-moneris-payment-gateway.php',
				'name' => __( 'Moneris Payment Gateway', 'wc-moneris-payment-gateway' ),
				'cat'  => __( 'Payments · Canada', 'wc-moneris-payment-gateway' ),
				'text' => __( 'Take Moneris card payments on your own checkout, block or classic.', 'wc-moneris-payment-gateway' ),
			),
		);
	}

	/**
	 * The installed copy of a plugin, by main file name.
	 *
	 * @param string $file Main file name.
	 * @return string Plugin basename (folder/file.php), or '' when not installed.
	 */
	public static function installed( $file ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( array_keys( get_plugins() ) as $basename ) {
			if ( basename( $basename ) === $file ) {
				return $basename;
			}
		}

		return '';
	}

	/**
	 * The button for one card.
	 *
	 * @param array  $item   Catalogue item.
	 * @param string $source Plugin slug showing the list, for UTM tags.
	 * @return string HTML.
	 */
	public static function action( array $item, $source ) {
		if ( ! empty( $item['url'] ) ) {
			$url = add_query_arg(
				array(
					'utm_source'   => $source,
					'utm_medium'   => 'plugin-settings',
					'utm_campaign' => 'more-from-wpheka',
				),
				$item['url']
			);

			return '<a class="w-btn w-btn--primary w-btn--sm" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Learn more', 'wc-moneris-payment-gateway' ) . '<span class="w-sr"> ' . esc_html__( '(opens in a new tab)', 'wc-moneris-payment-gateway' ) . '</span></a>';
		}

		$basename = self::installed( $item['file'] );
		if ( $basename && is_plugin_active( $basename ) ) {
			return '<span class="w-installed">' . Icons::get( 'check', '2.4' ) . esc_html__( 'Installed', 'wc-moneris-payment-gateway' ) . '</span>';
		}
		if ( $basename ) {
			if ( ! current_user_can( 'activate_plugin', $basename ) ) {
				return '<span class="w-plugin-cat">' . esc_html__( 'Installed, not active', 'wc-moneris-payment-gateway' ) . '</span>';
			}
			$url = wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $basename ) ), 'activate-plugin_' . $basename );

			return '<a class="w-btn w-btn--secondary w-btn--sm" href="' . esc_url( $url ) . '">' . esc_html__( 'Activate', 'wc-moneris-payment-gateway' ) . '</a>';
		}
		if ( current_user_can( 'install_plugins' ) ) {
			$url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . rawurlencode( $item['slug'] ) ), 'install-plugin_' . $item['slug'] );

			return '<a class="w-btn w-btn--secondary w-btn--sm" href="' . esc_url( $url ) . '">' . esc_html__( 'Install now', 'wc-moneris-payment-gateway' ) . '</a>';
		}
		$url = 'https://wordpress.org/plugins/' . $item['slug'] . '/';

		return '<a class="w-btn w-btn--secondary w-btn--sm" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'wc-moneris-payment-gateway' ) . '</a>';
	}
}
