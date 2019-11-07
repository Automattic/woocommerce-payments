<?php
/**
 * WC_Payments_API_Client class
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Communicates with WooCommerce Payments API.
 */
class WC_Payments_API_Client {

	const ENDPOINT_BASE          = 'https://public-api.wordpress.com/wpcom/v2';
	const ENDPOINT_SITE_FRAGMENT = 'sites/%s';
	const ENDPOINT_REST_BASE     = 'wcpay';

	const STRIPE_ACCOUNT_TRANSIENT = 'wcpay_stripe_account';

	const POST = 'POST';
	const GET  = 'GET';

	const ACCOUNTS_API     = 'accounts';
	const CHARGES_API      = 'charges';
	const INTENTIONS_API   = 'intentions';
	const REFUNDS_API      = 'refunds';
	const TRANSACTIONS_API = 'transactions';
	const OAUTH_API        = 'oauth';

	/**
	 * User agent string to report in requests.
	 *
	 * @var string
	 */
	private $user_agent;

	/**
	 * The ID of the Stripe account this client will be used for.
	 *
	 * @var string
	 */
	private $account_id;

	/**
	 * An HTTP client implementation used to send HTTP requests.
	 *
	 * @var WC_Payments_Http
	 */
	private $http_client;

	/**
	 * WC_Payments_API_Client constructor.
	 *
	 * @param string           $user_agent  - User agent string to report in requests.
	 * @param WC_Payments_Http $http_client - Used to send HTTP requests.
	 */
	public function __construct( $user_agent, $http_client ) {
		$this->user_agent  = $user_agent;
		$this->http_client = $http_client;
	}

	/**
	 * Set the account ID to use for requests to the API.
	 *
	 * @param string $account_id - The ID of the Stripe account this client will be used for.
	 */
	public function set_account_id( $account_id ) {
		$this->account_id = $account_id;
	}

	/**
	 * Create a charge
	 *
	 * @param int    $amount    - Amount to charge.
	 * @param string $source_id - ID of the source to associate with charge.
	 *
	 * @return WC_Payments_API_Charge
	 * @throws Exception - Exception thrown on payment failure.
	 */
	public function create_charge( $amount, $source_id ) {

		$request           = array();
		$request['amount'] = $amount;
		$request['source'] = $source_id;

		$response_array = $this->request( $request, self::CHARGES_API, self::POST );

		return $this->deserialize_charge_object_from_array( $response_array );
	}

	/**
	 * Create an intention, and automatically confirm it.
	 *
	 * @param int    $amount            - Amount to charge.
	 * @param string $currency_code     - Currency to charge in.
	 * @param string $payment_method_id - ID of payment method to process charge with.
	 * @param bool   $manual_capture    - Whether to capture funds via manual action.
	 *
	 * @return WC_Payments_API_Intention
	 * @throws Exception - Exception thrown on intention creation failure.
	 */
	public function create_and_confirm_intention( $amount, $currency_code, $payment_method_id, $manual_capture = false ) {
		// TODO: There's scope to have amount and currency bundled up into an object.
		$request                   = array();
		$request['amount']         = $amount;
		$request['currency']       = $currency_code;
		$request['confirm']        = 'true';
		$request['payment_method'] = $payment_method_id;
		$request['capture_method'] = $manual_capture ? 'manual' : 'automatic';

		$response_array = $this->request( $request, self::INTENTIONS_API, self::POST );

		return $this->deserialize_intention_object_from_array( $response_array );
	}

	/**
	 * Confirm an intention
	 *
	 * @param WC_Payments_API_Intention $intent            - The intention to confirm.
	 * @param string                    $payment_method_id - ID of payment method to process charge with.
	 *
	 * @return WC_Payments_API_Intention
	 * @throws Exception - Exception thrown on intention confirmation failure.
	 */
	public function confirm_intention( WC_Payments_API_Intention $intent, $payment_method_id ) {
		$request                   = array();
		$request['payment_method'] = $payment_method_id;

		$response_array = $this->request(
			$request,
			self::INTENTIONS_API . '/' . $intent->get_id() . '/confirm',
			self::POST
		);

		return $this->deserialize_intention_object_from_array( $response_array );
	}

	/**
	 * Refund a charge
	 *
	 * @param string $charge_id - The charge to refund.
	 * @param int    $amount    - Amount to charge.
	 *
	 * @return array
	 * @throws Exception - Exception thrown on refund creation failure.
	 */
	public function refund_charge( $charge_id, $amount = null ) {
		$request           = array();
		$request['charge'] = $charge_id;
		$request['amount'] = $amount;

		return $this->request( $request, self::REFUNDS_API, self::POST );
	}

	/**
	 * Capture an intention
	 *
	 * @param string $intention_id - The ID of the intention to capture.
	 * @param int    $amount       - Amount to capture.
	 *
	 * @return WC_Payments_API_Intention
	 * @throws Exception - Exception thrown on intention capture failure.
	 */
	public function capture_intention( $intention_id, $amount ) {
		$request                      = array();
		$request['amount_to_capture'] = $amount;

		$response_array = $this->request(
			$request,
			self::INTENTIONS_API . '/' . $intention_id . '/capture',
			self::POST
		);

		return $this->deserialize_intention_object_from_array( $response_array );
	}

	/**
	 * Cancel an intention
	 *
	 * @param string $intention_id - The ID of the intention to cancel.
	 *
	 * @return WC_Payments_API_Intention
	 * @throws Exception - Exception thrown on intention cancellation failure.
	 */
	public function cancel_intention( $intention_id ) {
		$response_array = $this->request(
			array(),
			self::INTENTIONS_API . '/' . $intention_id . '/cancel',
			self::POST
		);

		return $this->deserialize_intention_object_from_array( $response_array );
	}

	/**
	 * Retrive an order ID from the DB using a corresponding Stripe charge ID.
	 *
	 * @param string $charge_id Charge ID corresponding to an order ID.
	 *
	 * @return null|string
	 */
	private function order_id_from_charge_id( $charge_id ) {
		global $wpdb;

		// The order ID is saved to DB in `WC_Payment_Gateway_WCPay::process_payment()`.
		$order_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = '_charge_id'",
				$charge_id
			)
		);
		return $order_id;
	}

	/**
	 * Retrieve an order from the DB using a corresponding Stripe charge ID.
	 *
	 * @param string $charge_id Charge ID corresponding to an order ID.
	 *
	 * @return boolean|WC_Order|WC_Order_Refund
	 */
	private function order_from_charge_id( $charge_id ) {
		$order_id = $this->order_id_from_charge_id( $charge_id );

		if ( $order_id ) {
			return wc_get_order( $order_id );
		}
		return false;
	}

	/**
	 * List transactions
	 *
	 * @return array
	 * @throws Exception - Exception thrown on request failure.
	 */
	public function list_transactions() {
		$transactions = $this->request( array(), self::TRANSACTIONS_API, self::GET );

		// Add order information to each transaction available.
		// TODO: Throw exception when `$transactions` or `$transaction` don't have the fields expected?
		if ( isset( $transactions['data'] ) ) {
			foreach ( $transactions['data'] as &$transaction ) {
				$transaction = $this->add_order_info_to_transaction( $transaction );
			}
		}

		return $transactions;
	}

	/**
	 * Fetch a single transaction with provided id.
	 *
	 * @param string $transaction_id id of requested transaction.
	 * @return array transaction object.
	 */
	public function get_transaction( $transaction_id ) {
		$transaction = $this->request( array(), self::TRANSACTIONS_API . '/' . $transaction_id, self::GET );

		if ( is_wp_error( $transaction ) ) {
			return $transaction;
		}

		return $this->add_order_info_to_transaction( $transaction );
	}

	/**
	 * Get current account data
	 *
	 * @return array An array describing an account object.
	 */
	public function get_account_data() {
		$account = get_transient( self::STRIPE_ACCOUNT_TRANSIENT );

		if ( false === $account ) {
			$account = $this->request( array(), self::ACCOUNTS_API . '/' . $this->account_id, self::GET );

			if ( ! empty( $account ) && ! is_wp_error( $account ) ) {
				set_transient( self::STRIPE_ACCOUNT_TRANSIENT, $account, 2 * HOUR_IN_SECONDS );
			}
		}

		return $account;
	}

	/**
	 * Get data needed to initialize the OAuth flow
	 *
	 * @param string $return_url    - URL to redirect to at the end of the flow.
	 * @param array  $business_data - data to prefill the form.
	 *
	 * @return array An array containing the url and state fields
	 */
	public function get_oauth_data( $return_url, $business_data = array() ) {
		// Clear account transient when generating Stripe's oauth data.
		delete_transient( self::STRIPE_ACCOUNT_TRANSIENT );

		return $this->request(
			array(
				'return_url'    => $return_url,
				'business_data' => $business_data,
			),
			self::OAUTH_API . '/init',
			self::POST
		);
	}

	/**
	 * Get one-time dashboard login url
	 *
	 * @param string $redirect_url - URL to navigate back to from the dashboard.
	 *
	 * @return array An array containing the url field
	 */
	public function get_login_data( $redirect_url ) {
		// Clear account transient when generating Stripe dashboard's login link.
		delete_transient( self::STRIPE_ACCOUNT_TRANSIENT );

		return $this->request(
			array( 'redirect_url' => $redirect_url ),
			self::ACCOUNTS_API . '/' . $this->account_id . '/login_links',
			self::POST
		);
	}

	/**
	 * Send the request to the WooCommerce Payment API
	 *
	 * @param array  $request          - Details of the request to make.
	 * @param string $api              - The API endpoint to call.
	 * @param string $method           - The HTTP method to make the request with.
	 * @param bool   $is_site_specific - If true, the site ID will be included in the request url.
	 *
	 * @return array
	 * @throws Exception - If the account ID hasn't been set.
	 */
	private function request( $request, $api, $method, $is_site_specific = true ) {
		// Add account ID to the request.
		if ( ! isset( $this->account_id ) ) {
			throw new Exception( __( 'Account ID must be set', 'woocommerce-payments' ) );
		}
		$request['account_id'] = $this->account_id;

		// Build the URL we want to send the URL to.
		$url = self::ENDPOINT_BASE;
		if ( $is_site_specific ) {
			$url .= '/' . self::ENDPOINT_SITE_FRAGMENT;
		}
		$url .= '/' . self::ENDPOINT_REST_BASE . '/' . $api;

		$body = null;

		if ( self::GET === $method ) {
			$url .= '?' . http_build_query( $request );
		} else {
			// Encode the request body as JSON.
			$body = wp_json_encode( $request );
			if ( ! $body ) {
				throw new Exception(
					__( 'Unable to encode body for request to WooCommerce Payments API.', 'woocommerce-payments' )
				);
			}
		}

		// Create standard headers.
		$headers                 = array();
		$headers['Content-Type'] = 'application/json; charset=utf-8';
		$headers['User-Agent']   = $this->user_agent;

		$response = $this->http_client->remote_request(
			array(
				'url'     => $url,
				'method'  => $method,
				'headers' => apply_filters( 'wcpay_api_request_headers', $headers ),
			),
			$body,
			$is_site_specific
		);

		// Extract the response body and decode it from JSON into an array.
		$response_body_json = wp_remote_retrieve_body( $response );

		$response_body = json_decode( $response_body_json, true );
		if ( null === $response_body ) {
			throw new Exception(
				__( 'Unable to decode response from WooCommerce Payments API', 'woocommerce-payments' )
			);
		}

		// Check the response code and handle any errors.
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 500 <= $response_code ) {
			throw new Exception( __( 'Server error. Please try again.', 'woocommerce-payments' ) );
		} elseif ( 400 <= $response_code ) {
			return new WP_Error( $response_body['code'], $response_body['message'] );
		}

		return $response_body;
	}

	/**
	 * Returns a transaction with order information when it exists.
	 *
	 * @param array $transaction transaction.
	 * @return array new transaction object with order information.
	 */
	private function add_order_info_to_transaction( $transaction ) {
		$order = $this->order_from_charge_id( $this->get_charge_id_from_transaction( $transaction ) );

		// Add order information to the `$transaction`.
		// If the order couldn't be retrieved, return an empty order.
		$transaction['order'] = null;
		if ( $order ) {
			$transaction['order'] = array(
				'number' => $order->get_order_number(),
				'url'    => $order->get_edit_order_url(),
			);
		}

		return $transaction;
	}

	/**
	 * Gets charge id for a given transaction.
	 *
	 * @param array $transaction transaction.
	 */
	private function get_charge_id_from_transaction( $transaction ) {
		if ( 'refund' === $transaction['type'] ) {
			return $transaction['source']['charge']['id'];
		}
		return $transaction['source']['id'];
	}

	/**
	 * De-serialize a charge array into a charge object
	 *
	 * @param array $charge_array - The charge array to de-serialize.
	 *
	 * @return WC_Payments_API_Charge
	 * @throws Exception - Unable to deserialize charge array.
	 */
	private function deserialize_charge_object_from_array( array $charge_array ) {
		// TODO: Throw an exception if the response array doesn't contain mandatory properties.
		$created = new DateTime();
		$created->setTimestamp( $charge_array['created'] );

		$charge = new WC_Payments_API_Charge(
			$charge_array['id'],
			$charge_array['amount'],
			$created
		);

		if ( isset( $charge_array['captured'] ) ) {
			$charge->set_captured( $charge_array['captured'] );
		}

		return $charge;
	}

	/**
	 * De-serialize an intention array into a intention object
	 *
	 * @param array $intention_array - The intention array to de-serialize.
	 *
	 * @return WC_Payments_API_Intention
	 * @throws Exception - Unable to deserialize intention array.
	 */
	private function deserialize_intention_object_from_array( array $intention_array ) {
		// TODO: Throw an exception if the response array doesn't contain mandatory properties.
		$created = new DateTime();
		$created->setTimestamp( $intention_array['created'] );

		$charge = 0 < $intention_array['charges']['total_count'] ? end( $intention_array['charges']['data'] ) : null;

		$intent = new WC_Payments_API_Intention(
			$intention_array['id'],
			$intention_array['amount'],
			$created,
			$intention_array['status'],
			$charge ? $charge['id'] : null
		);

		return $intent;
	}
}
