<?php
/**
 * Plugin Name: WC Moneris Payment Gateway
 * Plugin URI: https://www.wpheka.com/product/wc-moneris-payment-gateway
 * Description: Take credit card payments on your WooCommerce store using Moneris. <a href="https://www.wpheka.com" target="_blank">Get more plugins for your e-commerce on <strong>WPHEKA</strong></a>
 * Author: WPHEKA
 * Author URI: https://www.wpheka.com
 * Version: 3.3
 * Requires at least: 4.9
 * Tested up to: 6.5.3
 * WC requires at least: 3.0
 * WC tested up to: 8.9.1
 * Text Domain: wpheka-gateway-moneris
 * Domain Path: /languages
 *
 * @package   WPHEKA_Moneris
 * @author    WPHEKA
 * @link      https://www.wpheka.com
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Required minimums and constants
 */
define('WPHEKA_MONERIS_VERSION', '3.3');
define('WPHEKA_MONERIS_MIN_PHP_VER', '5.6.0');
define('WPHEKA_MONERIS_MIN_WC_VER', '3.0');
define('WPHEKA_MONERIS_FUTURE_MIN_WC_VER', '17.8');
define('WPHEKA_MONERIS_MAIN_FILE', __FILE__);
define('WPHEKA_MONERIS_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
define('WPHEKA_MONERIS_PLUGIN_PATH', untrailingslashit(plugin_dir_path(__FILE__)));
define('WPHEKA_MONERIS_PLUGIN_ICON', untrailingslashit(plugins_url('/assets/images/logo.png', __FILE__)));
define('WPHEKA_MONERIS_PLUGIN_LOGO', untrailingslashit(plugins_url('/assets/images/logo-dark.png', __FILE__)));

/**
 * CURL not enabled fallback notice.
 *
 * @since 1.9
 */
function wpheka_moneris_missing_curl()
{
    echo '<div class="error"><p><strong>' . esc_html__('Moneris - cURL is not installed.', 'wpheka-gateway-moneris') . '</strong></p></div>';
}

/**
 * WooCommerce fallback notice.
 *
 * @since 1.9
 */
function wpheka_moneris_missing_wc_notice()
{
    /* translators: 1. URL link. */
    echo '<div class="error"><p><strong>' . sprintf(esc_html__('Moneris requires WooCommerce to be installed and active. You can download %s here.', 'wpheka-gateway-moneris'), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>') . '</strong></p></div>';
}

/**
 * WooCommerce not supported fallback notice.
 *
 * @since 1.9
 */
function wpheka_moneris_wc_not_supported()
{
    /* translators: $1. Minimum WooCommerce version. $2. Current WooCommerce version. */
    echo '<div class="error"><p><strong>' . sprintf(esc_html__('Moneris requires WooCommerce %1$s or greater to be installed and active. WooCommerce %2$s is no longer supported.', 'wpheka-gateway-moneris'), WPHEKA_MONERIS_MIN_WC_VER, WC_VERSION) . '</strong></p></div>';
}

/**
 * Minimum php version notice.
 *
 * @since 2.1
 */
function wpheka_moneris_min_php_ver_notice()
{
    /* translators: 1) int version 2) int version */
    echo '<div class="error"><p><strong>' . sprintf(esc_html__('WooCommerce Moneris - The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'wpheka-gateway-moneris'), WPHEKA_MONERIS_MIN_PHP_VER, phpversion()) . '</strong></p></div>';
}

/**
 * Moneris pro active notice.
 *
 * @since 2.0
 */
function wpheka_moneris_pro_active_notice()
{
    echo '<div class="error"><p><strong>' . esc_html__('Pro version of Moneris gateway plugin is already active, Please deactivate it first to install the free version.', 'wpheka-gateway-moneris-pro') . '</strong></p></div>';
}

add_action('plugins_loaded', 'wpheka_gateway_moneris_init');

/**
 * Declares support for HPOS.
 *
 * @since 3.1
 * @return void
 */
function woocommerce_gateway_moneris_declare_hpos_compatibility()
{
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
}
add_action('before_woocommerce_init', 'woocommerce_gateway_moneris_declare_hpos_compatibility');

/**
 * Declares support for WooCommerce Checkout blocks.
 *
 * @since 3.1
 * @return void
 */
function woocommerce_gateway_moneris_declare_cart_checkout_blocks_compatibility()
{
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            __FILE__,
            true // true (compatible, default) or false (not compatible)
        );
    }
}
add_action('before_woocommerce_init', 'woocommerce_gateway_moneris_declare_cart_checkout_blocks_compatibility');

/**
 * Add Moneris Payment Gateway to WooCommerce Blocks.
 *
 * @since 3.1
 * @return void
 */
function woocommerce_gateway_moneris_block_support()
{
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        require_once dirname(__FILE__) . '/includes/blocks/class-wpheka-gateway-moneris-blocks-support.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                $payment_method_registry->register(new Woocommerce_Gateway_Moneris_Blocks_Support());
            }
        );
    }
}

// Add support for WooCommerce Blocks.
add_action('woocommerce_blocks_loaded', 'woocommerce_gateway_moneris_block_support');

/**
 * Add credit card input styles to the blocks checkout page
 *
 * @return void
 */
function add_credit_card_input_styles_to_moneris_block()
{
    $block_name = 'monerisgateway/moneris_direct';
    $args       = array(
        'handle' => 'credit-card-input-styles',
        'src'    => plugins_url('/assets/css/blocks/credit-card-inputs.css', __FILE__),
        'path'   => plugins_url('/assets/css/blocks/credit-card-inputs.css', __FILE__),
        'ver'    => WPHEKA_MONERIS_VERSION,
    );

    wp_enqueue_block_style($block_name, $args);
}
add_action('after_setup_theme', 'add_credit_card_input_styles_to_moneris_block');

function wpheka_gateway_moneris_init()
{
    load_plugin_textdomain('wpheka-gateway-moneris', false, plugin_basename(dirname(__FILE__)) . '/languages');

    if (!function_exists('curl_init')) {
        add_action('admin_notices', 'wpheka_moneris_missing_curl');
        return;
    }

    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'wpheka_moneris_missing_wc_notice');
        return;
    }

    if (version_compare(WC_VERSION, WPHEKA_MONERIS_MIN_WC_VER, '<')) {
        add_action('admin_notices', 'wpheka_moneris_wc_not_supported');
        return;
    }

    if (version_compare(phpversion(), WPHEKA_MONERIS_MIN_PHP_VER, '<')) {
        add_action('admin_notices', 'wpheka_moneris_min_php_ver_notice');
        return;
    }

    if (class_exists('WPHEKA_Moneris_Pro')) {
        add_action('admin_notices', 'wpheka_moneris_pro_active_notice');
        return;
    }

    if (!class_exists('WPHEKA_Moneris')) :

        class WPHEKA_Moneris
        {

            /**
             * @var Singleton The reference the *Singleton* instance of this class
             */
            private static $instance;

            /**
             * Returns the *Singleton* instance of this class.
             *
             * @return Singleton The *Singleton* instance.
             */
            public static function get_instance()
            {
                if (null === self::$instance) {
                    self::$instance = new self();
                }
                return self::$instance;
            }

            /**
             * Private clone method to prevent cloning of the instance of the
             * *Singleton* instance.
             *
             * @return void
             */
            private function __clone()
            {
            }

            /**
             * Public unserialize method to prevent unserializing of the *Singleton*
             * instance.
             *
             * @return void
             */
            public function __wakeup()
            {
            }

            /**
             * Protected constructor to prevent creating a new instance of the
             * *Singleton* via the `new` operator from outside of this class.
             */
            private function __construct()
            {
                add_action('admin_init', array($this, 'install'));
                $this->init();
            }

            /**
             * Init the plugin after plugins_loaded so environment variables are set.
             *
             * @since 1.0.0
             * @version 1.9
             */
            public function init()
            {

                if (is_admin()) {
                    require_once dirname(__FILE__) . '/includes/admin/class-wpheka-gateway-moneris-deactivation.php';
                    require_once dirname(__FILE__) . '/includes/admin/class-wpheka-gateway-moneris-donation.php';
                }

                require_once dirname(__FILE__) . '/includes/class-wpheka-moneris-logger.php';
                require_once dirname(__FILE__) . '/includes/wpheka-moneris-api/vendor/autoload.php';
                require_once dirname(__FILE__) . '/includes/class-wpheka-gateway-moneris.php';

                add_filter('woocommerce_payment_gateways', array($this, 'add_gateways'));
                add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
                add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
            }

            /**
             * Updates the plugin version in db
             *
             * @since 1.9
             * @version 1.9
             */
            public function update_plugin_version()
            {
                delete_option('wpheka_moneris_version');
                update_option('wpheka_moneris_version', WPHEKA_MONERIS_VERSION);
            }

            /**
             * Handles upgrade routines.
             *
             * @since 1.9
             * @version 1.9
             */
            public function install()
            {
                if (!is_plugin_active(plugin_basename(__FILE__))) {
                    return;
                }

                if (!defined('IFRAME_REQUEST') && (WPHEKA_MONERIS_VERSION !== get_option('wpheka_moneris_version'))) {
                    do_action('wpheka_moneris_updated');

                    if (!defined('WPHEKA_MONERIS_INSTALLING')) {
                        define('WPHEKA_MONERIS_INSTALLING', true);
                    }

                    $this->update_plugin_version();
                }
            }

            /**
             * Add plugin action links.
             *
             * @since 1.0.0
             * @version 1.9
             * @param  array $links Original list of plugin links.
             */
            public function plugin_action_links($links)
            {
                $plugin_links = array(
                    '<a href="admin.php?page=wc-settings&tab=checkout&section=moneris">' . esc_html__('Settings', 'wpheka-gateway-moneris') . '</a>',
                );

                return array_merge($plugin_links, $links);
            }

            /**
             * Add plugin action links.
             *
             * @since 1.9
             * @param  array  $links Original list of plugin links.
             * @param  string $file  Name of current file.
             * @return array  $links Update list of plugin links.
             */
            public function plugin_row_meta($links, $file)
            {
                if (plugin_basename(__FILE__) === $file) {
                    $row_meta = array(
                        'support' => '<a href="' . esc_url(apply_filters('wpheka_gateway_moneris_support_url', 'https://www.wpheka.com/submit-ticket/')) . '" title="' . esc_attr(__('Open a support request at wpheka.com', 'wpheka-gateway-moneris')) . '">' . __('Support', 'wpheka-gateway-moneris') . '</a>',
                    );
                    return array_merge($links, $row_meta);
                }
                return (array) $links;
            }

            /**
             * Add the gateways to WooCommerce.
             *
             * @since 1.0.0
             * @version 1.9
             * @param  array $methods WC payment methods.
             */
            public function add_gateways($methods)
            {
                $methods[] = 'WPHEKA_Gateway_Moneris';
                return $methods;
            }
        }

        WPHEKA_Moneris::get_instance();
    endif;
}
