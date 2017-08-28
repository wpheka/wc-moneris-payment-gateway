<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class mpg_WOO_Moneris_Payment_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $MPG_Moneris_Payment_Gateway;

		$this->id                 = 'moneris';
		$this->method_title       = __( 'Moneris', $MPG_Moneris_Payment_Gateway->text_domain );
		$this->method_description = __( 'Allows payments by Moneris.', $MPG_Moneris_Payment_Gateway->text_domain );
		$this->new_method_label   = __( 'Use a new card', $MPG_Moneris_Payment_Gateway->text_domain );
		$this->has_fields         = true;
		$this->supports           = array(
			'products',
			'default_credit_card_form',
			'tokenization',
			'refunds',
			'pre-orders',
		);
		// If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
		$this->icon = $MPG_Moneris_Payment_Gateway->icon;
		// Load the form fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values
		$this->title           = $this->get_option( 'title' );
		$this->description     = $this->get_option( 'description' );
		$this->enabled         = $this->get_option( 'enabled' );
		$this->sandbox         = $this->get_option( 'sandbox' );
		$this->store_id        = $this->get_option( 'store_id' );
		$this->api_token       = $this->get_option( 'api_token' );
		$this->country_code    = $this->get_option( 'country_code' );
		$this->crypt_type      = $this->get_option( 'crypt_type' );

		$this->init_moneris_api();

		// Hooks
//		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
//		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
//		add_action( 'woocommerce_api_wc_gateway_simplify_commerce', array( $this, 'return_handler' ) );
	}

	function init_moneris_api() {
		// Include Api
		require_once( dirname( __FILE__ ) . '/api/mpgClasses.php' );
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		global $MPG_Moneris_Payment_Gateway;
		$this->form_fields = array(
			'enabled' => array(
				'title'       => __( 'Enable/Disable', $MPG_Moneris_Payment_Gateway->text_domain ),
				'label'       => __( 'Enable Moneris Gateway', $MPG_Moneris_Payment_Gateway->text_domain ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'yes',
			),
			'title' => array(
				'title'       => __( 'Title', $MPG_Moneris_Payment_Gateway->text_domain ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', $MPG_Moneris_Payment_Gateway->text_domain ),
				'default'     => __( 'Moneris', $MPG_Moneris_Payment_Gateway->text_domain ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', $MPG_Moneris_Payment_Gateway->text_domain ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', $MPG_Moneris_Payment_Gateway->text_domain ),
				'default'     => 'Pay with your credit card via moneris by MasterCard.',
				'desc_tip'    => true,
			),
			'store_id' => array(
				'title'		=> __( 'Store Id', $MPG_Moneris_Payment_Gateway->text_domain ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Enter your Moneris account store id here.', $MPG_Moneris_Payment_Gateway->text_domain ),
			),
			'api_token' => array(
				'title'		=> __( 'API Token', $MPG_Moneris_Payment_Gateway->text_domain ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Enter your Moneris API Token here.', $MPG_Moneris_Payment_Gateway->text_domain ),
			),
			'country_code' => array(
				'title'		=> __( 'Integration Country', $MPG_Moneris_Payment_Gateway->text_domain ),
				'type'		=> 'select',
				'desc_tip' => __( 'Is your Moneris account based in the US or Canada?', $MPG_Moneris_Payment_Gateway->text_domain ),
				'default'  => 'CA',
				'options' => array(
					'CA' => __( 'Canada', $MPG_Moneris_Payment_Gateway->text_domain ),
					'US' => __( 'United States', $MPG_Moneris_Payment_Gateway->text_domain ),
				)
			),
			'crypt_type' => array(
				'title'		=> __( 'E-Commerce indicator', $MPG_Moneris_Payment_Gateway->text_domain ),
				'type'		=> 'select',
				'desc_tip'	=> __( 'Select your E-Commerce indicator.', $MPG_Moneris_Payment_Gateway->text_domain ),
				'default'     => '7',
				'options' => array(
					'1' => __( 'Mail Order / Telephone Order—Single', $MPG_Moneris_Payment_Gateway->text_domain ),
					'2' => __( 'Mail Order / Telephone Order—Recurring', $MPG_Moneris_Payment_Gateway->text_domain ),
					'3' => __( 'Mail Order / Telephone Order—Instalment', $MPG_Moneris_Payment_Gateway->text_domain ),
					'4' => __( 'Mail Order / Telephone Order—Unknown classification', $MPG_Moneris_Payment_Gateway->text_domain ),
					'5' => __( 'Authenticated e-commerce transaction (VBV)', $MPG_Moneris_Payment_Gateway->text_domain ),
					'6' => __( 'Non-authenticated e-commerce transaction (VBV)', $MPG_Moneris_Payment_Gateway->text_domain ),
					'7' => __( 'SSL-enabled merchant', $MPG_Moneris_Payment_Gateway->text_domain ),
					'8' => __( 'Non-secure transaction (web- or email-based)', $MPG_Moneris_Payment_Gateway->text_domain ),
					'9' => __( 'SET non-authenticated transaction', $MPG_Moneris_Payment_Gateway->text_domain ),
				)
			),
			'sandbox' => array(
				'title'       => __( 'Sandbox', $MPG_Moneris_Payment_Gateway->text_domain ),
				'label'       => __( 'Enable sandbox mode', $MPG_Moneris_Payment_Gateway->text_domain ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in sandbox mode.', $MPG_Moneris_Payment_Gateway->text_domain ),
				'default'     => 'yes',
			),
		);
	}

	/**
	 * Payment form on checkout page.
	 */
	public function payment_fields() {
		$description = $this->get_description();

		if ( 'yes' == $this->sandbox ) {
			$description .= ' ' . sprintf( __( 'TEST MODE ENABLED. Use a test card: %s', 'woocommerce' ), '<a href="https://developer.moneris.com/More/Testing/Testing%20a%20Solution" target="_blank">https://developer.moneris.com/More/Testing/Testing a Solution</a>' );
		}

		if ( $description ) {
			echo wpautop( wptexturize( trim( $description ) ) );
		}

		if ( $this->supports( 'default_credit_card_form' ) ) {
			$this->credit_card_form(); // Deprecated, will be removed in a future version.
		}
	}

    /**
     * Validate card
     */
	public function validate_fields() {
        date_default_timezone_set(get_option('timezone_string'));
	    $moneris_approved_response_code_array = array('000','001','002','003','004','005','006','007','008','009','023','024','025','026','027','028','029');
        $store_id = $this->store_id;
        $api_token = $this->api_token;
        $environment = ( $this->sandbox == "yes" ) ? 'true' : 'false';
        list($month, $year) = explode("/", $_POST[$this->id.'-card-expiry']); //MMYY
        $newexpdate = $year.$month; //YYMM

        $txnArray=array('type'=>'card_verification',
            'order_id'=>'ord-'.date("dmy-G:i:s"),
            'cust_id'=> get_current_user_id(),
            'pan'=> $_POST[$this->id.'-card-number'],
            'expdate'=> $newexpdate,
            'crypt_type'=> $this->crypt_type
        );

        $mpgTxn = new mpgTransaction($txnArray);

        $mpgRequest = new mpgRequest($mpgTxn);
        $mpgRequest->setProcCountryCode($this->country_code);
        $mpgRequest->setTestMode($environment);

        $mpgHttpPost = new mpgHttpsPost($store_id,$api_token,$mpgRequest);

        $mpgResponse = $mpgHttpPost->getMpgResponse();
        $responsecode = $mpgResponse->getResponseCode();
        $responsemsg = $mpgResponse->getMessage();
        if(!empty($responsecode) && in_array($responsecode,$moneris_approved_response_code_array)) {
            return true;
        }else{
            if(!empty($responsemsg)){
                wc_add_notice( $mpgResponse->getMessage(), 'error' );
            }
            return false;
        }
    }

}