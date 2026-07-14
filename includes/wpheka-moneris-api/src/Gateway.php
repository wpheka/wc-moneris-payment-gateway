<?php

namespace wpheka\Moneris;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/mpgClasses.php';

class Gateway {


	const ENV_LIVE    = 'live';
	const ENV_STAGING = 'staging';
	const CRYPT_TYPE = '7';

	/**
	 * The environment used for connecting to the Moneris API.
	 *
	 * @var string
	 */
	protected $environment;

	/**
	 * The Moneris Store ID.
	 *
	 * @var string
	 */
	protected $store_id;

	/**
	 * The Moneris API Token.
	 *
	 * @var string
	 */
	protected $api_token;

	/**
	 * The extra parameters needed for Moneris.
	 *
	 * @var array
	 */
	protected $params;

	/**
	 * WC payment gateway id.
	 *
	 * @var array
	 */
	protected $gateway_id;

	/**
	 * Raw XML string sent to Moneris API on last request.
	 *
	 * @var string
	 */
	protected $last_request_xml = '';

	/**
	 * Checkout form posted data.
	 *
	 * @var array
	 */
	protected $posted_data;

	/**
	 * WooCommerce Order Object.
	 *
	 * @var object
	 */
	protected $order;

	/**
	 * Create a new Moneris instance.
	 *
	 * @param string $store_id  Moneris store id.
	 * @param string $api_token Moneris api token.
	 * @param array  $params    moneris parameters.
	 */
	public function __construct( $store_id = '', $api_token = '', array $params = array() ) {
		$this->store_id = $store_id;
		$this->api_token = $api_token;
		$this->environment = isset( $params['environment'] ) ? $params['environment'] : self::ENV_LIVE;
		$this->gateway_id = $params['gateway_id'];
		$this->order = $params['order'];
		$this->posted_data = $params['posted_data'];
		$this->params = $params;
	}

	/**
	 * Get credit card number
	 *
	 * @return string card number
	 */
	protected function get_card_number() {
		$card_number = str_replace( array( ' ', '-' ), '', wc_clean( wp_unslash( $this->posted_data[ $this->gateway_id . '-card-number' ] ) ) );
		return $card_number;
	}

	/**
	 * Get card expiry
	 *
	 * @return string card expiry
	 */
	protected function get_card_expiry() {
		$card_expiry = wc_clean( wp_unslash( $this->posted_data[ $this->gateway_id . '-card-expiry' ] ) );
		if ( ! empty( $card_expiry ) ) {
			$parts     = array_map( 'trim', explode( '/', $card_expiry ) );
			$cardmonth = $parts[0];
			$cardyear  = isset( $parts[1] ) ? $parts[1] : '';
			$card_expiry = $cardyear . $cardmonth;
		}
		return $card_expiry;
	}

	/**
	 * Get WC order id
	 *
	 * @return integer order id
	 */
	protected function get_order_id() {
		$order_id = $this->order->get_id();
		if ( 'staging' == $this->environment ) {
			$order_id = 'wc-order-' . gmdate( 'dmy-G:i:s' ); // Fix duplicate order issue.
		}

		return $order_id;
	}

	/**
	 * Get customer id
	 *
	 * @return integer customer id
	 */
	protected function get_customer_id() {
		return $this->order->get_user_id();
	}

	/**
	 * Get order amount
	 *
	 * @return float order total
	 */
	protected function get_order_amount() {
		return $this->order->get_total();
	}

	/**
	 * Escape a value for safe embedding in the Moneris request XML.
	 *
	 * @param  string $value Raw value.
	 * @return string
	 */
	protected function xml_escape( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	/**
	 * Get the order's tax totals mapped to Moneris tax1/tax2/tax3 fields.
	 *
	 * Each WooCommerce tax line (e.g. GST, PST) maps to one field, in order.
	 * Any lines beyond the third are summed into tax3.
	 *
	 * @return array Three formatted amounts (tax1, tax2, tax3); empty strings when unused.
	 */
	private function get_order_taxes() {
		$amounts = array();

		foreach ( $this->order->get_taxes() as $tax_item ) {
			$amounts[] = (float) $tax_item->get_tax_total() + (float) $tax_item->get_shipping_tax_total();
		}

		if ( empty( $amounts ) && (float) $this->order->get_total_tax() > 0 ) {
			$amounts[] = (float) $this->order->get_total_tax();
		}

		if ( count( $amounts ) > 3 ) {
			$amounts[2] = array_sum( array_slice( $amounts, 2 ) );
			$amounts    = array_slice( $amounts, 0, 3 );
		}

		$taxes = array( '', '', '' );
		foreach ( $amounts as $index => $amount ) {
			$taxes[ $index ] = number_format( $amount, 2, '.', '' );
		}

		return $taxes;
	}

	/**
	 * Get customer information from wc order
	 *
	 * @return object customer information
	 */
	private function get_customer_info() {

		$customer_info = new mpgCustInfo(); // Customer Information Object.

		// Customer Information Variables.
		$first_name = $this->order->get_billing_first_name();
		$last_name = $this->order->get_billing_last_name();
		$company_name = $this->order->get_billing_company();
		$address = $this->order->get_billing_address_1() . ' ' . $this->order->get_billing_address_2();
		$city = $this->order->get_billing_city();
		$province = $this->order->get_billing_state();
		$postal_code = $this->order->get_billing_postcode();
		$country = $this->order->get_billing_country();
		$phone_number = $this->order->get_billing_phone();
		$fax = '';
		list( $tax1, $tax2, $tax3 ) = $this->get_order_taxes();
		$shipping_cost = number_format( $this->order->get_total_shipping(), 2, '.', '' );
		$email = $this->order->get_billing_email();
		$instructions = $this->order->get_customer_note();

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
			'fax' => $fax,
			'tax1' => $tax1,
			'tax2' => $tax2,
			'tax3' => $tax3,
			'shipping_cost' => $shipping_cost,
		);

		$shipping_addr1 = empty( $this->order->get_shipping_address_1() ) ? $this->order->get_billing_address_1() : $this->order->get_shipping_address_1();

		$shipping_addr2 = empty( $this->order->get_shipping_address_2() ) ? $this->order->get_billing_address_2() : $this->order->get_shipping_address_2();

		$shipping_addr = $shipping_addr1 . ' ' . $shipping_addr2;

		$shipping = array(
			'first_name' => empty( $this->order->get_shipping_first_name() ) ? $this->order->get_billing_first_name() : $this->order->get_shipping_first_name(),
			'last_name' => empty( $this->order->get_shipping_last_name() ) ? $this->order->get_billing_last_name() : $this->order->get_shipping_last_name(),
			'company_name' => empty( $this->order->get_shipping_company() ) ? $this->order->get_billing_company() : $this->order->get_shipping_company(),
			'address' => $shipping_addr,
			'city' => empty( $this->order->get_shipping_city() ) ? $this->order->get_billing_city() : $this->order->get_shipping_city(),
			'province' => empty( $this->order->get_shipping_state() ) ? $this->order->get_billing_state() : $this->order->get_shipping_state(),
			'postal_code' => empty( $this->order->get_shipping_postcode() ) ? $this->order->get_billing_postcode() : $this->order->get_shipping_postcode(),
			'country' => empty( $this->order->get_shipping_country() ) ? $this->order->get_billing_country() : $this->order->get_shipping_country(),
			'phone_number' => $phone_number,
			'fax' => $fax,
			'tax1' => $tax1,
			'tax2' => $tax2,
			'tax3' => $tax3,
			'shipping_cost' => $shipping_cost,
		);

		// Set Customer Information. Values are escaped because mpgCustInfo
		// concatenates them into the request XML without encoding.
		$customer_info->setBilling( array_map( array( $this, 'xml_escape' ), $billing ) );

		$customer_info->setShipping( array_map( array( $this, 'xml_escape' ), $shipping ) );

		$customer_info->setEmail( $this->xml_escape( $email ) );
		$customer_info->setInstructions( $this->xml_escape( $instructions ) );

		// Set Customer Line Item Information.
		$i = 0;
		$items = $this->order->get_items();

		foreach ( $items as $item ) {
			$items_array = array();

			if ( ! empty( $item['variation_id'] ) ) {
				$product_id = $item['variation_id'];
			} elseif ( ! empty( $item['product_id'] ) ) {
				$product_id = $item['product_id'];
			}

			$product        = $item->get_product();
			$product_exists = is_object( $product );
			$items_array[ $i ] = array(
				'name' => $this->xml_escape( $item['name'] ),
				'quantity' => $this->xml_escape( $item['qty'] ),
				'product_code' => $this->xml_escape( $product_exists ? $product->get_sku() : $product_id ),
				'extended_amount' => number_format( $item['line_total'], 2, '.', '' ),
			);
			$customer_info->setItems( $items_array[ $i ] );
			$i++;
		}

		return $customer_info;
	}

	/**
	 * Moneris purchase
	 *
	 * @return object moneris response
	 */
	public function purchase() {

		$params = array(
			'type' => 'purchase',
			'order_id' => $this->get_order_id(),
			'cust_id' => $this->get_customer_id(),
			'amount' => $this->get_order_amount(),
			'pan' => $this->get_card_number(),
			'expdate' => $this->get_card_expiry(),
			'crypt_type' => self::CRYPT_TYPE,
		);

		$transaction = $this->transaction( $params );

		return $this->process( $transaction );
	}

	/**
	 * Moneris refund
	 *
	 * @return object moneris response
	 */
	public function refund( $amount, $reason ) {
		// Order object methods work with both HPOS and legacy post-meta storage.
		$txnnumber = $this->order->get_transaction_id();
		$order_id  = $this->order->get_meta( '_refund_order_id' );

		$params = array(
			'type' => 'refund',
			'txn_number' => $txnnumber,
			'order_id' => $order_id,
			'amount' => $amount,
			'crypt_type' => self::CRYPT_TYPE,
			'cust_id' => $this->get_customer_id(),
			'dynamic_descriptor' => ! empty( $reason ) ? $this->xml_escape( $reason ) : 'refund',
		);

		$transaction = $this->transaction( $params );

		return $this->process( $transaction );
	}

	/**
	 * Create a new Request instance.
	 *
	 * @param  object $transaction Moneris transaction.
	 * @return object              Moneris request object
	 */
	protected function request( $transaction ) {
		$request = new mpgRequest( $transaction );
		$request->setProcCountryCode( 'CA' );

		if ( 'staging' == $this->environment ) {
			$request->setTestMode( true ); // false or comment out this line for production transactions.
		}

		return $request;
	}

	/**
	 * Create new moneris transaction instance.
	 *
	 * @param  array $params gateway parameters.
	 * @return object         transaction instance
	 */
	protected function transaction( $params ) {
		$transaction = new mpgTransaction( $params );

		$customer_info = $this->get_customer_info();
		$transaction->setCustInfo( $customer_info );

		return $transaction;
	}

	/**
	 * Process a transaction through the Moneris API.
	 *
	 * @param  object $transaction transaction object.
	 * @return object              process respoanse
	 */
	protected function process( $transaction ) {
		// Request Object.
		$request = $this->request( $transaction );

		// HTTPS Post Object.
		$https_post = new mpgHttpsPost( $this->store_id, $this->api_token, $request );

		// Store raw request XML for logging.
		$this->last_request_xml = $https_post->xmlString;

		// Response.
		$response = $https_post->getMpgResponse();
		return $response;
	}

	/**
	 * Returns the raw XML string sent to the Moneris API on the last request.
	 *
	 * @return string
	 */
	public function getLastRequestXml() {
		return $this->last_request_xml;
	}
}
