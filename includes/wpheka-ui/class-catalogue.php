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
				'name' => __( 'Edit Orders for WooCommerce', 'wpheka-gateway-moneris' ),
				'cat'  => __( 'Order management', 'wpheka-gateway-moneris' ),
				'text' => __( 'Edit paid orders and settle the difference with automatic refunds and pay links.', 'wpheka-gateway-moneris' ),
			),
			array(
				'slug' => 'wc-moneris-payment-gateway-pro',
				'file' => 'wc-moneris-payment-gateway-pro.php',
				'icon' => 'wc-moneris-payment-gateway',
				'name' => __( 'Moneris Payment Gateway Pro', 'wpheka-gateway-moneris' ),
				'cat'  => __( 'Payments · Canada', 'wpheka-gateway-moneris' ),
				'text' => __( 'Subscriptions, saved cards, Moneris Checkout and 3-D Secure for Canadian stores.', 'wpheka-gateway-moneris' ),
				/* translators: %s: price. */
				'paid' => sprintf( __( 'From %s/year', 'wpheka-gateway-moneris' ), '$79' ),
				'url'  => 'https://www.wpheka.com/product/wc-moneris-payment-gateway-pro/',
			),
			array(
				'slug' => 'wc-search-orders-by-product',
				'file' => 'wc-search-orders-by-product.php',
				'name' => __( 'Search Orders By Product', 'wpheka-gateway-moneris' ),
				'cat'  => __( 'Order management', 'wpheka-gateway-moneris' ),
				'text' => __( 'Find every order that contains a product, variation, SKU or category.', 'wpheka-gateway-moneris' ),
			),
			array(
				'slug' => 'wc-backorder-split',
				'file' => 'wc-backorder-split.php',
				'name' => __( 'WC Backorder Split', 'wpheka-gateway-moneris' ),
				'cat'  => __( 'Order management', 'wpheka-gateway-moneris' ),
				'text' => __( 'Ship what is in stock now and move backordered items to their own order.', 'wpheka-gateway-moneris' ),
			),
			array(
				'slug' => 'wpheka-request-for-quote',
				'file' => 'wpheka-request-for-quote.php',
				'name' => __( 'Request For Quote', 'wpheka-gateway-moneris' ),
				'cat'  => __( 'Sales', 'wpheka-gateway-moneris' ),
				'text' => __( 'Let customers ask for a price with a quote list. Hide prices when you need to.', 'wpheka-gateway-moneris' ),
			),
			array(
				'slug' => 'wpheka-web-server-information',
				'file' => 'wpheka-web-server-information.php',
				'name' => __( 'Web Server Information', 'wpheka-gateway-moneris' ),
				'cat'  => __( 'Tools', 'wpheka-gateway-moneris' ),
				'text' => __( 'Your server, PHP and database details on one screen, ready for support.', 'wpheka-gateway-moneris' ),
			),
			array(
				'slug' => 'wc-moneris-payment-gateway',
				'file' => 'wc-moneris-payment-gateway.php',
				'name' => __( 'Moneris Payment Gateway', 'wpheka-gateway-moneris' ),
				'cat'  => __( 'Payments · Canada', 'wpheka-gateway-moneris' ),
				'text' => __( 'Take Moneris card payments on your own checkout, block or classic.', 'wpheka-gateway-moneris' ),
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

			return '<a class="w-btn w-btn--primary w-btn--sm" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Learn more', 'wpheka-gateway-moneris' ) . '<span class="w-sr"> ' . esc_html__( '(opens in a new tab)', 'wpheka-gateway-moneris' ) . '</span></a>';
		}

		$basename = self::installed( $item['file'] );
		if ( $basename && is_plugin_active( $basename ) ) {
			return '<span class="w-installed">' . Icons::get( 'check', '2.4' ) . esc_html__( 'Installed', 'wpheka-gateway-moneris' ) . '</span>';
		}
		if ( $basename ) {
			if ( ! current_user_can( 'activate_plugin', $basename ) ) {
				return '<span class="w-plugin-cat">' . esc_html__( 'Installed, not active', 'wpheka-gateway-moneris' ) . '</span>';
			}
			$url = wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $basename ) ), 'activate-plugin_' . $basename );

			return '<a class="w-btn w-btn--secondary w-btn--sm" href="' . esc_url( $url ) . '">' . esc_html__( 'Activate', 'wpheka-gateway-moneris' ) . '</a>';
		}
		if ( current_user_can( 'install_plugins' ) ) {
			$url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . rawurlencode( $item['slug'] ) ), 'install-plugin_' . $item['slug'] );

			return '<a class="w-btn w-btn--secondary w-btn--sm" href="' . esc_url( $url ) . '">' . esc_html__( 'Install now', 'wpheka-gateway-moneris' ) . '</a>';
		}
		$url = 'https://wordpress.org/plugins/' . $item['slug'] . '/';

		return '<a class="w-btn w-btn--secondary w-btn--sm" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'wpheka-gateway-moneris' ) . '</a>';
	}
}
