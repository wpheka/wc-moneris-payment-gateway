<?php
/**
 * WPHEKA > Moneris: the gateway's own page, with Settings, Transactions and Help tabs.
 *
 * The page has no settings of its own. It shows the values of the live Moneris gateway and
 * saves through the gateway's own process_admin_options(), so the option, its keys and
 * their formats stay exactly as WooCommerce's settings screen left them.
 *
 * Loaded on PHP 7.4 and later only (the admin UI kit needs it). Below that the gateway keeps
 * its WooCommerce settings screen.
 *
 * @package WPHEKA_Gateway_Moneris
 */

defined( 'ABSPATH' ) || exit;

use WPHEKA_Gateway_Moneris\AdminUI\Fields;
use WPHEKA_Gateway_Moneris\AdminUI\Icons;
use WPHEKA_Gateway_Moneris\AdminUI\Menu;
use WPHEKA_Gateway_Moneris\AdminUI\Page;

/**
 * WPHEKA_Gateway_Moneris_Admin_Page Class.
 */
class WPHEKA_Gateway_Moneris_Admin_Page {

	/**
	 * Page slug.
	 */
	const PAGE = 'wpheka-moneris';

	/**
	 * Capability for the page and the save: the one WooCommerce's settings need, so shop
	 * managers keep editing Moneris as they could before.
	 */
	const CAPABILITY = 'manage_woocommerce';

	/**
	 * The admin-post action that saves the settings.
	 */
	const SAVE_ACTION = 'wpheka_moneris_save_settings';

	/**
	 * The admin-post action that hides the getting-started card.
	 */
	const DISMISS_ACTION = 'wpheka_moneris_dismiss_start';

	/**
	 * User meta that records the getting-started card was hidden.
	 */
	const DISMISS_META = 'wpheka_moneris_start_dismissed';

	/**
	 * Transient holding the stats for an hour.
	 */
	const STATS_TRANSIENT = 'wpheka_moneris_stats';

	/**
	 * The wordpress.org slug.
	 */
	const SLUG = 'wc-moneris-payment-gateway';

	/**
	 * The form id.
	 */
	const FORM = 'wpheka-moneris-settings';

	/**
	 * A hidden field posted last in the form. PHP drops the end of a post that is cut short
	 * (max_input_vars, a proxy limit), so without it nothing is saved.
	 */
	const FORM_END = 'wpheka_moneris_form_end';

	/**
	 * The gateway fields this page draws. A field missing from a post is blanked by
	 * WooCommerce, so any other field (added by other code) keeps its saved value.
	 *
	 * @var string[]
	 */
	private static $fields = array( 'enabled', 'title', 'description', 'preferred_cards', 'sandbox', 'store_id', 'api_token', 'logging' );

	/**
	 * The page hook WordPress returned, for loading assets on this page only.
	 *
	 * @var string
	 */
	private static $hook = '';

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_' . self::DISMISS_ACTION, array( __CLASS__, 'handle_dismiss' ) );
		add_action( 'load-woocommerce_page_wc-settings', array( __CLASS__, 'redirect_old_section' ) );
	}

	/**
	 * The stats are cached for an hour; a new payment, refund or status change clears them
	 * so it shows at once. Hooked on every request: customers pay on the storefront.
	 */
	public static function watch_stats() {
		foreach ( array( 'woocommerce_payment_complete', 'woocommerce_order_refunded', 'woocommerce_order_status_changed' ) as $event ) {
			add_action( $event, array( __CLASS__, 'clear_stats' ) );
		}
	}

	/**
	 * Forget the cached stats.
	 */
	public static function clear_stats() {
		delete_transient( self::STATS_TRANSIENT );
	}

	/**
	 * URL of a tab.
	 *
	 * @param string $tab  settings, transactions or help.
	 * @param array  $args Extra query args.
	 * @return string
	 */
	public static function url( $tab = 'settings', array $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => self::PAGE,
					'tab'  => $tab,
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * The live Moneris gateway WooCommerce built, or null.
	 *
	 * @return WPHEKA_Gateway_Moneris|null
	 */
	public static function gateway() {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return null;
		}
		$gateways = WC()->payment_gateways()->payment_gateways();

		return isset( $gateways['moneris'] ) && $gateways['moneris'] instanceof WPHEKA_Gateway_Moneris ? $gateways['moneris'] : null;
	}

	/**
	 * Register the page under the shared WPHEKA menu.
	 */
	public static function register_page() {
		$hook = Menu::add_page( __( 'Moneris', 'wpheka-gateway-moneris' ), __( 'Moneris', 'wpheka-gateway-moneris' ), self::CAPABILITY, self::PAGE, array( __CLASS__, 'render' ), Icons::menu_icon() );
		if ( ! $hook ) {
			// A site hid the WPHEKA menu (wpheka_plugin_panel_menu_page_show): every link
			// still points at this page, so it lives under WooCommerce instead.
			$hook = add_submenu_page( 'woocommerce', __( 'Moneris', 'wpheka-gateway-moneris' ), __( 'Moneris', 'wpheka-gateway-moneris' ), self::CAPABILITY, self::PAGE, array( __CLASS__, 'render' ) );
		}

		self::$hook = is_string( $hook ) ? $hook : '';
		if ( '' !== self::$hook ) {
			add_action( 'load-' . self::$hook, array( Page::class, 'quiet_notices' ) );
		}
	}

	/**
	 * Load the kit on this page only.
	 *
	 * @param string $hook Page hook.
	 */
	public static function enqueue( $hook ) {
		if ( '' === self::$hook || self::$hook !== $hook ) {
			return;
		}
		Page::enqueue( 'wpheka-gateway-moneris', plugins_url( 'assets/wpheka-ui/', WPHEKA_MONERIS_MAIN_FILE ) );
		wp_add_inline_style( 'wpheka-gateway-moneris-wpheka-ui', '.wpheka-moneris-notes{margin:0;padding:var(--w-s5) var(--w-s5) var(--w-s5) calc(var(--w-s5) + 18px);list-style:disc}.wpheka-moneris-notes li{margin:0 0 var(--w-s2);color:var(--w-text-2)}.wpheka-moneris-notes li:last-child{margin:0}.wpheka-moneris-pro-group{margin:0 0 var(--w-s2);color:#fff;font-size:13px;font-weight:600}.w-procard .w-ticks{margin-bottom:var(--w-s4)}.w-procard .w-ticks strong{color:#fff}' );
	}

	/**
	 * Send visits to WooCommerce > Settings > Payments > Moneris to this page.
	 *
	 * Only page views: WooCommerce has already saved a post to that screen (on wp_loaded)
	 * before this runs, and a post from a tab left open there still saves as it always did.
	 */
	public static function redirect_old_section() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- which screen is being viewed.
		$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( 'checkout' !== $tab || ! in_array( $section, array( 'moneris', 'wpheka_gateway_moneris' ), true ) ) {
			return;
		}
		if ( ! current_user_can( self::CAPABILITY ) || ! self::gateway() ) {
			return;
		}

		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * The values to hand the gateway's process_admin_options(), built from a post.
	 *
	 * Fields this page draws are taken from the post as WooCommerce would take them (raw,
	 * so the gateway's own validation runs unchanged; an unticked switch posts nothing and
	 * saves "no"). Any other field keeps its saved value instead of being blanked.
	 *
	 * @param WC_Payment_Gateway $gateway The gateway.
	 * @param array              $posted  The post, still slashed.
	 * @return array|WP_Error
	 */
	public static function post_data( $gateway, array $posted ) {
		$data       = array();
		$incomplete = new WP_Error( 'wpheka_moneris_incomplete', __( 'The settings did not arrive complete, so nothing was saved. Please try again.', 'wpheka-gateway-moneris' ) );

		if ( empty( $posted[ self::FORM_END ] ) ) {
			return $incomplete;
		}

		foreach ( $gateway->get_form_fields() as $key => $field ) {
			$name = $gateway->get_field_key( $key );
			$type = isset( $field['type'] ) ? $field['type'] : 'text';

			if ( in_array( $key, self::$fields, true ) ) {
				if ( isset( $posted[ $name ] ) ) {
					$data[ $name ] = $posted[ $name ];
				} elseif ( 'checkbox' !== $type ) {
					// Every other field on the form always posts. Missing means the post was cut short.
					return $incomplete;
				}
				continue;
			}

			if ( 'title' === $type ) {
				continue;
			}
			$value = $gateway->get_option( $key );
			if ( 'checkbox' === $type ) {
				if ( 'yes' === $value ) {
					$data[ $name ] = '1';
				}
			} else {
				$data[ $name ] = wp_slash( $value );
			}
		}

		return $data;
	}

	/**
	 * Save through the gateway as WooCommerce's own screen does: fire the gateway's update
	 * action (the gateway saves on it, and other code may listen), then reload the gateways.
	 * For the length of the save $_POST holds the complete values, so any listener, or a
	 * second copy of the gateway, sees whole settings and never blanks one.
	 *
	 * @param WC_Payment_Gateway $gateway The live gateway.
	 * @param array              $data    Complete, slashed values from post_data().
	 */
	private static function save_gateway( $gateway, array $data ) {
		$action = 'woocommerce_update_options_payment_gateways_' . $gateway->id;
		$posted = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the caller checked the nonce; restored below.

		$_POST = $data;
		$gateway->set_post_data( $data );
		if ( has_action( $action, array( $gateway, 'process_admin_options' ) ) ) {
			do_action( $action ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- WooCommerce's own gateway save hook, fired as its settings screen fires it.
		} else {
			$gateway->process_admin_options();
		}
		$gateway->set_post_data( array() );
		$_POST = $posted;

		WC()->payment_gateways()->init();
	}

	/**
	 * Save the settings form (admin-post).
	 */
	public static function handle_save() {
		check_admin_referer( self::SAVE_ACTION );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'wpheka-gateway-moneris' ), '', array( 'response' => 403 ) );
		}

		$gateway = self::gateway();
		$data    = $gateway ? self::post_data( $gateway, $_POST ) : new WP_Error( 'wpheka_moneris_no_gateway', __( 'WooCommerce did not load the Moneris gateway, so nothing was saved.', 'wpheka-gateway-moneris' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated field by field by the gateway, as on WooCommerce's screen.
		$errors  = array();

		if ( is_wp_error( $data ) ) {
			$errors[] = $data->get_error_message();
		} else {
			self::save_gateway( $gateway, $data );
			$errors = $gateway->get_errors();
		}

		if ( $errors ) {
			set_transient( 'wpheka_moneris_save_error_' . get_current_user_id(), implode( ' ', array_map( 'wp_strip_all_tags', $errors ) ), MINUTE_IN_SECONDS );
			wp_safe_redirect( self::url( 'settings', array( 'w-error' => 1 ) ) );
			exit;
		}

		wp_safe_redirect( self::url( 'settings', array( 'w-saved' => 1 ) ) );
		exit;
	}

	/**
	 * Hide the getting-started card for this user (admin-post, also called by fetch()).
	 */
	public static function handle_dismiss() {
		check_admin_referer( self::DISMISS_ACTION );
		if ( current_user_can( self::CAPABILITY ) ) {
			update_user_meta( get_current_user_id(), self::DISMISS_META, 1 );
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Links for the header, the rail and the Help tab.
	 *
	 * @return array
	 */
	private static function links() {
		return array(
			'docs'    => 'https://www.wpheka.com/product/wc-moneris-payment-gateway/',
			'support' => 'https://wordpress.org/support/plugin/' . self::SLUG . '/',
			'changes' => 'https://wordpress.org/plugins/' . self::SLUG . '/#developers',
			'review'  => 'https://wordpress.org/support/plugin/' . self::SLUG . '/reviews/#new-post',
			'pro'     => 'https://www.wpheka.com/product/wc-moneris-payment-gateway-pro/?utm_source=moneris-free&utm_medium=settings&utm_campaign=upgrade',
			'testing' => 'https://developer.moneris.com/More/Testing/Testing%20a%20Solution',
			'logs'    => admin_url( 'admin.php?page=wc-status&tab=logs' ),
		);
	}

	/**
	 * The tab being shown.
	 *
	 * @return string
	 */
	private static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which tab to show.

		return in_array( $tab, array( 'settings', 'transactions', 'help' ), true ) ? $tab : 'settings';
	}

	/**
	 * The page.
	 */
	public static function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$gateway = self::gateway();
		$links   = self::links();
		$tab     = self::current_tab();
		$sandbox = $gateway && 'yes' === $gateway->get_option( 'sandbox' );
		$badges  = array( array( __( 'Free', 'wpheka-gateway-moneris' ), 'mint' ) );
		if ( $sandbox ) {
			$badges[] = array( __( 'Sandbox', 'wpheka-gateway-moneris' ), 'violet' );
		}

		Page::open(
			array(
				'icon'     => plugins_url( 'assets/wpheka-ui/icons/' . self::SLUG . '.png', WPHEKA_MONERIS_MAIN_FILE ),
				'name'     => __( 'Moneris Payment Gateway', 'wpheka-gateway-moneris' ),
				'version'  => WPHEKA_MONERIS_VERSION,
				'badges'   => $badges,
				'subtitle' => __( 'Take card payments through your Moneris merchant account.', 'wpheka-gateway-moneris' ),
				'crumb'    => __( 'Moneris', 'wpheka-gateway-moneris' ),
				'links'    => array(
					array( __( 'Docs', 'wpheka-gateway-moneris' ), $links['docs'], 'book' ),
					array( __( 'Support', 'wpheka-gateway-moneris' ), $links['support'], 'chat' ),
					array( __( "What's new", 'wpheka-gateway-moneris' ), $links['changes'], 'sparkle' ),
				),
				'cta'      => array( __( 'Upgrade to Pro', 'wpheka-gateway-moneris' ), $links['pro'], 'bolt', 'pro' ),
				'tabs'     => array(
					array( __( 'Settings', 'wpheka-gateway-moneris' ), self::url( 'settings' ), 'settings' === $tab ),
					array( __( 'Transactions', 'wpheka-gateway-moneris' ), self::url( 'transactions' ), 'transactions' === $tab ),
					array( __( 'Help', 'wpheka-gateway-moneris' ), self::url( 'help' ), 'help' === $tab ),
				),
			)
		);

		Page::notices( self::notices( $gateway ) );

		$error = '';
		if ( $gateway ) {
			if ( 'help' !== $tab ) {
				self::render_stats( $gateway );
			}
			if ( 'settings' === $tab ) {
				self::render_settings( $gateway, $links );
			} elseif ( 'transactions' === $tab ) {
				self::render_transactions();
			} else {
				self::render_help( $links );
			}
		}

		if ( 'settings' === $tab && isset( $_GET['w-error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag after our own redirect.
			$key   = 'wpheka_moneris_save_error_' . get_current_user_id();
			$error = (string) get_transient( $key );
			delete_transient( $key );
			$error = '' !== $error ? $error : __( 'The settings were not saved. Please try again.', 'wpheka-gateway-moneris' );
		}

		Page::close(
			array(
				'slug'       => self::SLUG,
				'icons_url'  => plugins_url( 'assets/wpheka-ui/icons/', WPHEKA_MONERIS_MAIN_FILE ),
				'short_name' => __( 'Moneris', 'wpheka-gateway-moneris' ),
				'review_url' => $links['review'],
				'form'       => 'settings' === $tab && $gateway ? self::FORM : '',
				'saved'      => 'settings' === $tab && isset( $_GET['w-saved'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag after our own redirect.
				'error'      => $error,
			)
		);
	}

	/**
	 * This page's notices. WordPress's notices are kept off the page (Page::quiet_notices()),
	 * so the plugin's own notices that can apply here are shown through the kit instead.
	 *
	 * @param WPHEKA_Gateway_Moneris|null $gateway The gateway, or null when WooCommerce did not load it.
	 * @return array[]
	 */
	private static function notices( $gateway ) {
		$notices = array();
		if ( ! $gateway ) {
			$notices[] = array(
				'id'   => 'wpheka-moneris-no-gateway',
				'kind' => 'warn',
				'text' => esc_html__( 'WooCommerce did not load the Moneris gateway on this request, so its settings cannot be shown. Reload the page, and check that WooCommerce is active.', 'wpheka-gateway-moneris' ),
			);
		}
		if ( class_exists( 'WPHEKA_Gateway_Moneris_Donation' ) && WPHEKA_Gateway_Moneris_Donation::is_shown() ) {
			$notices[] = array(
				'id'      => 'wpheka-moneris-donation',
				'kind'    => 'info',
				'text'    => '<strong>' . esc_html__( 'Enjoying this plugin?', 'wpheka-gateway-moneris' ) . '</strong> ' . esc_html__( 'Support its development with a small donation.', 'wpheka-gateway-moneris' ),
				'link'    => array( __( 'Make a donation', 'wpheka-gateway-moneris' ), WPHEKA_Gateway_Moneris_Donation::DONATION_URL ),
				'dismiss' => WPHEKA_Gateway_Moneris_Donation::dismiss_url(),
			);
		}

		return $notices;
	}

	/**
	 * Whether WooCommerce stores orders in its own tables now.
	 *
	 * @return bool
	 */
	private static function hpos() {
		return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Moneris payments and refunds in the last 30 days, in the store currency, from the
	 * storage WooCommerce uses now. Cached for an hour.
	 *
	 * Payments: Moneris orders WooCommerce recorded as paid in the window (it sets the paid
	 * date when Moneris approves the payment), whatever status they moved to afterwards:
	 * processing, completed, refunded or a status another plugin adds, such as Backordered.
	 * Trashed and draft orders are left out, and so are orders of zero (free orders never
	 * reach Moneris). Refunds: refunds created in the window on Moneris orders that
	 * WooCommerce sent through the gateway.
	 *
	 * @return array { payments, paid, refunds, refunded }
	 */
	public static function stats() {
		$currency = get_woocommerce_currency();
		$key      = ( self::hpos() ? 'hpos' : 'posts' ) . '-' . $currency;
		$cached   = get_transient( self::STATS_TRANSIENT );
		if ( is_array( $cached ) && isset( $cached[ $key ] ) ) {
			return $cached[ $key ];
		}

		global $wpdb;
		$cutoff = time() - 30 * DAY_IN_SECONDS;
		$since  = gmdate( 'Y-m-d H:i:s', $cutoff );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		if ( self::hpos() ) {
			$paid = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT COUNT(*) AS n, COALESCE( SUM( o.total_amount ), 0 ) AS amount
					FROM {$wpdb->prefix}wc_orders o
					JOIN {$wpdb->prefix}wc_order_operational_data od ON od.order_id = o.id
					WHERE o.type = 'shop_order' AND o.status NOT IN ( 'trash', 'auto-draft', 'draft', 'wc-checkout-draft' )
					AND od.date_paid_gmt >= %s
					AND o.payment_method = 'moneris' AND o.currency = %s AND o.total_amount > 0",
					$since,
					$currency
				),
				ARRAY_A
			);
			$back = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT COUNT(*) AS n, COALESCE( SUM( -r.total_amount ), 0 ) AS amount
					FROM {$wpdb->prefix}wc_orders r
					JOIN {$wpdb->prefix}wc_orders o ON o.id = r.parent_order_id
					WHERE r.type = 'shop_order_refund' AND r.date_created_gmt >= %s
					AND o.payment_method = 'moneris' AND o.currency = %s
					AND EXISTS ( SELECT 1 FROM {$wpdb->prefix}wc_orders_meta m WHERE m.order_id = r.id AND m.meta_key = '_refunded_payment' AND m.meta_value IN ( '1', 'yes', 'true' ) )",
					$since,
					$currency
				),
				ARRAY_A
			);
		} else {
			$paid = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT COUNT(*) AS n, COALESCE( SUM( tot.meta_value ), 0 ) AS amount
					FROM {$wpdb->posts} o
					JOIN {$wpdb->postmeta} pm ON pm.post_id = o.ID AND pm.meta_key = '_payment_method' AND pm.meta_value = 'moneris'
					JOIN {$wpdb->postmeta} cur ON cur.post_id = o.ID AND cur.meta_key = '_order_currency' AND cur.meta_value = %s
					JOIN {$wpdb->postmeta} tot ON tot.post_id = o.ID AND tot.meta_key = '_order_total'
					JOIN {$wpdb->postmeta} dp ON dp.post_id = o.ID AND dp.meta_key = '_date_paid'
					WHERE o.post_type = 'shop_order' AND o.post_status NOT IN ( 'trash', 'auto-draft', 'draft', 'wc-checkout-draft' )
					AND CAST( dp.meta_value AS UNSIGNED ) >= %d AND tot.meta_value > 0",
					$currency,
					$cutoff
				),
				ARRAY_A
			);
			$back = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT COUNT(*) AS n, COALESCE( SUM( amt.meta_value ), 0 ) AS amount
					FROM {$wpdb->posts} r
					JOIN {$wpdb->postmeta} pm ON pm.post_id = r.post_parent AND pm.meta_key = '_payment_method' AND pm.meta_value = 'moneris'
					JOIN {$wpdb->postmeta} cur ON cur.post_id = r.post_parent AND cur.meta_key = '_order_currency' AND cur.meta_value = %s
					JOIN {$wpdb->postmeta} paid ON paid.post_id = r.ID AND paid.meta_key = '_refunded_payment' AND paid.meta_value IN ( '1', 'yes', 'true' )
					JOIN {$wpdb->postmeta} amt ON amt.post_id = r.ID AND amt.meta_key = '_refund_amount'
					WHERE r.post_type = 'shop_order_refund' AND r.post_date_gmt >= %s",
					$currency,
					$since
				),
				ARRAY_A
			);
		}
		// phpcs:enable

		$stats = array(
			'payments' => isset( $paid['n'] ) ? (int) $paid['n'] : 0,
			'paid'     => isset( $paid['amount'] ) ? (float) $paid['amount'] : 0.0,
			'refunds'  => isset( $back['n'] ) ? (int) $back['n'] : 0,
			'refunded' => isset( $back['amount'] ) ? (float) $back['amount'] : 0.0,
		);

		$cached         = is_array( $cached ) ? $cached : array();
		$cached[ $key ] = $stats;
		set_transient( self::STATS_TRANSIENT, $cached, HOUR_IN_SECONDS );

		return $stats;
	}

	/**
	 * The card logos chosen, as labels.
	 *
	 * @param WPHEKA_Gateway_Moneris $gateway The gateway.
	 * @return string[]
	 */
	private static function card_labels( $gateway ) {
		$fields  = $gateway->get_form_fields();
		$options = isset( $fields['preferred_cards']['options'] ) ? $fields['preferred_cards']['options'] : array();
		$labels  = array();
		foreach ( array_filter( (array) $gateway->get_option( 'preferred_cards' ) ) as $card ) {
			if ( isset( $options[ $card ] ) ) {
				$labels[] = $options[ $card ];
			}
		}

		return $labels;
	}

	/**
	 * The stats strip: real numbers only.
	 *
	 * @param WPHEKA_Gateway_Moneris $gateway The gateway.
	 */
	private static function render_stats( $gateway ) {
		$stats   = self::stats();
		$sandbox = 'yes' === $gateway->get_option( 'sandbox' );
		$cards   = self::card_labels( $gateway );

		Page::stats(
			array(
				array(
					'label' => __( 'Moneris payments', 'wpheka-gateway-moneris' ),
					'icon'  => 'card',
					'value' => wp_strip_all_tags( wc_price( $stats['paid'] ) ),
					/* translators: %s: number of payments. */
					'meta'  => sprintf( _n( '%s payment in the last 30 days', '%s payments in the last 30 days', $stats['payments'], 'wpheka-gateway-moneris' ), number_format_i18n( $stats['payments'] ) ),
				),
				array(
					'label' => __( 'Refunds', 'wpheka-gateway-moneris' ),
					'icon'  => 'refund',
					'mint'  => true,
					'value' => number_format_i18n( $stats['refunds'] ),
					/* translators: %s: amount refunded. */
					'meta'  => sprintf( __( '%s sent back through Moneris, last 30 days', 'wpheka-gateway-moneris' ), wp_strip_all_tags( wc_price( $stats['refunded'] ) ) ),
				),
				array(
					'label' => __( 'Mode', 'wpheka-gateway-moneris' ),
					'icon'  => 'shield',
					'value' => $sandbox ? __( 'Sandbox', 'wpheka-gateway-moneris' ) : __( 'Live', 'wpheka-gateway-moneris' ),
					'meta'  => $sandbox ? __( 'Test cards only. No real money moves.', 'wpheka-gateway-moneris' ) : __( 'Taking real payments', 'wpheka-gateway-moneris' ),
				),
				array(
					'label' => __( 'Card logos', 'wpheka-gateway-moneris' ),
					'icon'  => 'card',
					'mint'  => true,
					'value' => number_format_i18n( count( $cards ) ),
					'meta'  => $cards ? implode( ', ', $cards ) : __( 'None shown at checkout', 'wpheka-gateway-moneris' ),
				),
			)
		);
	}

	/**
	 * Getting-started steps, each from real state.
	 *
	 * @param WPHEKA_Gateway_Moneris $gateway The gateway.
	 * @return array[] Each { label, done }.
	 */
	private static function steps( $gateway ) {
		// A Moneris payment that went through: an order with a paid date.
		$orders = wc_get_orders(
			array(
				'payment_method' => 'moneris',
				'date_paid'      => '>0',
				'limit'          => 1,
				'return'         => 'ids',
			)
		);

		return array(
			array(
				'label' => __( 'Add your Store ID and API token', 'wpheka-gateway-moneris' ),
				'done'  => '' !== trim( (string) $gateway->get_option( 'store_id' ) ) && '' !== trim( (string) $gateway->get_option( 'api_token' ) ),
			),
			array(
				'label' => __( 'Take a test payment in sandbox mode', 'wpheka-gateway-moneris' ),
				'done'  => ! empty( $orders ),
			),
			array(
				'label' => __( 'Switch to live', 'wpheka-gateway-moneris' ),
				'done'  => 'yes' !== $gateway->get_option( 'sandbox' ),
			),
		);
	}

	/**
	 * The getting-started card, until every step is done or it is hidden.
	 *
	 * @param WPHEKA_Gateway_Moneris $gateway The gateway.
	 */
	private static function render_start( $gateway ) {
		if ( get_user_meta( get_current_user_id(), self::DISMISS_META, true ) ) {
			return;
		}
		$steps = self::steps( $gateway );
		$done  = count( wp_list_filter( $steps, array( 'done' => true ) ) );
		if ( count( $steps ) === $done ) {
			return;
		}
		$dash = round( 94.25 * $done / count( $steps ), 1 );

		echo '<div class="w-card w-start"><svg class="w-ring" viewBox="0 0 36 36" aria-hidden="true"><circle cx="18" cy="18" r="15" fill="none" stroke="#ede9fe" stroke-width="4"/>';
		echo '<circle cx="18" cy="18" r="15" fill="none" stroke="#7c3aed" stroke-width="4" stroke-dasharray="' . esc_attr( $dash . ' 94.25' ) . '" stroke-linecap="round" transform="rotate(-90 18 18)"/>';
		echo '<text x="18" y="21.5" text-anchor="middle" font-size="9" font-weight="700" fill="#0f172a">' . esc_html( $done . '/' . count( $steps ) ) . '</text></svg>';
		echo '<div><h3>' . esc_html__( 'Getting started', 'wpheka-gateway-moneris' ) . '</h3><ol class="w-steps">';
		foreach ( $steps as $step ) {
			echo '<li' . ( $step['done'] ? ' class="is-done"' : '' ) . '>' . esc_html( $step['label'] ) . '<span class="w-sr"> ' . esc_html( $step['done'] ? __( '(done)', 'wpheka-gateway-moneris' ) : __( '(to do)', 'wpheka-gateway-moneris' ) ) . '</span></li>';
		}
		echo '</ol></div><div class="w-start-actions">';
		echo '<a class="w-close" data-w-dismiss=".w-start" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS_ACTION ), self::DISMISS_ACTION ) ) . '" aria-label="' . esc_attr__( 'Hide getting started', 'wpheka-gateway-moneris' ) . '">' . Icons::get( 'xcircle' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		echo '</div></div>';
	}

	/**
	 * The Settings tab: one form holding every field the page draws.
	 *
	 * @param WPHEKA_Gateway_Moneris $gateway The gateway.
	 * @param array                  $links   Links.
	 */
	private static function render_settings( $gateway, array $links ) {
		$fields = $gateway->get_form_fields();
		$name   = array( $gateway, 'get_field_key' );
		$opt    = array( $gateway, 'get_option' );

		echo '<div class="w-stack">';
		self::render_start( $gateway );
		echo '<div class="w-layout"><div>';
		Page::nav(
			array(
				array( 'checkout', 'cart', __( 'Checkout', 'wpheka-gateway-moneris' ) ),
				array( 'account', 'key', __( 'Moneris account', 'wpheka-gateway-moneris' ) ),
				array( 'troubleshooting', 'sliders', __( 'Troubleshooting', 'wpheka-gateway-moneris' ) ),
			)
		);
		echo '</div><form id="' . esc_attr( self::FORM ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-w-form autocomplete="off">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::SAVE_ACTION ) . '">';
		wp_nonce_field( self::SAVE_ACTION );

		Fields::section_start( 'checkout', __( 'Checkout', 'wpheka-gateway-moneris' ), __( 'What customers see when they pay.', 'wpheka-gateway-moneris' ) );
		Fields::switch_row(
			array(
				'name'    => call_user_func( $name, 'enabled' ),
				'checked' => 'yes' === call_user_func( $opt, 'enabled' ),
				'label'   => __( 'Enable Moneris', 'wpheka-gateway-moneris' ),
				'help'    => __( 'Show Moneris as a payment method at checkout.', 'wpheka-gateway-moneris' ),
				'off'     => null,
			)
		);
		Fields::text_row(
			array(
				'name'  => call_user_func( $name, 'title' ),
				'value' => call_user_func( $opt, 'title' ),
				'label' => __( 'Title', 'wpheka-gateway-moneris' ),
				'help'  => __( 'The payment method name at checkout.', 'wpheka-gateway-moneris' ),
			)
		);
		Fields::text_row(
			array(
				'name'  => call_user_func( $name, 'description' ),
				'value' => call_user_func( $opt, 'description' ),
				'label' => __( 'Description', 'wpheka-gateway-moneris' ),
				'help'  => __( 'Shown under the title at checkout.', 'wpheka-gateway-moneris' ),
				'stack' => true,
			)
		);
		Fields::chips_row(
			array(
				'name'     => call_user_func( $name, 'preferred_cards' ),
				'options'  => isset( $fields['preferred_cards']['options'] ) ? $fields['preferred_cards']['options'] : array(),
				'selected' => array_filter( (array) call_user_func( $opt, 'preferred_cards' ) ),
				'label'    => __( 'Card logos', 'wpheka-gateway-moneris' ),
				'help'     => __( 'The logos shown next to the payment method.', 'wpheka-gateway-moneris' ),
			)
		);
		Fields::section_end();

		Fields::section_start( 'account', __( 'Moneris account', 'wpheka-gateway-moneris' ), __( 'Connect your Moneris merchant account. Sandbox uses test cards and moves no money.', 'wpheka-gateway-moneris' ) );
		Fields::switch_row(
			array(
				'name'    => call_user_func( $name, 'sandbox' ),
				'checked' => 'yes' === call_user_func( $opt, 'sandbox' ),
				'label'   => __( 'Sandbox mode', 'wpheka-gateway-moneris' ),
				'help'    => __( 'Use the Moneris test environment. Turn it off to take real payments.', 'wpheka-gateway-moneris' ),
				'off'     => null,
			)
		);
		Fields::text_row(
			array(
				'name'  => call_user_func( $name, 'store_id' ),
				'value' => call_user_func( $opt, 'store_id' ),
				'label' => __( 'Store ID', 'wpheka-gateway-moneris' ),
				'help'  => __( 'From your Moneris Merchant Resource Centre.', 'wpheka-gateway-moneris' ),
			)
		);
		Fields::text_row(
			array(
				'name'  => call_user_func( $name, 'api_token' ),
				'value' => call_user_func( $opt, 'api_token' ),
				'label' => __( 'API token', 'wpheka-gateway-moneris' ),
				'help'  => __( 'From the same page in the Merchant Resource Centre.', 'wpheka-gateway-moneris' ),
			)
		);
		Fields::section_end();

		Fields::section_start( 'troubleshooting', __( 'Troubleshooting', 'wpheka-gateway-moneris' ) );
		Fields::switch_row(
			array(
				'name'    => call_user_func( $name, 'logging' ),
				'checked' => 'yes' === call_user_func( $opt, 'logging' ),
				'label'   => __( 'Log gateway events', 'wpheka-gateway-moneris' ),
				/* translators: %s: URL of WooCommerce > Status > Logs. */
				'help'    => sprintf( __( 'View the log in <a href="%s">WooCommerce › Status › Logs</a>, source wpheka-gateway-moneris. Turn it on only while you troubleshoot.', 'wpheka-gateway-moneris' ), esc_url( $links['logs'] ) ),
				'off'     => null,
			)
		);
		Fields::section_end();

		Fields::find_empty();
		// Last in the form on purpose: see FORM_END.
		echo '<input type="hidden" name="' . esc_attr( self::FORM_END ) . '" value="1">';
		// Without JavaScript the save bar never appears, so the form keeps a plain button.
		echo '<noscript><p><button type="submit" class="w-btn w-btn--primary">' . esc_html__( 'Save changes', 'wpheka-gateway-moneris' ) . '</button></p></noscript>';
		echo '</form><aside class="w-rail" aria-label="' . esc_attr__( 'Moneris Pro and help', 'wpheka-gateway-moneris' ) . '">';
		self::render_pro_card( $links );
		Page::help_card(
			array(
				array( __( 'Read the documentation', 'wpheka-gateway-moneris' ), $links['docs'], 'book' ),
				array( __( 'Ask in the support forum', 'wpheka-gateway-moneris' ), $links['support'], 'chat' ),
			)
		);
		echo '</aside></div></div>';
	}

	/**
	 * The Moneris Pro card: what Pro adds, from the list the plugin always showed.
	 *
	 * @param array $links Links.
	 */
	private static function render_pro_card( array $links ) {
		$groups = array(
			__( 'Sell more', 'wpheka-gateway-moneris' )  => array(
				array( __( 'Subscriptions', 'wpheka-gateway-moneris' ), __( 'Renewals are charged automatically through WooCommerce Subscriptions.', 'wpheka-gateway-moneris' ) ),
				array( __( 'Saved cards', 'wpheka-gateway-moneris' ), __( 'Returning customers pay with a saved card.', 'wpheka-gateway-moneris' ) ),
				array( __( 'Moneris Checkout', 'wpheka-gateway-moneris' ), __( 'Moneris\'s own secure payment form, so card details never touch your server.', 'wpheka-gateway-moneris' ) ),
				array( __( 'CAD and USD', 'wpheka-gateway-moneris' ), __( 'Send each currency to its own Moneris account.', 'wpheka-gateway-moneris' ) ),
			),
			__( 'Stop fraud', 'wpheka-gateway-moneris' ) => array(
				array( __( '3-D Secure', 'wpheka-gateway-moneris' ), __( 'Extra card verification on eligible cards.', 'wpheka-gateway-moneris' ) ),
				array( __( 'Address and CVD checks', 'wpheka-gateway-moneris' ), __( 'You decide which mismatches to accept or decline.', 'wpheka-gateway-moneris' ) ),
				array( __( 'Card-testing protection', 'wpheka-gateway-moneris' ), __( 'Cap payment attempts per order and per IP address.', 'wpheka-gateway-moneris' ) ),
			),
			__( 'Save time', 'wpheka-gateway-moneris' )  => array(
				array( __( 'Same-day refunds', 'wpheka-gateway-moneris' ), __( 'Refund right from the order screen, even on the day of the sale.', 'wpheka-gateway-moneris' ) ),
				array( __( 'Authorize and capture', 'wpheka-gateway-moneris' ), __( 'Authorize at checkout and capture when you complete the order.', 'wpheka-gateway-moneris' ) ),
				array( __( 'Statement descriptor', 'wpheka-gateway-moneris' ), __( 'Show your own description on customers\' card statements.', 'wpheka-gateway-moneris' ) ),
			),
		);

		echo '<div class="w-procard"><h3>' . Icons::get( 'bolt', '2' ) . esc_html__( 'Moneris Pro', 'wpheka-gateway-moneris' ) . '</h3>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		echo '<p>' . esc_html__( 'The free version handles one-off card payments. Pro adds the things growing stores ask us for most.', 'wpheka-gateway-moneris' ) . '</p>';
		foreach ( $groups as $group => $features ) {
			echo '<h4 class="wpheka-moneris-pro-group">' . esc_html( $group ) . '</h4><ul class="w-ticks">';
			foreach ( $features as $feature ) {
				echo '<li><span><strong>' . esc_html( $feature[0] ) . '</strong> ' . esc_html( $feature[1] ) . '</span></li>';
			}
			echo '</ul>';
		}
		echo '<a class="w-btn w-btn--pro w-btn--block" href="' . esc_url( $links['pro'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Upgrade to Pro', 'wpheka-gateway-moneris' ) . '<span class="w-sr"> ' . esc_html__( '(opens in a new tab)', 'wpheka-gateway-moneris' ) . '</span></a>';
		echo '<small>' . esc_html__( 'From $79. Includes a year of updates and support.', 'wpheka-gateway-moneris' ) . '</small></div>';
	}

	/**
	 * The Transactions tab: the newest Moneris orders and what was refunded on them.
	 */
	private static function render_transactions() {
		$orders = wc_get_orders(
			array(
				'payment_method' => 'moneris',
				'type'           => 'shop_order',
				'limit'          => 20,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		echo '<div class="w-stack">';
		Fields::section_start( 'transactions', __( 'Recent Moneris orders', 'wpheka-gateway-moneris' ), __( 'The 20 newest orders paid or attempted with Moneris. Open an order to refund it or see its notes.', 'wpheka-gateway-moneris' ) );

		if ( ! $orders ) {
			echo '<div class="w-empty"><h3>' . esc_html__( 'No Moneris orders yet.', 'wpheka-gateway-moneris' ) . '</h3><p>' . esc_html__( 'Orders paid with Moneris show here, newest first.', 'wpheka-gateway-moneris' ) . '</p></div>';
			Fields::section_end();
			echo '</div>';
			return;
		}

		echo '<div class="w-table-wrap"><table class="w-table wpheka-moneris-orders"><thead><tr><th>' . esc_html__( 'Order', 'wpheka-gateway-moneris' ) . '</th><th>' . esc_html__( 'Date', 'wpheka-gateway-moneris' ) . '</th><th>' . esc_html__( 'Status', 'wpheka-gateway-moneris' ) . '</th><th>' . esc_html__( 'Total', 'wpheka-gateway-moneris' ) . '</th><th>' . esc_html__( 'Refunded', 'wpheka-gateway-moneris' ) . '</th><th>' . esc_html__( 'Moneris transaction', 'wpheka-gateway-moneris' ) . '</th></tr></thead><tbody>';
		foreach ( $orders as $order ) {
			$created  = $order->get_date_created();
			$refunded = (float) $order->get_total_refunded();
			$status   = $order->get_status();
			// Paid through Moneris (a paid date), whatever status the order moved to since.
			$kind = in_array( $status, array( 'failed', 'cancelled' ), true ) ? 'bad' : ( $order->get_date_paid() ? 'ok' : 'info' );

			// get_edit_order_url() arrived in WooCommerce 3.3; the plugin declares 3.0.
			$edit = is_callable( array( $order, 'get_edit_order_url' ) ) ? $order->get_edit_order_url() : admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' );
			echo '<tr data-order="' . esc_attr( $order->get_id() ) . '"><td><a href="' . esc_url( $edit ) . '">#' . esc_html( $order->get_order_number() ) . '</a></td>';
			echo '<td>' . esc_html( $created ? wc_format_datetime( $created ) : '' ) . '</td>';
			echo '<td><span class="w-pill w-pill--' . esc_attr( $kind ) . ' w-pill--plain">' . esc_html( wc_get_order_status_name( $status ) ) . '</span></td>';
			echo '<td>' . wp_kses_post( $order->get_formatted_order_total() ) . '</td>';
			echo '<td>' . ( $refunded > 0 ? wp_kses_post( wc_price( $refunded, array( 'currency' => $order->get_currency() ) ) ) : '&ndash;' ) . '</td>';
			echo '<td>' . ( $order->get_transaction_id() ? '<code>' . esc_html( $order->get_transaction_id() ) . '</code>' : '&ndash;' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
		Fields::section_end();
		echo '</div>';
	}

	/**
	 * The Help tab, from what the plugin already says.
	 *
	 * @param array $links Links.
	 */
	private static function render_help( array $links ) {
		$cards = array(
			array( 'book', __( 'Documentation', 'wpheka-gateway-moneris' ), __( 'Setup, settings and answers to common issues.', 'wpheka-gateway-moneris' ), $links['docs'] ),
			array( 'chat', __( 'Support forum', 'wpheka-gateway-moneris' ), __( 'Ask a question. The developers who built the plugin answer.', 'wpheka-gateway-moneris' ), $links['support'] ),
			array( 'sparkle', __( "What's new", 'wpheka-gateway-moneris' ), __( 'Every change in each version, in plain words.', 'wpheka-gateway-moneris' ), $links['changes'] ),
		);
		echo '<div class="w-stack"><div class="w-flow">';
		foreach ( $cards as $card ) {
			echo '<a class="w-card w-flow-step" href="' . esc_url( $card[3] ) . '" target="_blank" rel="noopener"><span class="w-ico w-ico--lg">' . Icons::get( $card[0] ) . '</span><h3>' . esc_html( $card[1] ) . '</h3><p>' . esc_html( $card[2] ) . '<span class="w-sr"> ' . esc_html__( '(opens in a new tab)', 'wpheka-gateway-moneris' ) . '</span></p></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		}
		echo '</div>';

		$link  = array(
			'a' => array(
				'href'   => array(),
				'target' => array(),
				'rel'    => array(),
			),
		);
		$notes = array(
			__( 'Your Store ID and API token are in the Moneris Merchant Resource Centre. Sandbox and live use different ones.', 'wpheka-gateway-moneris' ),
			/* translators: %s: URL of the Moneris testing page. */
			sprintf( __( 'In sandbox mode, pay with the card number 4242424242424242, any CVC and a future expiry date, or see the <a href="%s" target="_blank" rel="noopener">Moneris testing guide</a> for more test cards.', 'wpheka-gateway-moneris' ), esc_url( $links['testing'] ) ),
			__( 'Refunds are sent to Moneris from the order screen, from the day after the payment. On the day of the payment itself Moneris needs a different request (a Purchase Correction), which Moneris Pro sends.', 'wpheka-gateway-moneris' ),
			/* translators: %s: URL of WooCommerce > Status > Logs. */
			sprintf( __( 'With logging on, each request and reply is written to <a href="%s">WooCommerce › Status › Logs</a> under the source wpheka-gateway-moneris. Card numbers are masked to the last four digits; the expiry date, CVD, API token and customer details are left out.', 'wpheka-gateway-moneris' ), esc_url( $links['logs'] ) ),
			__( 'Moneris works on the classic checkout and the block checkout.', 'wpheka-gateway-moneris' ),
			__( 'Moving to Moneris Pro keeps your Moneris account: Pro takes over the same Store ID and API token.', 'wpheka-gateway-moneris' ),
		);

		Fields::section_start( 'good-to-know', __( 'Good to know', 'wpheka-gateway-moneris' ) );
		echo '<ul class="wpheka-moneris-notes">';
		foreach ( $notes as $note ) {
			echo '<li>' . wp_kses( $note, $link ) . '</li>';
		}
		echo '</ul>';
		Fields::section_end();
		echo '</div>';
	}
}
