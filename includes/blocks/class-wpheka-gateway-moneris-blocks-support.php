<?php
/**
 * Moneris Payment Gateway Blocks Support
 *
 * @package WPHEKA_Gateway_Moneris
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Purchase Order payment Gateway Blocks integration
 *
 * @since 1.4.0
 */
final class Woocommerce_Gateway_Moneris_Blocks_Support extends AbstractPaymentMethodType
{

    private $gateway;

    /**
     * Name of the payment method.
     *
     * @var string
     */
    protected $name = 'moneris';

    /**
     * Initializes the payment method type.
     */
    public function initialize()
    {
        // get payment gateway settings
        $this->settings = get_option("woocommerce_{$this->name}_settings", array());
        
        // you can also initialize your payment gateway here
        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway  = $gateways[ $this->name ];
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active()
    {
        return ! empty($this->settings[ 'enabled' ]) && 'yes' === $this->settings[ 'enabled' ];
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        $script_path = 'assets/js/block/frontend/payment-block.js';
        $script_asset_path = \WPHEKA_MONERIS_PLUGIN_PATH . '/assets/js/block/frontend/payment-block.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require($script_asset_path)
            : array(
                'dependencies' => array(),
                'version' => '1.2.0'
            );
        $script_url = plugin_dir_url(WPHEKA_MONERIS_MAIN_FILE) . $script_path;

        wp_register_script(
            'wpheka-gateway-moneris-payment-blocks',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        return [ 'wpheka-gateway-moneris-payment-blocks' ];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        return array(
            'title' => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'supports' => array_filter($this->gateway->supports, [ $this->gateway, 'supports' ]),
            'icons' => $this->get_icons(),
        );
    }

    /**
     * Return the icons urls.
     *
     * @return array Arrays of icons metadata.
     */
    private function get_icons()
    {
        $payment_icons = $this->gateway->getPreferredCards();

        $icons_src = [];

        foreach ($payment_icons as $payment_icon_key => $payment_icon_name) {
            $icons_src[$payment_icon_key] = [
                'src' => WC()->plugin_url(). '/assets/images/icons/credit-cards/'.$payment_icon_key.'.svg',
                'alt' => esc_attr($payment_icon_name),
            ];
        }
        
        return $icons_src;
    }
}
