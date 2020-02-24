<?php
/**
 * WC_Payments_API_Client class
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

use WCPay\Logger;

/**
 * Communicates with WooCommerce Payments API.
 */
class WC_Payments_API_Client {

	const ENDPOINT_BASE          = 'https://public-api.wordpress.com/wpcom/v2';
	const ENDPOINT_SITE_FRAGMENT = 'sites/%s';
	const ENDPOINT_REST_BASE     = 'wcpay';

	const POST = 'POST';
	const GET  = 'GET';

	const ACCOUNTS_API     = 'accounts';
	const CHARGES_API      = 'charges';
	const INTENTIONS_API   = 'intentions';
	const REFUNDS_API      = 'refunds';
	const DEPOSITS_API     = 'deposits';
	const TRANSACTIONS_API = 'transactions';
	const DISPUTES_API     = 'disputes';
	const FILES_API        = 'files';
	const OAUTH_API        = 'oauth';

	/**
	 * User agent string to report in requests.
	 *
	 * @var string
	 */
	private $user_agent;

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
	 * Create a charge
	 *
	 * @param int    $amount    - Amount to charge.
	 * @param string $source_id - ID of the source to associate with charge.
	 *
	 * @return WC_Payments_API_Charge
	 * @throws WC_Payments_API_Exception - Exception thrown on payment failure.
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
	 * @throws WC_Payments_API_Exception - Exception thrown on intention creation failure.
	 */
	public function create_and_confirm_intention(
		$amount,
		$currency_code,
		$payment_method_id,
		$manual_capture = false
	) {
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
	 * @throws WC_Payments_API_Exception - Exception thrown on intention confirmation failure.
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
	 * @throws WC_Payments_API_Exception - Exception thrown on refund creation failure.
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
	 * @throws WC_Payments_API_Exception - Exception thrown on intention capture failure.
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
	 * @throws WC_Payments_API_Exception - Exception thrown on intention cancellation failure.
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
	 * List deposits
	 *
	 * @param int $page      The requested page.
	 * @param int $page_size The size of the requested page.
	 *
	 * @return array
	 * @throws WC_Payments_API_Exception - Exception thrown on request failure.
	 */
	public function list_deposits( $page = 0, $page_size = 25 ) {
		$query = [
			'page'     => $page,
			'pagesize' => $page_size,
		];

		return $this->request( $query, self::DEPOSITS_API, self::GET );
	}

	/**
	 * Fetch a single deposit with provided id.
	 *
	 * @param string $deposit_id id of requested deposit.
	 * @return array deposit object.
	 */
	public function get_deposit( $deposit_id ) {
		return $this->request( array(), self::DEPOSITS_API . '/' . $deposit_id, self::GET );
	}

	/**
	 * Return summary for transactions.
	 *
	 * @param string $deposit_id The deposit to filter on.
	 *
	 * @return array     The transactions summary.
	 * @throws WC_Payments_API_Exception Exception thrown on request failure.
	 */
	public function get_transactions_summary( $deposit_id = null ) {
		$query = [
			'deposit_id' => $deposit_id,
		];

		return $this->request( $query, self::TRANSACTIONS_API . '/summary', self::GET );
	}

	/**
	 * List transactions
	 *
	 * @param int    $page       The requested page.
	 * @param int    $page_size  The size of the requested page.
	 * @param string $deposit_id The deposit to filter on.
	 *
	 * @return array
	 * @throws WC_Payments_API_Exception - Exception thrown on request failure.
	 */
	public function list_transactions( $page = 0, $page_size = 25, $deposit_id = null ) {
		$query = [
			'page'       => $page,
			'pagesize'   => $page_size,
			'deposit_id' => $deposit_id,
		];

		$transactions = $this->request( $query, self::TRANSACTIONS_API, self::GET );

		// Add order information to each transaction available.
		// TODO: Throw exception when `$transactions` or `$transaction` don't have the fields expected?
		if ( isset( $transactions['data'] ) ) {
			foreach ( $transactions['data'] as &$transaction ) {
				$transaction = $this->add_order_info_to_object( $transaction['charge_id'], $transaction );
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

		return $this->add_order_info_to_object( $transaction['charge_id'], $transaction );
	}

	/**
	 * Fetch a single charge with provided id.
	 *
	 * @param string $charge_id id of requested charge.
	 * @return array charge object.
	 */
	public function get_charge( $charge_id ) {
		$charge = $this->request( array(), self::CHARGES_API . '/' . $charge_id, self::GET );

		if ( is_wp_error( $charge ) ) {
			return $charge;
		}

		return $this->add_order_info_to_object( $charge['id'], $charge );
	}

	/**
	 * List disputes
	 *
	 * @return array
	 * @throws WC_Payments_API_Exception - Exception thrown on request failure.
	 */
	public function list_disputes() {
		$disputes = $this->request( array(), self::DISPUTES_API, self::GET );

		// Add WooCommerce order information to each dispute.
		if ( isset( $disputes['data'] ) ) {
			foreach ( $disputes['data'] as &$dispute ) {
				try {
					// Wrap with try/catch to avoid failing whole request because of a single dispute.
					$dispute = $this->add_order_info_to_object( $dispute['charge']['id'], $dispute );
				} catch ( Exception $e ) {
					// TODO: Log the error once Logger PR (#326) is merged.
					continue;
				}
			}
		}

		return $disputes;
	}

	/**
	 * Fetch a single dispute with provided id.
	 *
	 * @param string $dispute_id id of requested dispute.
	 * @return array dispute object.
	 */
	public function get_dispute( $dispute_id ) {
		$dispute = $this->request( array(), self::DISPUTES_API . '/' . $dispute_id, self::GET );

		if ( is_wp_error( $dispute ) ) {
			return $dispute;
		}

		$charge_id = is_array( $dispute['charge'] ) ? $dispute['charge']['id'] : $dispute['charge'];
		return $this->add_order_info_to_object( $charge_id, $dispute );
	}

	/**
	 * Update dispute with provided id.
	 *
	 * @param string $dispute_id id of dispute to update.
	 * @param array  $evidence   evidence to upload.
	 * @param bool   $submit     whether to submit (rather than stage) evidence.
	 * @param array  $metadata   metadata associated with this dispute.
	 *
	 * @return array dispute object.
	 */
	public function update_dispute( $dispute_id, $evidence, $submit, $metadata ) {
		$request = array(
			'evidence' => $evidence,
			'submit'   => $submit,
			'metadata' => $metadata,
		);

		$dispute = $this->request( $request, self::DISPUTES_API . '/' . $dispute_id, self::POST );

		if ( is_wp_error( $dispute ) ) {
			return $dispute;
		}

		$charge_id = is_array( $dispute['charge'] ) ? $dispute['charge']['id'] : $dispute['charge'];
		return $this->add_order_info_to_object( $charge_id, $dispute );
	}

	/**
	 * Close dispute with provided id.
	 *
	 * @param string $dispute_id id of dispute to close.
	 * @return array dispute object.
	 */
	public function close_dispute( $dispute_id ) {
		return $this->request( array(), self::DISPUTES_API . '/' . $dispute_id . '/close', self::POST );
	}

	/**
	 * Upload evidence and return file object.
	 *
	 * @param string $request request object received.
	 *
	 * @return array file object.
	 * @throws WC_Payments_API_Exception - If request throws.
	 */
	public function upload_evidence( $request ) {
		$purpose     = $request->get_param( 'purpose' );
		$file_params = $request->get_file_params();
		$file_name   = $file_params['file']['name'];
		$file_type   = $file_params['file']['type'];

		// Sometimes $file_params is empty array for large files (8+ MB).
		$file_error = empty( $file_params ) || $file_params['file']['error'];

		if ( $file_error ) {
			// TODO - Add better error message by specifiying which limit is reached (host or Stripe).
			throw new WC_Payments_API_Exception(
				__( 'Max file size exceeded.', 'woocommerce-payments' ),
				'wcpay_evidence_file_max_size',
				400
			);
		}

		$body = [
			// We disable php linting here because otherwise it will show a warning on improper
			// use of `file_get_contents()` and say you should "use `wp_remote_get()` for
			// remote URLs instead", which is unrelated to our use here.
			// phpcs:disable
			'file'      => base64_encode( file_get_contents( $file_params['file']['tmp_name'] ) ),
			// phpcs:enable
			'file_name' => $file_name,
			'file_type' => $file_type,
			'purpose'   => $purpose,
		];

		$response = $this->request( $body, self::FILES_API, self::POST );
		return $response;
	}

	/**
	 * Get current account data
	 *
	 * @return array An array describing an account object.
	 */
	public function get_account_data() {
		return $this->request(
			array(
				'test_mode' => WC_Payments::get_gateway()->is_in_dev_mode(), // only send a test mode request if in dev mode.
			),
			self::ACCOUNTS_API,
			self::GET
		);
	}

	/**
	 * Get data needed to initialize the OAuth flow
	 *
	 * @param string $return_url    - URL to redirect to at the end of the flow.
	 * @param array  $business_data - data to prefill the form.
	 *
	 * @return array An array containing the url and state fields.
	 */
	public function get_oauth_data( $return_url, $business_data = array() ) {
		$request_args = apply_filters(
			'wc_payments_get_oauth_data_args',
			[
				'return_url'          => $return_url,
				'business_data'       => $business_data,
				'create_live_account' => ! WC_Payments::get_gateway()->is_in_dev_mode(),
			]
		);

		return $this->request( $request_args, self::OAUTH_API . '/init', self::POST );
	}

	/**
	 * Get one-time dashboard login url
	 *
	 * @param string $redirect_url - URL to navigate back to from the dashboard.
	 *
	 * @return array An array containing the url field
	 */
	public function get_login_data( $redirect_url ) {
		return $this->request(
			array(
				'redirect_url' => $redirect_url,
				'test_mode'    => WC_Payments::get_gateway()->is_in_dev_mode(), // only send a test mode request if in dev mode.
			),
			self::ACCOUNTS_API . '/login_links',
			self::POST
		);
	}

	/**
	 * Send the request to the WooCommerce Payment API
	 *
	 * @param array  $params           - Request parameters to send as either JSON or GET string. Defaults to test_mode=1 if either in dev or test mode, 0 otherwise.
	 * @param string $api              - The API endpoint to call.
	 * @param string $method           - The HTTP method to make the request with.
	 * @param bool   $is_site_specific - If true, the site ID will be included in the request url.
	 *
	 * @return array
	 * @throws WC_Payments_API_Exception - If the account ID hasn't been set.
	 */
	private function request( $params, $api, $method, $is_site_specific = true ) {
		// Apply the default params that can be overriden by the calling method.
		$params = wp_parse_args(
			$params,
			array(
				'test_mode' => WC_Payments::get_gateway()->is_in_test_mode(),
			)
		);

		// Build the URL we want to send the URL to.
		$url = self::ENDPOINT_BASE;
		if ( $is_site_specific ) {
			$url .= '/' . self::ENDPOINT_SITE_FRAGMENT;
		}
		$url .= '/' . self::ENDPOINT_REST_BASE . '/' . $api;

		$body = null;

		if ( self::GET === $method ) {
			$url .= '?' . http_build_query( $params );
		} else {
			// Encode the request body as JSON.
			$body = wp_json_encode( $params );
			if ( ! $body ) {
				throw new WC_Payments_API_Exception(
					__( 'Unable to encode body for request to WooCommerce Payments API.', 'woocommerce-payments' ),
					'wcpay_client_unable_to_encode_json',
					0
				);
			}
		}

		// Create standard headers.
		$headers                 = array();
		$headers['Content-Type'] = 'application/json; charset=utf-8';
		$headers['User-Agent']   = $this->user_agent;

		Logger::log( "REQUEST $method $url" );
		$response = $this->http_client->remote_request(
			array(
				'url'     => $url,
				'method'  => $method,
				'headers' => apply_filters( 'wcpay_api_request_headers', $headers ),
			),
			$body,
			$is_site_specific
		);

		$response_body = $this->extract_response_body( $response );
		Logger::log( 'RESPONSE ' . var_export( $response_body, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export

		return $response_body;
	}

	/**
	 * From a given response extract the body. Invalid HTTP codes will result in an error.
	 *
	 * @param array $response That was given to us by http_client remote_request.
	 *
	 * @return array $response_body
	 *
	 * @throws WC_Payments_API_Exception Standard exception in case we can't extract the body.
	 */
	protected function extract_response_body( $response ) {
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( ! $response_code ) {
			$response_code = 0;
		}

		$response_body_json = wp_remote_retrieve_body( $response );
		$response_body      = json_decode( $response_body_json, true );
		if ( null === $response_body ) {
			$message = __( 'Unable to decode response from WooCommerce Payments API', 'woocommerce-payments' );
			Logger::error( $message );
			throw new WC_Payments_API_Exception(
				$message,
				'wcpay_unparseable_or_null_body',
				$response_code
			);
		}

		// Check response error codes.
		if ( 500 <= $response_code || 400 <= $response_code ) {
			if ( isset( $response_body['error'] ) ) {
				$error_code    = $response_body['error']['code'];
				$error_message = $response_body['error']['message'];
			} elseif ( isset( $response_body['code'] ) ) {
				$error_code    = $response_body['code'];
				$error_message = $response_body['message'];
			} else {
				$error_code    = 'wcpay_client_error_code_missing';
				$error_message = __( 'Server error. Please try again.', 'woocommerce-payments' );
			}

			$message = sprintf(
				// translators: This is an error from a 400 API response.
				_x( 'Error: %1$s %2$s', '400 Error type message to throw as Exception', 'woocommerce-payments' ),
				$error_code,
				$error_message
			);

			Logger::error( $message );
			throw new WC_Payments_API_Exception( $message, $error_code, $response_code );
		}

		// Make sure empty metadata serialized on the client as an empty object {} rather than array [].
		if ( isset( $response_body['metadata'] ) && empty( $response_body['metadata'] ) ) {
			$response_body['metadata'] = new stdClass();
		}

		return $response_body;
	}

	/**
	 * Returns a transaction with order information when it exists.
	 *
	 * @param  string $charge_id related charge id.
	 * @param  array  $object object to add order information.
	 * @return array  new object with order information.
	 */
	private function add_order_info_to_object( $charge_id, $object ) {
		$order = $this->order_from_charge_id( $charge_id );

		// Add order information to the `$transaction`.
		// If the order couldn't be retrieved, return an empty order.
		$object['order'] = null;
		if ( $order ) {
			$object['order'] = array(
				'number' => $order->get_order_number(),
				'url'    => $order->get_edit_order_url(),
			);
		}

		return $object;
	}

	/**
	 * De-serialize a charge array into a charge object
	 *
	 * @param array $charge_array - The charge array to de-serialize.
	 *
	 * @return WC_Payments_API_Charge
	 * @throws WC_Payments_API_Exception - Unable to deserialize charge array.
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
	 * @throws WC_Payments_API_Exception - Unable to deserialize intention array.
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
