<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class mpg_WOO_Moneris_Payment_Gateway extends WC_Payment_Gateway_CC {

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
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
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
				'default'     => 'Pay with your credit card via moneris.',
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
				'class'     => 'wc-enhanced-select',
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
				'class'     => 'wc-enhanced-select',
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
			parent::payment_fields();
		}
	}	

	public function process_payment($order_id) {
		global $MPG_Moneris_Payment_Gateway,$woocommerce;
		$customer_order = new WC_Order($order_id);

		/************************ Request Variables ***************************/
		$store_id = $this->store_id;
		$api_token = $this->api_token;

		/********************* Transactional Variables ************************/
		$type = 'purchase';
		$cust_id = $customer_order->get_user_id();
		$amount = $customer_order->order_total;
		$pan = str_replace( array(' ', '-' ), '', $_POST[$this->id.'-card-number'] );
		$expiry_date = $_POST[$this->id.'-card-expiry'];
		if(!empty($expiry_date)) {
			$expiry_date = explode('/',$expiry_date);
			list($cardmonth, $cardyear) = $expiry_date;
			$expiry_date = $cardyear.$cardmonth;
		}
		$crypt = $this->crypt_type;
		$dynamic_descriptor = ( isset( $_POST[$this->id.'-card-cvc'] ) ) ? $_POST[$this->id.'-card-cvc'] : '';
		$payment_mode = ( $this->sandbox == 'yes' ) ? true : false;
		$status_check = 'false';

		/******************* Customer Information Variables ********************/

		$first_name = $customer_order->get_billing_first_name();
		$last_name = $customer_order->get_billing_last_name();
		$company_name = $customer_order->get_billing_company();
		$address = $customer_order->get_billing_address_1().' '.$customer_order->get_billing_address_2();
		$city = $customer_order->get_billing_city();
		$province = $customer_order->get_billing_state();
		$postal_code = $customer_order->get_billing_postcode();
		$country = $customer_order->get_billing_country();
		$phone_number = $customer_order->get_billing_phone();
//		$fax = '453-989-9877';
//		$tax1 = '1.01';
//		$tax2 = '1.02';
//		$tax3 = '1.03';
		$shipping_cost = wc_format_decimal( $customer_order->get_shipping_total(), 2 );
		$email = $customer_order->get_billing_email();
		$instructions = $customer_order->get_customer_note();

		/******************** Customer Information Object *********************/

		$mpgCustInfo = new mpgCustInfo();

		/********************** Set Customer Information **********************/

		$billing = array(
			'first_name' => $first_name,
			'last_name' => $last_name,
			'company_name' => $company_name,
			'address' => $address,
			'city' => $city,
			'province' => $province,
			'postal_code' => $postal_code,
			'country' => $country,
			'phone_number' => $phone_number,
//			'fax' => $fax,
//			'tax1' => $tax1,
//			'tax2' => $tax2,
//			'tax3' => $tax3,
			'shipping_cost' => $shipping_cost
		);

		$mpgCustInfo->setBilling($billing);

		$shipping = array(
			'first_name' => $customer_order->get_shipping_first_name(),
			'last_name' => $customer_order->get_shipping_last_name(),
			'company_name' => $customer_order->get_shipping_company(),
			'address' => $customer_order->get_shipping_address_1().' '.$customer_order->get_shipping_address_2(),
			'city' => $customer_order->get_shipping_city(),
			'province' => $customer_order->get_shipping_state(),
			'postal_code' => $customer_order->get_shipping_postcode(),
			'country' => $customer_order->get_shipping_country(),
			'phone_number' => $phone_number,
//			'fax' => $fax,
//			'tax1' => $tax1,
//			'tax2' => $tax2,
//			'tax3' => $tax3,
			'shipping_cost' => $shipping_cost
		);

		$mpgCustInfo->setShipping($shipping);

		$mpgCustInfo->setEmail($email);
		$mpgCustInfo->setInstructions($instructions);

		/*********************** Set Line Item Information *********************/

		$i = 0;
		$items = $customer_order->get_items();

		foreach ( $items as $item ) {
			$itemsArray = array();
			$product_id = ( $item['variation_id'] > 0 ) ? $item['variation_id'] : $item['product_id'];
			$itemsArray[$i] = array(
				'name' => get_the_title( $item['product_id'] ),
				'quantity' => $item['qty'],
				'product_code' => $product_id,
				'extended_amount' => $item['line_total']
			);
			$mpgCustInfo->setItems( $itemsArray[$i] );
			$i++;
		}

		/************************** CVD Variables *****************************/

		$cvd_indicator = '1';
		$cvd_value = ( isset( $_POST[$this->id.'-card-cvc'] ) ) ? $_POST[$this->id.'-card-cvc'] : '';

		/********************** CVD Associative Array *************************/

		$cvdTemplate = array(
		'cvd_indicator' => $cvd_indicator,
		'cvd_value' => $cvd_value
		);

		/************************** CVD Object ********************************/

		$mpgCvdInfo = new mpgCvdInfo($cvdTemplate);

		/***************** Transactional Associative Array ********************/
		if($this->sandbox == 'yes') {
			date_default_timezone_set(get_option('timezone_string'));
			$order_id='ord-'.date("dmy-G:i:s"); // Fix duplicate order issue
		}
		$txnArray=array(
			'type'=> $type,
			'order_id'=> strval($order_id),
			'cust_id'=> strval($cust_id),
			'amount'=> strval($amount),
			'pan'=> $pan,
			'expdate'=> $expiry_date,
			'crypt_type'=> strval($crypt)
		);

		/********************** Transaction Object ****************************/

		$mpgTxn = new mpgTransaction($txnArray);

		/******************** Set Customer Information ************************/

		$mpgTxn->setCustInfo($mpgCustInfo);

		/************************ Set CVD *****************************/
		
		$mpgTxn->setCvdInfo($mpgCvdInfo);

		/************************* Request Object *****************************/

		$mpgRequest = new mpgRequest($mpgTxn);
		$mpgRequest->setProcCountryCode($this->country_code);
		if($this->sandbox == 'yes') {
			$mpgRequest->setTestMode(true);
		}

		/************************ HTTPS Post Object ***************************/

		$mpgHttpPost = new mpgHttpsPost($store_id,$api_token,$mpgRequest);
//		$mpgHttpPost = new mpgHttpsPostStatus($store_id,$api_token,$status_check,$mpgRequest);

		/*************************** Response *********************************/

		$mpgResponse = $mpgHttpPost->getMpgResponse();	
		
		if ( ( $mpgResponse->getResponseCode() != 'null' ) && ( $mpgResponse->getResponseCode() < 50 ) && $mpgResponse->getComplete() ) {
			// Payment has been successful
			$customer_order->add_order_note( __( 'Moneris payment completed.', $MPG_Moneris_Payment_Gateway->text_domain ) );

			// Add Transaction details
			$_date = $mpgResponse->getTransDate() . ' ' . $mpgResponse->getTransTime();
			add_post_meta( $order_id, '_paid_date', $_date, true );
			add_post_meta( $order_id, '_transaction_id', $mpgResponse->getTxnNumber(), true );
			add_post_meta( $order_id, '_completed_date', $_date, true );
			add_post_meta( $order_id, '_reference_no', $mpgResponse->getReferenceNum(), true );
			add_post_meta( $order_id, '_response_code', $mpgResponse->getResponseCode(), true );
			add_post_meta( $order_id, '_iso_code', $mpgResponse->getISO(), true );
			add_post_meta( $order_id, '_authorization_code', $mpgResponse->getAuthCode(), true );
			add_post_meta( $order_id, '_transaction_type', $mpgResponse->getTransType(), true );
			add_post_meta( $order_id, '_card_type', $mpgResponse->getCardType(), true );
			add_post_meta( $order_id, '_dynamic_descriptor', $dynamic_descriptor, true );
			add_post_meta( $order_id, '_card_cvd', $cvd_value, true );
			add_post_meta( $order_id, '_country_code', $this->country_code, true );
			if($this->sandbox == 'yes') {
				add_post_meta( $order_id, '_sandbox_order_id', $mpgResponse->getReceiptId(), true );
			}	

			// Mark order as Paid
			$customer_order->payment_complete();
			
			// Reduce stock levels
			$customer_order->reduce_order_stock();

			// Empty the cart (Very important step)
			$woocommerce->cart->empty_cart();

			// Redirect to thank you page
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $customer_order ),
			);
		} else {
			wc_add_notice( __('Payment error: '.$mpgResponse->getMessage(), $MPG_Moneris_Payment_Gateway->text_domain), 'error' );
			$customer_order->add_order_note( __( $mpgResponse->getMessage(), $MPG_Moneris_Payment_Gateway->text_domain ) );
			return;
		}
	}
	
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		global $MPG_Moneris_Payment_Gateway;
		$txnnumber = get_post_meta( $order_id, '_transaction_id', true );
		$customer_order = new WC_Order($order_id);
		$order_country_code = get_post_meta( $order_id, '_country_code', true );
		if($this->sandbox == 'yes') {
			$order_id= get_post_meta( $order_id, '_sandbox_order_id', true );
		}	
		//Refund transaction object mandatory values
		// step 1) create transaction array
		$txnArray=array(
				'type'=>'refund',
				'txn_number'=>$txnnumber,
				'order_id'=>$order_id,
				'amount'=>$amount,
				'crypt_type'=> $this->crypt_type,
				'cust_id'=> $customer_order->get_user_id(),
				);

		// step 2) create a transaction  object passing the array created in step 1.
		$mpgTxn = new mpgTransaction($txnArray);
		
		// step 3) create a mpgRequest object passing the transaction object created in step 2
		$mpgRequest = new mpgRequest($mpgTxn);
		$mpgRequest->setProcCountryCode($order_country_code);
		if($this->sandbox == 'yes') {
			$mpgRequest->setTestMode(true);
		}
		// step 4) create mpgHttpsPost object which does an https post ##
		$mpgHttpPost = new mpgHttpsPost($store_id,$api_token,$mpgRequest);
		
		// step 5) get an mpgResponse object ##
		$mpgResponse=$mpgHttpPost->getMpgResponse();
		if($mpgResponse->getComplete()) {
		$customer_order->add_order_note( __( 'Amount Refunded: '.$amount, $MPG_Moneris_Payment_Gateway->text_domain ) );
			return true;
		}else{
			return false;
		}
	}
}
