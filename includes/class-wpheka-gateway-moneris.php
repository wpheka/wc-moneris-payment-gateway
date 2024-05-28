<?php
if (!defined('ABSPATH')) {
    exit;
}

// HPOS
use Automattic\WooCommerce\Utilities\OrderUtil;


use wpheka\Moneris\Gateway;

/**
 * WPHEKA_Gateway_Moneris class.
 *
 * @extends WC_Payment_Gateway_CC
 */
class WPHEKA_Gateway_Moneris extends WC_Payment_Gateway_CC
{

    public $sandbox;
    public $store_id;
    public $api_token;
    public $preferred_cards;

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->id = 'moneris';
        $this->method_title = __('Moneris', 'wpheka-gateway-moneris');
        $this->method_description = __('Allows payments by Moneris.', 'wpheka-gateway-moneris');
        $this->new_method_label = __('Use a new card', 'wpheka-gateway-moneris');
        $this->has_fields = true;
        $this->supports = array(
            'products',
            'default_credit_card_form',
            'refunds',
            'pre-orders',
        );
        // If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
        $this->icon = WPHEKA_MONERIS_PLUGIN_ICON;
        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Get setting values.
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->sandbox = $this->get_option('sandbox');
        $this->store_id = $this->get_option('store_id');
        $this->api_token = $this->get_option('api_token');
        $this->preferred_cards = $this->get_option('preferred_cards');

        // Hooks.
        add_action('admin_enqueue_scripts', array($this, 'moneris_admin_styles'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_after_settings_checkout', array($this, 'submit_button_css'));
    }

    /**
     * Moneris admin css
     *
     * @return void
     */
    public function moneris_admin_styles()
    {
        wp_enqueue_style('wpheka_gateway_moneris_css', plugins_url('assets/css/moneris.css', WPHEKA_MONERIS_MAIN_FILE), array(), WPHEKA_MONERIS_VERSION);
    }

    /**
     * Admin Panel Options
     *
     * @since 1.7
     * @return void
     */
    public function admin_options()
    {
        ?>
        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-2">
                <div id="post-body-content">
                    <?php parent::admin_options();?>
                </div>
                <div id="postbox-container-1" class="postbox-container">
                    <div id="side-sortables" class="meta-box-sortables ui-sortable">
                        <div class="postbox moneris-pro">
                        <div class="handlediv" title="Click to toggle"><br></div>
                        <h3 class="hndle"><span><i class="dashicons dashicons-update"></i>&nbsp;&nbsp;Upgrade to Pro</span></h3>
                        <div class="inside">
                            <div class="support-widget">
                                <ul>
                                    <li><span class="pro-feature-list">»</span>  New payment method <strong style="color: #000000;">Moneris Checkout</strong> added that delivers greater payment security, flexibility and control for online businesses. Read more <a href="https://www.wpheka.com/docs/wc-moneris-payment-gateway-pro/#mco" target="_blank"><b style="color: #000000;">here</b></a>.</li>
                                    <li><span class="pro-feature-list">»</span> Route payments to different Moneris accounts based on their currency. </li>
                                    <li><span class="pro-feature-list">»</span> Customers can save cards to their accounts for future purchases.</li>
                                    <li><span class="pro-feature-list">»</span> Supports eFraud tools / address and card verification.</li>
                                    <li><span class="pro-feature-list">»</span> Accepts Major Credit Cards / Debit Cards (Visa, MasterCard, Discover, JCB and American Express).</li>
                                    <li><span class="pro-feature-list">»</span> Process refunds automatically from within WooCommerce.</li>
                                    <li><span class="pro-feature-list">»</span> Option to directly charge credit cards or pre authorize credit cards transactions.</li>
                                    <li><span class="pro-feature-list">»</span> Statement descriptor option (Merchant defined description sent on a per-transaction basis that will appear on the credit card statement appended to the merchant’s business name).</li>
                                    <li><span class="pro-feature-list">»</span> Moneris Vault support for storing/removing credit card profiles.</li>
                                    <li><span class="pro-feature-list">»</span> WooCommerce sequential order numbers pro compatibility.</li>
                                    <li><span class="pro-feature-list">»</span> Auto Hassle-Free Updates</li>
                                    <li><span class="pro-feature-list">»</span> High Priority Customer Support</li>
                                </ul>
                                <a href="https://www.wpheka.com/product/wc-moneris-payment-gateway-pro/" class="button moneris-upgrade" target="_blank"><span class="dashicons dashicons-star-filled" style="margin-top: 3px;"></span> Upgrade Now</a>
                            </div>
                        </div>
                        </div>
                        <div class="postbox ">
                            <div class="handlediv" title="Click to toggle"><br></div>
                            <h3 class="hndle"><span><i class="dashicons dashicons-editor-help"></i>&nbsp;&nbsp;Plugin Support</span></h3>
                            <div class="inside">
                                <div class="support-widget">
                                    <p>
                                    <img style="width: 70%;margin: 0 auto;position: relative;display: inherit;" src="<?php echo WPHEKA_MONERIS_PLUGIN_LOGO; ?>">
                                    <br/>
                                    Got a Question, Idea, Problem or Praise?</p>
                                    <ul>
                                        <li>» Please leave us a <a target="_blank" href="https://wordpress.org/support/view/plugin-reviews/wc-moneris-payment-gateway?filter=5#postform">★★★★★</a> rating.</li>
                                        <li>» <a href="https://www.wpheka.com/submit-ticket/" target="_blank">Support Request</a></li>
                                        <li>» <a href="https://www.wpheka.com/product/wc-moneris-payment-gateway/" target="_blank">Documentation and Common issues.</a></li>
                                        <li>» <a href="https://www.wpheka.com/plugins/" target="_blank">Our Plugins Shop</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="clear"></div>
        <?php
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'wpheka-gateway-moneris'),
                'label' => __('Enable Moneris Gateway', 'wpheka-gateway-moneris'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('Title', 'wpheka-gateway-moneris'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'wpheka-gateway-moneris'),
                'default' => __('Moneris', 'wpheka-gateway-moneris'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'wpheka-gateway-moneris'),
                'type' => 'text',
                'description' => __('This controls the description which the user sees during checkout.', 'wpheka-gateway-moneris'),
                'default' => 'Pay with your credit card via moneris.',
                'desc_tip' => true,
            ),
            'store_id' => array(
                'title' => __('Store Id', 'wpheka-gateway-moneris'),
                'type' => 'text',
                'desc_tip' => __('Enter your Moneris account store id here.', 'wpheka-gateway-moneris'),
            ),
            'api_token' => array(
                'title' => __('API Token', 'wpheka-gateway-moneris'),
                'type' => 'text',
                'desc_tip' => __('Enter your Moneris API Token here.', 'wpheka-gateway-moneris'),
            ),
            'preferred_cards' => array(
                'title' => __('Preferred Cards', 'wpheka-gateway-moneris'),
                'type' => 'multiselect',
                'class' => 'wc-enhanced-select',
                'desc_tip' => __('Select your desired cards from the multiple-select box. The logo of the selected card(s) will be displayed on the checkout page.', 'wpheka-gateway-moneris'),
                'default' => 'visa',
                'options' => array(
                    'visa' => __('Visa', 'wpheka-gateway-moneris'),
                    'mastercard' => __('MasterCard', 'wpheka-gateway-moneris'),
                    'discover' => __('Discover', 'wpheka-gateway-moneris'),
                    'amex' => __('American Express', 'wpheka-gateway-moneris'),
                    'jcb' => __('JCB', 'wpheka-gateway-moneris'),
                ),
                'custom_attributes' => array(
                    'data-placeholder' => __('Select your desired cards', 'wpheka-gateway-moneris'),
                ),
            ),
            'sandbox' => array(
                'title' => __('Sandbox', 'wpheka-gateway-moneris'),
                'label' => __('Enable sandbox mode', 'wpheka-gateway-moneris'),
                'type' => 'checkbox',
                'description' => __('Place the payment gateway in sandbox mode.', 'wpheka-gateway-moneris'),
                'default' => 'yes',
            ),
        );
    }

    /**
     * Payment form on checkout page.
     */
    public function payment_fields()
    {
        $description = $this->get_description();

        if ('yes' == $this->sandbox) {
            /* translators: link to Moneris testing page */
            $description .= ' ' . sprintf(__('TEST MODE ENABLED. In test mode, you can use the card number 4242424242424242 with any CVC and a valid expiration date or check the <a href="%s" target="_blank">Testing Moneris documentation</a> for more card numbers.', 'wpheka-gateway-moneris'), 'https://developer.moneris.com/More/Testing/Testing%20a%20Solution');
        }

        if ($description) {
            echo wpautop(wptexturize(trim($description)));
        }

        if ($this->supports('default_credit_card_form')) {
            parent::payment_fields();
        }
    }

    /**
     * Check transaction response and return the result.
     *
     * @param int $order_id Order ID.
     * @return bool
     */
    private function transaction_success($response)
    {

        if (($response->getResponseCode() != 'null') && ($response->getResponseCode() < 50) && $response->getComplete()) {
            return true;
        }

        return false;
    }

    /**
     * Validate frontend fields.
     *
     * Validate payment fields on the frontend.
     *
     * @return bool
     */
    public function validate_fields()
    {

        if (empty($_POST[$this->id . '-card-number'])) {
            wc_add_notice(__('Please enter your card number.', 'wpheka-gateway-moneris'), 'error');
            WPHEKA_Moneris_Logger::log('Please enter your card number.');
            return false;
        }

        if (empty($_POST[$this->id . '-card-expiry'])) {
            wc_add_notice(__('Please enter your card expiry.', 'wpheka-gateway-moneris'), 'error');
            WPHEKA_Moneris_Logger::log('Please enter your card expiry.');
            return false;
        }

        if (empty($_POST[$this->id . '-card-cvc'])) {
            wc_add_notice(__('Please enter your card cvd code.', 'wpheka-gateway-moneris'), 'error');
            WPHEKA_Moneris_Logger::log('Please enter your card cvd code.');
            return false;
        }

        return true;
    }

    /**
     * Completes a free order.
     *
     * @since 2.1
     * @param WC_Order $order             The order to complete.
     * @return array                      Redirection data for `process_payment`.
     */
    public function complete_free_order($order)
    {
        // Remove cart.
        WC()->cart->empty_cart();

        $order->payment_complete();

        // Return thank you page redirect.
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }

    private function update_order_meta_data($key, $value, $order, $order_id)
    {
        if (OrderUtil::custom_orders_table_usage_is_enabled()) {
            $order->update_meta_data($key, $value);
            $order->save();
        } else {
            update_post_meta($order_id, $key, $value);
        }
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function process_payment($order_id)
    {

        $order = wc_get_order($order_id);

        if (0 >= $order->get_total()) {
            return $this->complete_free_order($order);
        }

        $store_id = $this->store_id;
        $api_token = $this->api_token;

        if (empty($store_id) || empty($api_token)) {
            WPHEKA_Moneris_Logger::log('Please update your Moneris credentials in payment settings.');
            throw new Exception(__('Please update your Moneris credentials in payment settings.', 'wpheka-gateway-moneris'));
        }

        $params = array(
            'environment' => ('yes' == $this->sandbox) ? 'staging' : 'live',
            'gateway_id' => $this->id,
            'posted_data' => wp_unslash($_POST),
            'order' => $order,
            'avs' => false, // default: false.
            'cvd' => false, // default: false.
            'cof' => false, // default: false.
        );

        $gateway = new Gateway($store_id, $api_token, $params);

        $response = $gateway->purchase();

        if ($this->transaction_success($response)) {
            // Add Transaction details.
            $_date = $response->getTransDate() . ' ' . $response->getTransTime();

            $this->update_order_meta_data('_paid_date', $_date, $order, $order_id);
            $this->update_order_meta_data('_transaction_id', $response->getTxnNumber(), $order, $order_id);
            $this->update_order_meta_data('_completed_date', $_date, $order, $order_id);
            $this->update_order_meta_data('_reference_no', $response->getReferenceNum(), $order, $order_id);
            $this->update_order_meta_data('_response_code', $response->getResponseCode(), $order, $order_id);
            $this->update_order_meta_data('_iso_code', $response->getISO(), $order, $order_id);
            $this->update_order_meta_data('_authorization_code', $response->getAuthCode(), $order, $order_id);
            $this->update_order_meta_data('_transaction_type', $response->getTransType(), $order, $order_id);
            $this->update_order_meta_data('_card_type', $response->getCardType(), $order, $order_id);
            $this->update_order_meta_data('_refund_order_id', $response->getReceiptId(), $order, $order_id);

            $transaction_id = $response->getTxnNumber();
            // add transaction id to order notes

            if (!empty($transaction_id)) {
                /* translators: transaction id */
                $message = sprintf(__('Moneris payment complete (Transaction ID: %s)', 'wpheka-gateway-moneris'), $transaction_id);
                // Mark order as processing
                $order->update_status('processing', $message);

                $order->set_transaction_id($transaction_id);
            }

            if (is_callable([$order, 'save'])) {
                $order->save();
            }

            // Remove cart.
            if (isset(WC()->cart)) {
                WC()->cart->empty_cart();
            }

            // Redirect to thank you page.
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            );
        } else {
            wc_add_notice(__('Payment error: ' . $response->getMessage(), 'wpheka-gateway-moneris'), 'error');
            WPHEKA_Moneris_Logger::log('Payment error: ' . $response->getMessage());
            $order->add_order_note(__($response->getMessage(), 'wpheka-gateway-moneris'));
            /* translators: error message */
            $order->update_status('failed');
            return;
        }
    }

    /**
     * Check timestamps is on same day.
     *
     * @param  int $ts1 Timestamp1.
     * @param  int $ts2 Timestamp2.
     * @return bool
     */
    private function isSameDay($ts1, $ts2 = '')
    {
        if ($ts2 == '') {
            $ts2 = time();
        }
        $f = false;
        if (date('z-Y', $ts1) == date('z-Y', $ts2)) {
            $f = true;
        }
        return $f;
    }

    /**
     * Process a refund if supported.
     *
     * @param  int    $order_id Order ID.
     * @param  float  $amount Refund amount.
     * @param  string $reason Refund reason.
     * @return bool|WP_Error
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        if ($amount <= 0) {
            WPHEKA_Moneris_Logger::log('Refund failed.');
            return new WP_Error('error', __('Refund failed.', 'woocommerce'));
        }

        $store_id = $this->store_id;
        $api_token = $this->api_token;
        $order = new WC_Order($order_id);

        $timezone_string = get_option('timezone_string');
        if (!empty($timezone_string) && function_exists('date_default_timezone_set')) {
            date_default_timezone_set($timezone_string);
        }
        $order_placed_datetime = get_post_meta($order_id, '_paid_date', true);

        if (!empty($order_placed_datetime)) {
            $order_placed_timestamp = strtotime($order_placed_datetime);

            $is_order_placed_same_day = $this->isSameDay($order_placed_timestamp, time());

            if ($is_order_placed_same_day) {
                WPHEKA_Moneris_Logger::log('Same day refund feature is not available. Please contact plugin author for professional version of this plugin.');
                return new WP_Error('error', __('Same day refund feature is not available. Please contact plugin author for professional version of this plugin.', 'woocommerce'));
            }
        }

        $params = array(
            'environment' => ('yes' == $this->sandbox) ? 'staging' : 'live',
            'gateway_id' => $this->id,
            'posted_data' => array(),
            'order' => $order,
            'avs' => false, // default: false.
            'cvd' => false, // default: false.
            'cof' => false, // default: false.
        );

        $gateway = new Gateway($store_id, $api_token, $params);

        $response = $gateway->refund($amount, $reason);

        if ($this->transaction_success($response)) {
            $order->add_order_note(__('Amount Refunded: ' . $amount, 'wpheka-gateway-moneris'));
            return true;
        } else {
            $order->add_order_note(__($response->getMessage(), 'wpheka-gateway-moneris'));
            return false;
        }
    }

    /**
     * Get gateway icon.
     *
     * @access public
     * @return string
     */
    public function get_icon()
    {

        $visa = '<img src="' . WC_HTTPS::force_https_url(WC()->plugin_url() . '/assets/images/icons/credit-cards/visa.svg') . '" alt="Visa" width="32" />';
        $mastercard = '<img src="' . WC_HTTPS::force_https_url(WC()->plugin_url() . '/assets/images/icons/credit-cards/mastercard.svg') . '" alt="MasterCard" width="32" />';
        $discover = '<img src="' . WC_HTTPS::force_https_url(WC()->plugin_url() . '/assets/images/icons/credit-cards/discover.svg') . '" alt="Discover" width="32" />';
        $amex = '<img src="' . WC_HTTPS::force_https_url(WC()->plugin_url() . '/assets/images/icons/credit-cards/amex.svg') . '" alt="Amex" width="32" />';
        $jcb = '<img src="' . WC_HTTPS::force_https_url(WC()->plugin_url() . '/assets/images/icons/credit-cards/jcb.svg') . '" alt="JCB" width="32" />';

        $icon = '';
        if (!empty($this->preferred_cards)) {
            foreach ($this->preferred_cards as $card) {
                $icon .= $$card;
            }
        }

        return apply_filters('wpheka_gateway_icon', $icon, $this->id);
    }

    public function getPreferredCards()
    {
        $preferred_cards = array();

        if (!empty($this->preferred_cards)) {
            foreach ($this->preferred_cards as $card) {
                $preferred_cards[$card] = ucfirst($card);
            }
        }

        return $preferred_cards;
    }

    /**
     * Moneris settings submit button css
     */
    public function submit_button_css()
    {
        $section = isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : '';

        if (isset($_GET['section']) && 'moneris' === $_GET['section']) {
            ?>
            <script>
                if(window.jQuery) {
                    var save_moneris_options_btn = jQuery("button.button-primary.woocommerce-save-button").parent('p.submit').clone();
                    jQuery("button.button-primary.woocommerce-save-button").parent('p.submit').remove();
                    save_moneris_options_btn.appendTo('div#post-body-content');
                }
            </script>
            <?php
        }
    }
}
