<?php
	// Setup Moneris Gateway - Credit/Debit Card Payment
	class VTD_MONERIS_GATEWAY extends WC_Payment_Gateway {
		function __construct() {
			// The global ID for this Payment method
			$this->id = "vtd_moneris_gateway";

			// The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
			$this->method_title = __( "Moneris - WooCommerce Gateway", 'vtd-moneris-gateway' );

			// The description for this Payment Gateway, shown on the actual Payment options page on the backend
			$this->method_description = __( "Moneris - WooCommerce Payment Gateway Plug-in for WooCommerce", 'vtd-moneris-gateway' );

			// The title to be used for the vertical tabs that can be ordered top to bottom
			$this->title = __( "Moneris - WooCommerce Gateway", 'vtd-moneris-gateway' );

			// If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
			$this->icon = WC_HTTPS::force_https_url( plugins_url( '/assets/images/moneris.png', __FILE__ ) );

			// Bool. Can be set to true if you want payment fields to show on the checkout 
			// if doing a direct integration, which we are doing in this case
			$this->has_fields = true;

			// Supports the default credit card form
			$this->supports = array( 'default_credit_card_form', 'refunds' );

			// This basically defines your settings which are then loaded with init_settings()
			$this->init_form_fields();

			// After init_settings() is called, you can get the settings and load them into variables, e.g:
			// $this->title = $this->get_option( 'title' );
			$this->init_settings();
			
			// Turn these settings into variables we can use
			foreach ( $this->settings as $setting_key => $value ) {
				$this->$setting_key = $value;
			}
			
			// Lets check for SSL
			add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );
			
			// Save settings
			if ( is_admin() ) {
				// Versions over 2.0
				// Save our administration options. Since we are not going to be doing anything special
				// we have not defined 'process_admin_options' in this class so the method in the parent
				// class will be used instead
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			}		
		}
		
		// Card No. validation
		function validate_card_number( $card_number ) {
			$number = preg_replace( '/\D/', '', $card_number );
			$number_length = strlen( $number );
			$parity = $number_length % 2;
			$total=0;
			
			for ($i=0;$i<$number_length;$i++) {
				$digit = $number[$i];
				if ( $i % 2 == $parity ) {
					$digit *= 2;
					if ( $digit > 9 ) {
						$digit -= 9;
					}
				}
				$total += $digit;
			}
			return ( $total % 10 == 0 ) ? TRUE : FALSE;
		}
		
		// Card code validation
		function validate_card_code( $card_code ) {
			$exp_date = str_replace( ' ', '', $card_code );
			$exp_date_array = explode('/', $exp_date);
			if( $exp_date_array[1] < date('y')){
				return false;
			} else if( $exp_date_array[0] < date('m')){
				return false;
			} else {
				return true;
			}
		}
		
		// Validate fields
		public function validate_fields() {
			if( $this->validate_card_number( $_POST['vtd_moneris_gateway-card-number'] ) === FALSE ) {
				wc_add_notice( 'Please, check your entered card number. It seems to be an invalid card number.', 'error' );
				return false;
			} else if( !preg_match( '/^[0-9]{3,4}$/', $_POST['vtd_moneris_gateway-card-cvc'] ) ) {
				wc_add_notice( 'Please, check your entered card code. It seems to be an invalid card code.', 'error' );
				return false;
			} else if( $this->validate_card_code( $_POST['vtd_moneris_gateway-card-expiry'] ) === FALSE ) {
				wc_add_notice( 'Your card already expired. Please, use another card.', 'error' );
				return false;
			} else {
				return true;
			}
		}
		
		// Check if we are forcing SSL on checkout pages
		// Custom function not required by the Gateway
		public function do_ssl_check() {
			if( $this->enabled == "yes" ) {
				if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
					echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";	
				}
			}		
		}
		
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title'		=> __( 'Enable / Disable', 'vtd-moneris-gateway' ),
					'label'		=> __( 'Enable this payment gateway', 'vtd-moneris-gateway' ),
					'type'		=> 'checkbox',
					'default'	=> 'no',
				),
				'title' => array(
					'title'		=> __( 'Title', 'vtd-moneris-gateway' ),
					'type'		=> 'text',
					'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'vtd-moneris-gateway' ),
					'default'	=> __( 'Moneris', 'vtd-moneris-gateway' ),
				),
				'store_id' => array(
					'title'		=> __( 'Store Id', 'vtd-moneris-gateway' ),
					'type'		=> 'text',
					'desc_tip'	=> __( 'Enter your Moneris account store id here.', 'vtd-moneris-gateway' ),
				),
				'api_key' => array(
					'title'		=> __( 'API Key', 'vtd-moneris-gateway' ),
					'type'		=> 'text',
					'desc_tip'	=> __( 'Enter your Moneris API key here.', 'vtd-moneris-gateway' ),
				),
				'env_code' => array(
					'title'		=> __( 'Choose Payment Environment', 'vtd-moneris-gateway' ),
					'type'		=> 'select',
					'desc_tip'	=> __( 'Select your payment environment.', 'vtd-moneris-gateway' ),
					'options' => array(
						'CA' => 'Canada',
						'US' => 'United States',
					)
				),
				'crypt_type' => array(
					'title'		=> __( 'E-Commerce indicator', 'vtd-moneris-gateway' ),
					'type'		=> 'select',
					'desc_tip'	=> __( 'Select your E-Commerce indicator.', 'vtd-moneris-gateway' ),
					'options' => array(
						'1' => 'Mail Order / Telephone Order—Single',
						'2' => 'Mail Order / Telephone Order—Recurring',
						'3' => 'Mail Order / Telephone Order—Instalment',
						'4' => 'Mail Order / Telephone Order—Unknown classification',
						'5' => 'Authenticated e-commerce transaction (VBV)',
						'6' => 'Non-authenticated e-commerce transaction (VBV)',
						'7' => 'SSL-enabled merchant',
						'8' => 'Non-secure transaction (web- or email-based)',
						'9' => 'SET non-authenticated transaction'
					)
				),
				'payment_mode' => array(
					'title'		=> __( 'Enable Test Mode', 'vtd-moneris-gateway' ),
					'label'		=> __( 'Manage your transaction mode. Check to make your gateway in test mode.', 'vtd-moneris-gateway' ),
					'type'		=> 'checkbox',
					'default'	=> 'no',
				)
			);		
		}
		
		// Submit payment and handle response
		public function process_payment( $order_id ) {
			global $woocommerce;
	
			// Get this Order's information so that we know
			// who to charge and how much
			$customer_order = new WC_Order( $order_id );
			
			$order_id = $customer_order->id;
			$store_id = $this->store_id;
			$api_token = $this->api_key;
			$payment_mode = ( $this->payment_mode == 'yes' ) ? true : false;
			
			$type = 'purchase';
			$cust_id = get_current_user_id();
			$amount = $customer_order->order_total;
			$pan = str_replace( array(' ', '-' ), '', $_POST['vtd_moneris_gateway-card-number'] );
			$expiry_date = str_replace( array( '/', ' '), '', $_POST['vtd_moneris_gateway-card-expiry'] );
			$crypt = $this->crypt_type;
			$dynamic_descriptor = ( isset( $_POST['vtd_moneris_gateway-card-cvc'] ) ) ? $_POST['vtd_moneris_gateway-card-cvc'] : '';
			$status_check = 'false';
			
			/** Customer Information Object */
			$vtd_moneris_gateway_customer_info = new mpgCustInfo();

			/** Set Customer Information - Billing */
			$billing = array(
				'first_name' => get_post_meta( $order_id, '_billing_first_name', true ),
				'last_name' => get_post_meta( $order_id, '_billing_last_name', true ),
				'company_name' => get_post_meta( $order_id, '_billing_company', true ),
				'address' => get_post_meta( $order_id, '_billing_address_1', true ),
				'city' => get_post_meta( $order_id, '_billing_city', true ),
				'province' => get_post_meta( $order_id, '_billing_state', true ),
				'postal_code' => get_post_meta( $order_id, '_billing_postcode', true ),
				'country' => get_post_meta( $order_id, '_billing_country', true ),
				'phone_number' => get_post_meta( $order_id, '_billing_phone', true ),
				'shipping_cost' => get_post_meta( $order_id, '_order_shipping', true )
            );
			$vtd_moneris_gateway_customer_info->setBilling( $billing );

			/** Set Customer Information - Shipping */
			$shipping = array(
				'first_name' => get_post_meta( $order_id, '_shipping_first_name', true ),
				'last_name' => get_post_meta( $order_id, '_shipping_last_name', true ),
				'company_name' => get_post_meta( $order_id, '_shipping_company', true ),
				'address' => get_post_meta( $order_id, '_shipping_address_1', true ),
				'city' => get_post_meta( $order_id, '_shipping_city', true ),
				'province' => get_post_meta( $order_id, '_shipping_state', true ),
				'postal_code' => get_post_meta( $order_id, '_shipping_postcode', true ),
				'country' => get_post_meta( $order_id, '_shipping_country', true ),
				'phone_number' => get_post_meta( $order_id, '_billing_phone', true ),
				'shipping_cost' => get_post_meta( $order_id, '_order_shipping', true )
            );
			$vtd_moneris_gateway_customer_info->setShipping( $shipping );

			$vtd_moneris_gateway_customer_info->setEmail( get_post_meta( $order_id, '_billing_email', true ) );
			$vtd_moneris_gateway_customer_info->setInstructions( $customer_order->customer_note );

			/** Set Line Item Information */
			$i = 0;
			$items = $customer_order->get_items();

			foreach ( $items as $item ) {
				$itemsArray = array();
				$product_id = ( $item['variation_id'] > 0 ) ? $item['variation_id'] : $item['product_id'];
				$itemsArray[$i] = array(
					'name'			  => get_the_title( $item['product_id'] ),
					'quantity'		  => $item['qty'],
					'product_code'	  => $product_id,
					'extended_amount' => $item['line_total']
				);
				$vtd_moneris_gateway_customer_info->setItems( $itemsArray[$i] );
				$i++;
			}
			
			/** Transactional Associative Array */
			$txnArray = array(
				'type' 				 => $type,
     		    'order_id' 			 => $order_id,
     		    'cust_id'			 => $cust_id,
    		    'amount'			 => $amount,
   			    'pan'				 => $pan,
   			    'expdate'			 => $expiry_date,
   			    'crypt_type'		 => $crypt,
   			    'dynamic_descriptor' => $dynamic_descriptor
   		    );
			
			/** Transaction Object */
			$vtd_moneris_gateway_transaction = new mpgTransaction( $txnArray );
			$vtd_moneris_gateway_transaction->setCustInfo( $vtd_moneris_gateway_customer_info );
			
			/** Request Object */
			$vtd_moneris_gateway_request = new mpgRequest( $vtd_moneris_gateway_transaction );
			$vtd_moneris_gateway_request->setProcCountryCode( $this->env_code );
			//$vtd_moneris_gateway_request->setTestMode( $payment_mode );
			
			/** HTTPS/HTTP Post Object */
			$vtd_moneris_gateway_post = new mpgHttpsPostStatus( $store_id, $api_token, $status_check, $vtd_moneris_gateway_request );
			//$vtd_moneris_gateway_post = new mpgHttpsPost( $store_id, $api_token, $vtd_moneris_gateway_request );

			/** Response */
			$vtd_moneris_gateway_response = $vtd_moneris_gateway_post->getMpgResponse();

			// transaction was a success
			if ( ( $vtd_moneris_gateway_response->getResponseCode() != 'null' ) && ( $vtd_moneris_gateway_response->getResponseCode() < 50 ) && $vtd_moneris_gateway_response->getComplete() ) {
				// Payment has been successful
				//$customer_order->add_order_note( 'Gateway Response: ' . $vtd_moneris_gateway_response->getMessage(), 'vtd-moneris-gateway' );
				$customer_order->add_order_note( __( 'Moneris payment completed.', 'vtd-moneris-gateway' ) );
				
				// Add Transaction details
				$_date = $vtd_moneris_gateway_response->getTransDate() . ' ' . $vtd_moneris_gateway_response->getTransTime();
				add_post_meta( $order_id, '_paid_date', $_date, true );
				add_post_meta( $order_id, '_transaction_id', $vtd_moneris_gateway_response->getTxnNumber(), true );
				add_post_meta( $order_id, '_completed_date', $_date, true );
				add_post_meta( $order_id, '_reference_no', $vtd_moneris_gateway_response->getReferenceNum(), true );
				add_post_meta( $order_id, '_response_code', $vtd_moneris_gateway_response->getResponseCode(), true );
				add_post_meta( $order_id, '_iso_code', $vtd_moneris_gateway_response->getISO(), true );
				add_post_meta( $order_id, '_authorization_code', $vtd_moneris_gateway_response->getAuthCode(), true );
				add_post_meta( $order_id, '_transaction_type', $vtd_moneris_gateway_response->getTransType(), true );
				add_post_meta( $order_id, '_card_type', $vtd_moneris_gateway_response->getCardType(), true );
				add_post_meta( $order_id, '_dynamic_descriptor', $dynamic_descriptor, true );

				// Mark order as Paid
				$customer_order->payment_complete();

				// Empty the cart (Very important step)
				$woocommerce->cart->empty_cart();

				// Redirect to thank you page
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $customer_order ),
				);
			} else {
				wc_add_notice( 'Unable to complete your payment using Moneris. Please, try again later.', 'error' );
				
				$customer_order->add_order_note( 'Error: '. $vtd_moneris_gateway_response->getMessage() );
			}
		}
		
		// Submit Refund and handle response
		/*public function process_refund( $order_id, $amount = null ) {
			global $woocommerce;
	
			// Get this Order's information so that we know
			// who to charge and how much
			$customer_order = new WC_Order( $order_id );
			
			$store_id = $this->store_id;
			$api_token = $this->api_key;
			$payment_mode = ( $this->payment_mode == 'yes' ) ? true : false;
			
			$txnnumber = get_post_meta( $order_id, '_transaction_id', true );
			$dynamic_descriptor = get_post_meta( $order_id, '_dynamic_descriptor', true );

			if( $this->env_code == 'CA' ) {
				$txnArray = array(
					'type'				 => 'refund',
					'txn_number'		 => $txnnumber,
					'order_id'			 => $order_id,
					'amount'		     => $amount,
					'crypt_type'		 => $this->crypt_type,
					'cust_id'			 => get_post_meta( $order_id, '_customer_user', true ),
					'dynamic_descriptor' => $dynamic_descriptor
				);
			} else if( $this->env_code == 'US' ) {
				$txnArray = array(
					'type'				 => 'refund',
					'txn_number'		 => $txnnumber,
					'order_id'			 => $order_id,
					'amount'		     => $amount,
					'crypt_type'		 => $this->crypt_type
				);
			}
			

			$vtd_moneris_gateway_refund = new mpgTransaction( $txnArray );
			
			$vtd_moneris_gateway_refund_request = new mpgRequest( $vtd_moneris_gateway_refund );
			$vtd_moneris_gateway_refund_request->setProcCountryCode( $this->env_code );
			$vtd_moneris_gateway_refund_request->setTestMode( $payment_mode );

			$vtd_moneris_gateway_refund_post = new mpgHttpsPost( $store_id, $api_token, $vtd_moneris_gateway_refund_request );


			$vtd_moneris_gateway_refund_response = $vtd_moneris_gateway_refund_post->getMpgResponse();

			// refund process
			if ( !is_null( $vtd_moneris_gateway_response->getResponseCode() ) && ( $vtd_moneris_gateway_refund_response->getResponseCode() < 50 ) && $vtd_moneris_gateway_refund_response->getComplete() ) {
				$customer_order->add_order_note( __( 'Refunded ' . $amount . ' - Transaction Id ' . $vtd_moneris_gateway_refund_response->getTxnNumber() . ' - Reference No ' . $vtd_moneris_gateway_refund_response->getReferenceNum(), 'vtd-moneris-gateway' ) );
				
				return true;
			} else {
				$customer_order->add_order_note( __( 'Unable to refund - ' . $amount, 'vtd-moneris-gateway' ) );
				
				return false;
			}
		}*/
	}