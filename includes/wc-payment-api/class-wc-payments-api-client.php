<?php
/**
 * WC_Payments_API_Client class
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

use WCPay\Exceptions\API_Exception;
use WCPay\Exceptions\Amount_Too_Small_Exception;
use WCPay\Logger;
use Automattic\WooCommerce\Admin\API\Reports\Customers\DataStore;

/**
 * Communicates with WooCommerce Payments API.
 */
class WC_Payments_API_Client {

	const ENDPOINT_BASE          = 'https://public-api.wordpress.com/wpcom/v2';
	const ENDPOINT_SITE_FRAGMENT = 'sites/%s';
	const ENDPOINT_REST_BASE     = 'wcpay';

	const POST   = 'POST';
	const GET    = 'GET';
	const DELETE = 'DELETE';

	const API_TIMEOUT_SECONDS = 70;

	const ACCOUNTS_API           = 'accounts';
	const CAPABILITIES_API       = 'accounts/capabilities';
	const APPLE_PAY_API          = 'apple_pay';
	const CHARGES_API            = 'charges';
	const CONN_TOKENS_API        = 'terminal/connection_tokens';
	const TERMINAL_LOCATIONS_API = 'terminal/locations';
	const CUSTOMERS_API          = 'customers';
	const CURRENCY_API           = 'currency';
	const INTENTIONS_API         = 'intentions';
	const REFUNDS_API            = 'refunds';
	const DEPOSITS_API           = 'deposits';
	const TRANSACTIONS_API       = 'transactions';
	const DISPUTES_API           = 'disputes';
	const FILES_API              = 'files';
	const ONBOARDING_API         = 'onboarding';
	const TIMELINE_API           = 'timeline';
	const PAYMENT_METHODS_API    = 'payment_methods';
	const SETUP_INTENTS_API      = 'setup_intents';
	const TRACKING_API           = 'tracking';
	const PRODUCTS_API           = 'products';
	const PRICES_API             = 'products/prices';
	const INVOICES_API           = 'invoices';
	const SUBSCRIPTIONS_API      = 'subscriptions';
	const SUBSCRIPTION_ITEMS_API = 'subscriptions/items';
	const READERS_CHARGE_SUMMARY = 'reader-charges/summary';
	const TERMINAL_READERS_API   = 'terminal/readers';

	/**
	 * Common keys in API requests/responses that we might want to redact.
	 */
	const API_KEYS_TO_REDACT = [
		'client_secret',
		'email',
		'name',
		'phone',
		'line1',
		'line2',
		'postal_code',
		'state',
		'city',
		'country',
		'customer_name',
		'customer_email',
	];

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
	 * DB access wrapper.
	 *
	 * @var WC_Payments_DB
	 */
	private $wcpay_db;

	/**
	 * WC_Payments_API_Client constructor.
	 *
	 * @param string           $user_agent  - User agent string to report in requests.
	 * @param WC_Payments_Http $http_client - Used to send HTTP requests.
	 * @param WC_Payments_DB   $wcpay_db    - DB access wrapper.
	 */
	public function __construct( $user_agent, $http_client, $wcpay_db ) {
		$this->user_agent  = $user_agent;
		$this->http_client = $http_client;
		$this->wcpay_db    = $wcpay_db;
	}

	/**
	 * Whether the site can communicate with the WCPay server (i.e. Jetpack connection has been established).
	 *
	 * @return bool
	 */
	public function is_server_connected() {
		return $this->http_client->is_connected();
	}

	/**
	 * Checks if the site has an admin who is also a connection owner.
	 *
	 * @return bool True if Jetpack connection has an owner.
	 */
	public function has_server_connection_owner() {
		return $this->http_client->has_connection_owner();
	}

	/**
	 * Gets the current WP.com blog ID, if the Jetpack connection has been set up.
	 *
	 * @return integer|NULL Current WPCOM blog ID, or NULL if not connected yet.
	 */
	public function get_blog_id() {
		return $this->is_server_connected() ? $this->http_client->get_blog_id() : null;
	}

	/**
	 * Starts the Jetpack connection process. Note that running this function will immediately redirect
	 * to the Jetpack flow, so any PHP code after it will never be executed.
	 *
	 * @param string $redirect - URL to redirect to after the connection process is over.
	 *
	 * @throws API_Exception - Exception thrown on failure.
	 */
	public function start_server_connection( $redirect ) {
		$this->http_client->start_connection( $redirect );
	}

	/**
	 * Create a charge
	 *
	 * @param int    $amount    - Amount to charge.
	 * @param string $source_id - ID of the source to associate with charge.
	 *
	 * @return WC_Payments_API_Charge
	 * @throws API_Exception - Exception thrown on payment failure.
	 */
	public function create_charge( $amount, $source_id ) {

		$request           = [];
		$request['amount'] = $amount;
		$request['source'] = $source_id;

		$response_array = $this->request( $request, self::CHARGES_API, self::POST );

		return $this->deserialize_charge_object_from_array( $response_array );
	}

	/**
	 * Create an intention, and automatically confirm it.
	 *
	 * @param int    $amount                 - Amount to charge.
	 * @param string $currency_code          - Currency to charge in.
	 * @param string $payment_method_id      - ID of payment method to process charge with.
	 * @param string $customer_id            - ID of the customer making the payment.
	 * @param bool   $manual_capture         - Whether to capture funds via manual action.
	 * @param bool   $save_payment_method    - Whether to save payment method for future purchases.
	 * @param array  $metadata               - Meta data values to be sent along with payment intent creation.
	 * @param array  $level3                 - Level 3 data.
	 * @param bool   $off_session            - Whether the payment is off-session (merchant-initiated), or on-session (customer-initiated).
	 * @param array  $additional_parameters  - An array of any additional request parameters, particularly for additional payment methods.
	 * @param array  $payment_methods        - An array of payment methods that might be used for the payment.
	 *
	 * @return WC_Payments_API_Intention
	 * @throws API_Exception - Exception thrown on intention creation failure.
	 */
	public function create_and_confirm_intention(
		$amount,
		$currency_code,
		$payment_method_id,
		$customer_id,
		$manual_capture = false,
		$save_payment_method = false,
		$metadata = [],
		$level3 = [],
		$off_session = false,
		$additional_parameters = [],
		$payment_methods = null
	) {
		// TODO: There's scope to have amount and currency bundled up into an object.
		$request                   = [];
		$request['amount']         = $amount;
		$request['currency']       = $currency_code;
		$request['confirm']        = 'true';
		$request['payment_method'] = $payment_method_id;
		$request['customer']       = $customer_id;
		$request['capture_method'] = $manual_capture ? 'manual' : 'automatic';
		$request['metadata']       = $metadata;
		$request['level3']         = $level3;
		$request['description']    = $this->get_intent_description( $metadata['order_id'] ?? 0 );

		if ( ! empty( $payment_methods ) ) {
			$request['payment_method_types'] = $payment_methods;
		}

		$request = array_merge( $request, $additional_parameters );

		if ( $off_session ) {
			$request['off_session'] = 'true';
		}

		if ( $save_payment_method ) {
			$request['setup_future_usage'] = 'off_session';
		}

		$response_array = $this->request_with_level3_data( $request, self::INTENTIONS_API, self::POST );

		return $this->deserialize_intention_object_from_array( $response_array );
	}

	/**
	 * Create an intention, without confirming it.
	 *
	 * @param int    $amount          - Amount to charge.
	 * @param string $currency_code   - Currency to charge in.
	 * @param array  $payment_methods - Payment methods to include.
	 * @param int    $order_id        - The order ID.
	 * @param string $capture_method  - optional capture method (either `automatic` or `manual`).
	 *
	 * @return WC_Payments_API_Intention
	 * @throws API_Exception - Exception thrown on intention creation failure.
	 */
	public function create_intention(
		$amount,
		$currency_code,
		$payment_methods,
		$order_id,
		$capture_method = 'automatic'
	) {
		$request                         = [];
		$request['amount']               = $amount;
		$request['currency']             = $currency_code;
		$request['description']          = $this->get_intent_description( $order_id );
		$request['payment_method_types'] = $payment_methods;
		$request['capture_method']       = $capture_method;

		$response_array = $this->request( $request, self::INTENTIONS_API, self::POST );

		return $this->deserialize_intention_object_from_array( $response_array );
	}

	/**
	 * Updates an intention, without confirming it.
	 *
	 * @param string  $intention_id              - The ID of the intention to update.
	 * @param int     $amount                    - Amount to charge.
	 * @param string  $currency_code             - Currency to charge in.
	 * @param bool    $save_payment_method       - Whether to setup payment intent for future usage.
	 * @param string  $customer_id               - Stripe customer to associate payment intent with.
	 * @param array   $metadata                  - Meta data values to be sent along with payment intent creation.
	 * @param array   $level3                    - Level 3 data.
	 * @param string  $selected_upe_payment_type - The name of the selected UPE payment type or empty string.
	 * @param ?string $payment_country           - The payment two-letter iso country code or null.
	 *
	 * @return WC_Payments_API_Intention
	 * @throws API_Exception - Exception thrown on intention creation failure.
	 */
	public function update_intention(
		$intention_id,
		$amount,
		$currency_code,
		$save_payment_method = false,
		$customer_id = '',
		$metadata = [],
		$level3 = [],
		$selected_upe_payment_type = '',
		$payment_country = null
	) {
		$request = [
			'amount'      => $amount,
			'currency'    => $currency_code,
			'metadata'    => $metadata,
			'level3'      => $level3,
			'description' => $this->get_intent_description( $metadata['order_id'] ?? 0 ),
		];

		if ( '' !== $selected_upe_payment_type ) {
			// Only update the payment_method_types if we have a reference to the payment type the customer selected.
			$request['payment_method_types'] = [ $selected_upe_payment_type ];
		}
		if ( $payment_country && ! WC_Payments::get_gateway()->is_in_dev_mode() ) {
			// Do not update on dev mode, Stripe tests cards don't return the appropriate country.
			$request['payment_country'] = $payment_country;
		}
		if ( $customer_id ) {
			$request['customer'] = $customer_id;
		}
		if ( $save_payment_method ) {
			$request['setup_future_usage'] = 'off_session';
		}

		$response_array = $this->request_with_level3_data( $request, self::INTENTIONS_API . '/' . $intention_id, self::POST );

		return $this->deserialize_intention_object_from_array( $response_array );
	}

	/**
	 * Updates an intention's metadata.
	 * Unlike `update_intention`, this method allows to update metadata without
	 * requiring amount, currency, and other mandatory params to be present.
	 *
	 * @param string $intention_id - The ID of the intention to update.
	 * @param array  $metadata     - Meta data values to be sent along with payment intent creation.
	 *
	 * @return WC_Payments_API_Intention
	 * @throws API_Exception - Exception thrown on intention creation failure.
	 */
	public function update_intention_metadata( $intention_id, $metadata ) {
		$request = [
			'metadata' => $metadata,
		];

		$response_array = $this->request_with_level3_data( $request, self::INTENTIONS_API . '/' . $intention_id, self::POST );

		return $this->deserialize_intention_object_from_array( $response_array );
	}

	/**
	 * Refund a charge
	 *
	 * @param string $charge_id - The charge to refund.
	 * @param int    $amount    - Amount to charge.
	 *
	 * @return array
	 * @throws API_Exception - Exception thrown on refund creation failure.
	 */
	public function refund_charge( $charge_id, $amount = null ) {
		$request           = [];
		$request['charge'] = $charge_id;
		$request['amount'] = $amount;

		return $this->request( $request, self::REFUNDS_API, self::POST );
	}

	/**
	 * Capture an intention
	 *
	 * @param string $intention_id - The ID of the intention to capture.
	 * @param int    $amount       - Amount to capture.
	 * @param array  $level3       - Level 3 data.
	 *
	 * @return WC_Payments_API_Intention
	 * @throws API_Exception - Exception thrown on intention capture failure.
	 */
	public function capture_intention( $intention_id, $amount, $level3 = [] ) {
		$request                      = [];
		$request['amount_to_capture'] = $amount;
		$request['level3']            = $level3;

		$response_array = $this->request_with_level3_data(
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
	 * @throws API_Exception - Exception thrown on intention cancellation failure.
	 */
	public function cancel_intention( $intention_id ) {
		$response_array = $this->request(
			[],
			self::INTENTIONS_API . '/' . $intention_id . '/cancel',
			self::POST
		);

		return $this->deserialize_intention_object_from_array( $response_array );
	}

	/**
	 * Fetch a single intent with provided id.
	 *
	 * @param string $intent_id intent id.
	 *
	 * @return WC_Payments_API_Intention intention object.
	 */
	public function get_intent( $intent_id ) {
		$intent = $this->request( [], self::INTENTIONS_API . '/' . $intent_id, self::GET );

		return $this->deserialize_intention_object_from_array( $intent );
	}

	/**
	 * Setup an intention, without confirming it.
	 *
	 * @param string $customer_id          - ID of the customer.
	 * @param array  $payment_method_types - Payment methods to include.
	 *
	 * @return array
	 * @throws API_Exception - Exception thrown on intention creation failure.
	 */
	public function create_setup_intention(
		$customer_id,
		$payment_method_types
	) {
		$request = [
			'customer'             => $customer_id,
			'confirm'              => 'false',
			'payment_method_types' => $payment_method_types,
		];

		return $this->request( $request, self::SETUP_INTENTS_API, self::POST );
	}

	/**
	 * Create a setup intent.
	 *
	 * @param string $payment_method_id      - ID of payment method to be saved.
	 * @param string $customer_id            - ID of the customer.
	 *
	 * @return array
	 * @throws API_Exception - Exception thrown on setup intent creation failure.
	 */
	public function create_and_confirm_setup_intent( $payment_method_id, $customer_id ) {
		$request = [
			'payment_method' => $payment_method_id,
			'customer'       => $customer_id,
			'confirm'        => 'true',
		];

		return $this->request( $request, self::SETUP_INTENTS_API, self::POST );
	}

	/**
	 * Fetch a setup intent details.
	 *
	 * @param string $setup_intent_id ID of the setup intent.
	 *
	 * @return array
	 * @throws API_Exception - When fetch of setup intent fails.
	 */
	public function get_setup_intent( $setup_intent_id ) {
		return $this->request( [], self::SETUP_INTENTS_API . '/' . $setup_intent_id, self::GET );
	}

	/**
	 * List deposits
	 *
	 * @param int    $page      The requested page.
	 * @param int    $page_size The size of the requested page.
	 * @param string $sort      The column to be used for sorting.
	 * @param string $direction The sorting direction.
	 * @param array  $filters   The filters to be used in the query.
	 *
	 * @return array
	 * @throws API_Exception - Exception thrown on request failure.
	 */
	public function list_deposits( $page = 0, $page_size = 25, $sort = 'date', $direction = 'desc', array $filters = [] ) {
		$query = array_merge(
			$filters,
			[
				'page'      => $page,
				'pagesize'  => $page_size,
				'sort'      => $sort,
				'direction' => $direction,
			]
		);

		return $this->request( $query, self::DEPOSITS_API, self::GET );
	}

	/**
	 * Get overview of deposits.
	 *
	 * @return array
	 * @throws API_Exception - Exception thrown on request failure.
	 */
	public function get_deposits_overview() {
		return $this->request( [], self::DEPOSITS_API . '/overview', self::GET );
	}

	/**
	 * Get an overview of all deposits (for all currencies).
	 *
	 * @return array
	 * @throws API_Exception - Exception thrown on request failure.
	 */
	public function get_all_deposits_overviews() {
		return $this->request( [], self::DEPOSITS_API . '/overview-all', self::GET );
	}

	/**
	 * Get summary of deposits.
	 *
	 * @param array $filters The filters to be used in the query.
	 *
	 * @return array
	 * @throws API_Exception - Exception thrown on request failure.
	 */
	public function get_deposits_summary( array $filters = [] ) {
		return $this->request( $filters, self::DEPOSITS_API . '/summary', self::GET );
	}

	/**
	 * Fetch a single deposit with provided id.
	 *
	 * @param string $deposit_id id of requested deposit.
	 * @return array deposit object.
	 */
	public function get_deposit( $deposit_id ) {
		return $this->request( [], self::DEPOSITS_API . '/' . $deposit_id, self::GET );
	}

	/**
	 * Trigger a manual deposit.
	 *
	 * @param string $type Type of deposit. Only "instant" is supported for now.
	 * @param string $transaction_ids Comma-separated list of transaction IDs that will be associated with this deposit.
	 * @return array The new deposit object.
	 * @throws API_Exception - Exception thrown on request failure.
	 */
	public function manual_deposit( $type, $transaction_ids ) {
		return $this->request(
			[
				'type'            => $type,
				'transaction_ids' => $transaction_ids,
			],
			self::DEPOSITS_API,
			self::POST
		);
	}

	/**
	 * Return summary for transactions.
	 *
	 * @param array  $filters    The filters to be used in the query.
	 * @param string $deposit_id The deposit to filter on.
	 *
	 * @return array     The transactions summary.
	 * @throws API_Exception Exception thrown on request failure.
	 */
	public function get_transactions_summary( $filters = [], $deposit_id = null ) {
		// Map Order # terms to the actual charge id to be used in the server.
		if ( ! empty( $filters['search'] ) ) {
			$filters['search'] = WC_Payments_Utils::map_search_orders_to_charge_ids( $filters['search'] );
		}

		$query = array_merge(
			$filters,
			[
				'deposit_id' => $deposit_id,
			]
		);

		return $this->request( $query, self::TRANSACTIONS_API . '/summary', self::GET );
	}

	/**
	 * List transactions
	 *
	 * @param int    $page       The requested page.
	 * @param int    $page_size  The size of the requested page.
	 * @param string $sort       The column to be used for sorting.
	 * @param string $direction  The sorting direction.
	 * @param array  $filters    The filters to be used in the query.
	 * @param string $deposit_id The deposit to filter on.
	 *
	 * @return array
	 * @throws API_Exception - Exception thrown on request failure.
	 */
	public function list_transactions( $page = 0, $page_size = 25, $sort = 'date', $direction = 'desc', $filters = [], $deposit_id = null ) {
		// Map Order # terms to the actual charge id to be used in the server.
		if ( ! empty( $filters['search'] ) ) {
			$filters['search'] = WC_Payments_Utils::map_search_orders_to_charge_ids( $filters['search'] );
		}

		$query = array_merge(
			$filters,
			[
				'page'       => $page,
				'pagesize'   => $page_size,
				'sort'       => $sort,
				'direction'  => $direction,
				'deposit_id' => $deposit_id,
			]
		);

		$transactions = $this->request( $query, self::TRANSACTIONS_API, self::GET );

		$charge_ids             = array_column( $transactions['data'], 'charge_id' );
		$orders_with_charge_ids = $this->wcpay_db->orders_with_charge_id_from_charge_ids( $charge_ids );

		// Add order information to each transaction available.
		// TODO: Throw exception when `$transactions` or `$transaction` don't have the fields expected?
		if ( isset( $transactions['data'] ) ) {
			foreach ( $transactions['data'] as &$transaction ) {
				foreach ( $orders_with_charge_ids as $order_with_charge_id ) {
					if ( $order_with_charge_id['charge_id'] === $transaction['charge_id'] ) {
						$transaction['order'] = $this->build_order_info( $order_with_charge_id['order'] );
					}
				}
			}
			// Securing future changes from modifying reference content.
			unset( $transaction );
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
		$transaction = $this->request( [], self::TRANSACTIONS_API . '/' . $transaction_id, self::GET );

		if ( is_wp_error( $transaction ) ) {
			return $transaction;
		}

		return $this->add_order_info_to_object( $transaction['charge_id'], $transaction );
	}

	/**
	 * Fetch transactions search options for provided query.
	 *
	 * @param string $search_term Query to be used to get search options - can be an order ID, or part of a name or email.
	 * @return array|WP_Error Search results.
	 */
	public function get_transactions_search_autocomplete( $search_term ) {
		$order = wc_get_order( $search_term );

		$search_results = $this->request( [ 'search_term' => $search_term ], self::TRANSACTIONS_API . '/search', self::GET );
		$results        = array_map(
			function ( $result ) {
				return [
					'label' => $result['customer_name'] . ' (' . $result['customer_email'] . ')',
				];
			},
			$search_results
		);

		if ( $order ) {
			if ( function_exists( 'wcs_is_subscription' ) && wcs_is_subscription( $order ) ) {
				$prefix = __( 'Subscription #', 'woocommerce-payments' );
			} else {
				$prefix = __( 'Order #', 'woocommerce-payments' );
			}
			array_unshift( $results, [ 'label' => $prefix . $search_term ] );
		}

		return $results;
	}

	/**
	 * Fetch a single charge with provided id.
	 *
	 * @param string $charge_id id of requested charge.
	 * @return array charge object.
	 */
	public function get_charge( $charge_id ) {
		$charge = $this->request( [], self::CHARGES_API . '/' . $charge_id, self::GET );

		if ( is_wp_error( $charge ) ) {
			return $charge;
		}

		return $this->add_order_info_to_object( $charge['id'], $charge );
	}

	/**
	 * List disputes
	 *
	 * @return array
	 * @throws API_Exception - Exception thrown on request failure.
	 */
	public function list_disputes() {
		$query = [
			'limit' => 100,
		];

		$disputes = $this->request( $query, self::DISPUTES_API, self::GET );

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
		$dispute = $this->request( [], self::DISPUTES_API . '/' . $dispute_id, self::GET );

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
		$request = [
			'evidence' => $evidence,
			'submit'   => $submit,
			'metadata' => $metadata,
		];

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
		$dispute = $this->request( [], self::DISPUTES_API . '/' . $dispute_id . '/close', self::POST );

		if ( is_wp_error( $dispute ) ) {
			return $dispute;
		}

		$charge_id = is_array( $dispute['charge'] ) ? $dispute['charge']['id'] : $dispute['charge'];
		return $this->add_order_info_to_object( $charge_id, $dispute );
	}

	/**
	 * Upload evidence and return file object.
	 *
	 * @param WP_REST_Request $request request object received.
	 *
	 * @return array file object.
	 * @throws API_Exception - If request throws.
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
			throw new API_Exception(
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

		try {
			return $this->request( $body, self::FILES_API, self::POST );
		} catch ( API_Exception $e ) {
			throw new API_Exception(
				$e->getMessage(),
				'wcpay_evidence_file_upload_error',
				$e->get_http_code()
			);
		}
	}

	/**
	 * Create a connection token.
	 *
	 * @param WP_REST_Request $request request object received.
	 *
	 * @return array
	 * @throws API_Exception - If request throws.
	 */
	public function create_token( $request ) {
		return $this->request( [], self::CONN_TOKENS_API, self::POST );
	}

	/**
	 * Get timeline of events for an intention
	 *
	 * @param string $intention_id The payment intention ID.
	 *
	 * @return array
	 *
	 * @throws Exception - Exception thrown on request failure.
	 */
	public function get_timeline( $intention_id ) {
		return $this->request( [], self::TIMELINE_API . '/' . $intention_id, self::GET );
	}

	/**
	 * Get currency rates from the server.
	 *
	 * @param string $currency_from - The currency to convert from.
	 * @param ?array $currencies_to - An array of the currencies we want to convert into. If left empty, will get all supported currencies.
	 *
	 * @return array
	 *
	 * @throws API_Exception - Error contacting the API.
	 */
	public function get_currency_rates( string $currency_from, $currencies_to = null ) {
		$query_body = [ 'currency_from' => $currency_from ];

		if ( null !== $currencies_to ) {
			$query_body['currencies_to'] = $currencies_to;
		}

		return $this->request(
			$query_body,
			self::CURRENCY_API . '/rates',
			self::GET
		);
	}

	/**
	 * Get current account data
	 *
	 * @return array An array describing an account object.
	 *
	 * @throws API_Exception - Error contacting the API.
	 */
	public function get_account_data() {
		return $this->request(
			[
				'test_mode' => WC_Payments::get_gateway()->is_in_dev_mode(), // only send a test mode request if in dev mode.
			],
			self::ACCOUNTS_API,
			self::GET
		);
	}

	/**
	 * Update Stripe account data
	 *
	 * @param array $stripe_account_settings Settings to update.
	 *
	 * @return array Updated account data.
	 */
	public function update_account( $stripe_account_settings ) {
		return $this->request(
			$stripe_account_settings,
			self::ACCOUNTS_API,
			self::POST,
			true,
			true
		);
	}

	/**
	 * Request capability activation from the server
	 *
	 * @param   string $capability_id  Capability ID.
	 * @param   bool   $requested      State.
	 *
	 * @return  array                   Request result.
	 */
	public function request_capability( string $capability_id, bool $requested ) {
		return $this->request(
			[
				'capability_id' => $capability_id,
				'requested'     => $requested,
			],
			self::CAPABILITIES_API,
			self::POST,
			true,
			true
		);
	}

	/**
	 * Get data needed to initialize the onboarding flow
	 *
	 * @param string $return_url     - URL to redirect to at the end of the flow.
	 * @param array  $business_data  - Data to prefill the form.
	 * @param array  $site_data      - Data to track ToS agreement.
	 * @param array  $actioned_notes - Actioned WCPay note names to be sent to the on-boarding flow.
	 *
	 * @return array An array containing the url and state fields.
	 *
	 * @throws API_Exception Exception thrown on request failure.
	 */
	public function get_onboarding_data( $return_url, array $business_data = [], array $site_data = [], array $actioned_notes = [] ) {
		$request_args = apply_filters(
			'wc_payments_get_onboarding_data_args',
			[
				'return_url'          => $return_url,
				'business_data'       => $business_data,
				'site_data'           => $site_data,
				'create_live_account' => ! WC_Payments::get_gateway()->is_in_dev_mode(),
				'actioned_notes'      => $actioned_notes,
			]
		);

		return $this->request( $request_args, self::ONBOARDING_API . '/init', self::POST, true, true );
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
			[
				'redirect_url' => $redirect_url,
				'test_mode'    => WC_Payments::get_gateway()->is_in_dev_mode(), // only send a test mode request if in dev mode.
			],
			self::ACCOUNTS_API . '/login_links',
			self::POST,
			true,
			true
		);
	}

	/**
	 * Create a customer.
	 *
	 * @param array $customer_data Customer data.
	 *
	 * @return string The created customer's ID
	 *
	 * @throws API_Exception Error creating customer.
	 */
	public function create_customer( array $customer_data ): string {
		$customer_array = $this->request(
			$customer_data,
			self::CUSTOMERS_API,
			self::POST
		);

		return $customer_array['id'];
	}

	/**
	 * Update a customer.
	 *
	 * @param string $customer_id   ID of customer to update.
	 * @param array  $customer_data Data to be updated.
	 *
	 * @throws API_Exception Error updating customer.
	 */
	public function update_customer( $customer_id, $customer_data = [] ) {
		if ( null === $customer_id || '' === trim( $customer_id ) ) {
			throw new API_Exception(
				__( 'Customer ID is required', 'woocommerce-payments' ),
				'wcpay_mandatory_customer_id_missing',
				400
			);
		}

		$this->request(
			$customer_data,
			self::CUSTOMERS_API . '/' . $customer_id,
			self::POST
		);
	}

	/**
	 * Create a product.
	 *
	 * @param array $product_data Product data.
	 *
	 * @return array The created product's product and price IDs.
	 *
	 * @throws API_Exception Error creating the product.
	 */
	public function create_product( array $product_data ): array {
		return $this->request(
			$product_data,
			self::PRODUCTS_API,
			self::POST
		);
	}

	/**
	 * Update a product.
	 *
	 * @param string $product_id    ID of product to update.
	 * @param array  $product_data  Data to be updated.
	 *
	 * @return array The updated product's product and/or price IDs.
	 *
	 * @throws API_Exception Error updating product.
	 */
	public function update_product( string $product_id, array $product_data = [] ) : array {
		if ( null === $product_id || '' === trim( $product_id ) ) {
			throw new API_Exception(
				__( 'Product ID is required', 'woocommerce-payments' ),
				'wcpay_mandatory_product_id_missing',
				400
			);
		}

		return $this->request(
			$product_data,
			self::PRODUCTS_API . '/' . $product_id,
			self::POST
		);
	}

	/**
	 * Update a price.
	 *
	 * @param string $price_id    ID of price to update.
	 * @param array  $price_data  Data to be updated.
	 *
	 * @throws API_Exception Error updating price.
	 */
	public function update_price( string $price_id, array $price_data = [] ) {
		if ( null === $price_id || '' === trim( $price_id ) ) {
			throw new API_Exception(
				__( 'Price ID is required', 'woocommerce-payments' ),
				'wcpay_mandatory_price_id_missing',
				400
			);
		}

		$this->request(
			$price_data,
			self::PRICES_API . '/' . $price_id,
			self::POST
		);
	}

	/**
	 * Charges an invoice.
	 *
	 * Calling this function charges the customer. Pass the param 'paid_out_of_band' => true to mark the invoice as paid without charging the customer.
	 *
	 * @param string $invoice_id ID of the invoice to charge.
	 * @param array  $data       Parameters to send to the invoice /pay endpoint. Optional. Default is an empty array.
	 * @return array
	 *
	 * @throws API_Exception Error charging the invoice.
	 */
	public function charge_invoice( string $invoice_id, array $data = [] ) {
		return $this->request(
			$data,
			self::INVOICES_API . '/' . $invoice_id . '/pay',
			self::POST
		);
	}

	/**
	 * Fetch a WCPay subscription.
	 *
	 * @param string $wcpay_subscription_id Data used to create subscription.
	 *
	 * @return array The WCPay subscription.
	 *
	 * @throws API_Exception If fetching the subscription fails.
	 */
	public function get_subscription( string $wcpay_subscription_id ) {
		return $this->request(
			[],
			self::SUBSCRIPTIONS_API . '/' . $wcpay_subscription_id,
			self::GET
		);
	}

	/**
	 * Create a WCPay subscription.
	 *
	 * @param array $data Data used to create subscription.
	 *
	 * @return array New WCPay subscription.
	 *
	 * @throws API_Exception If creating the subscription fails.
	 */
	public function create_subscription( array $data = [] ) {
		return $this->request(
			$data,
			self::SUBSCRIPTIONS_API,
			self::POST
		);
	}

	/**
	 * Update a WCPay subscription.
	 *
	 * @param string $wcpay_subscription_id WCPay subscription ID.
	 * @param array  $data                  Update subscription data.
	 *
	 * @return array Updated WCPay subscription response from server.
	 *
	 * @throws API_Exception If updating the WCPay subscription fails.
	 */
	public function update_subscription( $wcpay_subscription_id, $data ) {
		return $this->request(
			$data,
			self::SUBSCRIPTIONS_API . '/' . $wcpay_subscription_id,
			self::POST
		);
	}

	/**
	 * Cancel a WC Pay subscription.
	 *
	 * @param string $wcpay_subscription_id WCPay subscription ID.
	 *
	 * @return array Canceled subscription.
	 *
	 * @throws API_Exception If canceling the subscription fails.
	 */
	public function cancel_subscription( string $wcpay_subscription_id ) {
		return $this->request(
			[],
			self::SUBSCRIPTIONS_API . '/' . $wcpay_subscription_id,
			self::DELETE
		);
	}

	/**
	 * Update a WCPay subscription item.
	 *
	 * @param string $wcpay_subscription_item_id WCPay subscription item ID.
	 * @param array  $data                       Update subscription item data.
	 *
	 * @return array Updated WCPay subscription item response from server.
	 *
	 * @throws API_Exception If updating the WCPay subscription item fails.
	 */
	public function update_subscription_item( $wcpay_subscription_item_id, $data ) {
		return $this->request(
			$data,
			self::SUBSCRIPTION_ITEMS_API . '/' . $wcpay_subscription_item_id,
			self::POST
		);
	}

	/**
	 * Get payment method details.
	 *
	 * @param string $payment_method_id Payment method ID.
	 *
	 * @return array Payment method details.
	 *
	 * @throws API_Exception If payment method does not exist.
	 */
	public function get_payment_method( $payment_method_id ) {
		return $this->request(
			[],
			self::PAYMENT_METHODS_API . '/' . $payment_method_id,
			self::GET
		);
	}

	/**
	 * Update payment method data.
	 *
	 * @param string $payment_method_id Payment method ID.
	 * @param array  $payment_method_data Payment method updated data.
	 *
	 * @return array Payment method details.
	 *
	 * @throws API_Exception If payment method update fails.
	 */
	public function update_payment_method( $payment_method_id, $payment_method_data = [] ) {
		return $this->request(
			$payment_method_data,
			self::PAYMENT_METHODS_API . '/' . $payment_method_id,
			self::POST
		);
	}

	/**
	 * Get payment methods for customer.
	 *
	 * @param string $customer_id The customer ID.
	 * @param string $type        Type of payment methods to fetch.
	 * @param int    $limit       Amount of items to fetch.
	 *
	 * @return array Payment methods response.
	 *
	 * @throws API_Exception If an error occurs.
	 */
	public function get_payment_methods( $customer_id, $type, $limit = 100 ) {
		return $this->request(
			[
				'customer' => $customer_id,
				'type'     => $type,
				'limit'    => $limit,
			],
			self::PAYMENT_METHODS_API,
			self::GET
		);
	}

	/**
	 * Detach a payment method from a customer.
	 *
	 * @param string $payment_method_id Payment method ID.
	 *
	 * @return array Payment method details.
	 *
	 * @throws API_Exception If detachment fails.
	 */
	public function detach_payment_method( $payment_method_id ) {
		return $this->request(
			[],
			self::PAYMENT_METHODS_API . '/' . $payment_method_id . '/detach',
			self::POST
		);
	}

	/**
	 * Records a new Terms of Service agreement.
	 *
	 * @param string $source     A string, which describes where the merchant agreed to the terms.
	 * @param string $user_name  The user_login of the current user.
	 *
	 * @return array An array, containing a `success` flag.
	 *
	 * @throws API_Exception If an error occurs.
	 */
	public function add_tos_agreement( $source, $user_name ) {
		return $this->request(
			[
				'source'    => $source,
				'user_name' => $user_name,
			],
			self::ACCOUNTS_API . '/tos_agreements',
			self::POST,
			true,
			true
		);
	}

	/**
	 * Track a order creation/update event.
	 *
	 * @param array $order_data  The order data, as an array.
	 * @param bool  $update      Is this an update event? (Defaults to false, which is a creation event).
	 *
	 * @return array An array, containing a `success` flag.
	 *
	 * @throws API_Exception If an error occurs.
	 */
	public function track_order( $order_data, $update = false ) {
		return $this->request(
			[
				'order_data' => $order_data,
				'update'     => $update,
			],
			self::TRACKING_API . '/order',
			self::POST
		);
	}

	/**
	 * Link the current customer with the browsing session, for tracking purposes.
	 *
	 * @param string $session_id  Session ID, specific to this site.
	 * @param string $customer_id Stripe customer ID.
	 *
	 * @return array An array, containing a `success` flag.
	 *
	 * @throws API_Exception If an error occurs.
	 */
	public function link_session_to_customer( $session_id, $customer_id ) {
		return $this->request(
			[
				'session'  => $session_id,
				'customer' => $customer_id,
			],
			self::TRACKING_API . '/link-session',
			self::POST
		);
	}

	/**
	 * Registers a new domain with Apple Pay.
	 *
	 * @param string $domain_name Domain name which to register for Apple Pay.
	 *
	 * @return array An array containing an id in case it has succeeded, or an error message in case it has failed.
	 *
	 * @throws API_Exception If an error occurs.
	 */
	public function register_domain_with_apple( $domain_name ) {
		return $this->request(
			[
				'test_mode'   => false, // Force live mode - Domain registration doesn't work in test mode.
				'domain_name' => $domain_name,
			],
			self::APPLE_PAY_API . '/domains',
			self::POST
		);
	}

	/**
	 * Retrieves a store's terminal locations.
	 *
	 * @return array[] Stripe terminal location objects.
	 * @see https://stripe.com/docs/api/terminal/locations/object
	 *
	 * @throws API_Exception If an error occurs.
	 */
	public function get_terminal_locations() {
		return $this->request( [], self::TERMINAL_LOCATIONS_API, self::GET );
	}

	/**
	 * Retrieves a store's terminal readers.
	 *
	 * @return array[] Stripe terminal readers objects.
	 * @see https://stripe.com/docs/api/terminal/readers/object
	 *
	 * @throws API_Exception If an error occurs.
	 */
	public function get_terminal_readers() {
		return $this->request( [], self::TERMINAL_READERS_API, self::GET );
	}

	/**
	 * Registers a card reader to a terminal.
	 *
	 * @param string  $location           The location to assign the reader to.
	 * @param string  $registration_code  A code generated by the reader used for registering to an account.
	 * @param ?string $label              Custom label given to the reader for easier identification.
	 *                                    If no label is specified, the registration code will be used.
	 * @param ?array  $metadata           Set of key-value pairs that you can attach to the reader.
	 *                                    Useful for storing additional information about the object.
	 *
	 * @return array Stripe terminal reader object.
	 * @see https://stripe.com/docs/api/terminal/readers/object
	 *
	 * @throws API_Exception If an error occurs.
	 */
	public function register_terminal_reader( string $location, string $registration_code, string $label = null, array $metadata = null ) {
		$request = [
			'location'          => $location,
			'registration_code' => $registration_code,
		];

		if ( null !== $label ) {
			$request['label'] = $label;
		}
		if ( null !== $metadata ) {
			$request['metadata'] = $metadata;
		}

		return $this->request( $request, self::TERMINAL_READERS_API, self::POST );
	}

	/**
	 * Creates a new terminal location.
	 *
	 * @param string  $display_name The display name of the terminal location.
	 * @param array   $address {
	 *     Address partials.
	 *
	 *     @type string $country     Two-letter country code.
	 *     @type string $line1       Address line 1.
	 *     @type string $line2       Optional. Address line 2.
	 *     @type string $city        Optional. City, district, suburb, town, or village.
	 *     @type int    $postal_code Optional. ZIP or postal code.
	 *     @type string $state       Optional. State, county, province, or region.
	 * }
	 * @param mixed[] $metadata Optional. Metadata for the location.
	 *
	 * @return array A Stripe terminal location object.
	 * @see https://stripe.com/docs/api/terminal/locations/object
	 *
	 * @throws API_Exception If an error occurs.
	 */
	public function create_terminal_location( $display_name, $address, $metadata = [] ) {
		if ( ! isset( $address['country'], $address['line1'] ) ) {
			throw new API_Exception(
				__( 'Address country and line 1 are required.', 'woocommerce-payments' ),
				'wcpay_invalid_terminal_location_request',
				400
			);
		}

		$request = [
			'display_name' => $display_name,
			'address'      => $address,
			'metadata'     => $metadata,
		];

		return $this->request(
			$request,
			self::TERMINAL_LOCATIONS_API,
			self::POST
		);
	}

	/**
	 * Updates an existing terminal location.
	 *
	 * @param string $location_id The id of the terminal location.
	 * @param string $display_name The display name of the terminal location.
	 * @param array  $address {
	 *     Address partials.
	 *
	 *     @type string $country     Two-letter country code.
	 *     @type string $line1       Address line 1.
	 *     @type string $line2       Optional. Address line 2.
	 *     @type string $city        Optional. City, district, suburb, town, or village.
	 *     @type int    $postal_code Optional. ZIP or postal code.
	 *     @type string $state       Optional. State, county, province, or region.
	 * }
	 *
	 * @return array A Stripe terminal location object.
	 * @see https://stripe.com/docs/api/terminal/locations/object
	 *
	 * @throws API_Exception If an error occurs.
	 */
	public function update_terminal_location( $location_id, $display_name, $address ) {
		// Any parameters not provided will be left unchanged so pass only supplied values.
		$update_request_body = array_merge(
			( isset( $address ) ? [ 'address' => $address ] : [] ),
			( isset( $display_name ) ? [ 'display_name' => $display_name ] : [] )
		);

		return $this->request(
			$update_request_body,
			self::TERMINAL_LOCATIONS_API . '/' . $location_id,
			self::POST
		);
	}

	/**
	 * Retrieves the specified terminal location.
	 *
	 * @param string $location_id The id of the terminal location.
	 *
	 * @return array A Stripe terminal location object.
	 * @see https://stripe.com/docs/api/terminal/locations/object
	 *
	 * @throws API_Exception If an error occurs.
	 */
	public function get_terminal_location( $location_id ) {
		return $this->request( [], self::TERMINAL_LOCATIONS_API . '/' . $location_id, self::GET );
	}

	/**
	 * Deletes the specified location object.
	 *
	 * @param string $location_id The id of the terminal location.
	 *
	 * @return array Stripe's terminal deletion response.
	 * @see https://stripe.com/docs/api/terminal/locations/delete
	 *
	 * @throws API_Exception If the location id is invalid or downstream call fails.
	 */
	public function delete_terminal_location( $location_id ) {
		return $this->request( [], self::TERMINAL_LOCATIONS_API . '/' . $location_id, self::DELETE );
	}

	/**
	 * Send the request to the WooCommerce Payment API
	 *
	 * @param array  $params           - Request parameters to send as either JSON or GET string. Defaults to test_mode=1 if either in dev or test mode, 0 otherwise.
	 * @param string $api              - The API endpoint to call.
	 * @param string $method           - The HTTP method to make the request with.
	 * @param bool   $is_site_specific - If true, the site ID will be included in the request url. Defaults to true.
	 * @param bool   $use_user_token   - If true, the request will be signed with the user token rather than blog token. Defaults to false.
	 *
	 * @return array
	 * @throws API_Exception - If the account ID hasn't been set.
	 */
	protected function request( $params, $api, $method, $is_site_specific = true, $use_user_token = false ) {
		// Apply the default params that can be overridden by the calling method.
		$params = wp_parse_args(
			$params,
			[
				'test_mode' => WC_Payments::get_gateway()->is_in_test_mode(),
			]
		);

		// Build the URL we want to send the URL to.
		$url = self::ENDPOINT_BASE;
		if ( $is_site_specific ) {
			$url .= '/' . self::ENDPOINT_SITE_FRAGMENT;
		}
		$url .= '/' . self::ENDPOINT_REST_BASE . '/' . $api;

		$headers                 = [];
		$headers['Content-Type'] = 'application/json; charset=utf-8';
		$headers['User-Agent']   = $this->user_agent;
		$body                    = null;

		$redacted_params = WC_Payments_Utils::redact_array( $params, self::API_KEYS_TO_REDACT );
		$redacted_url    = $url;

		if ( in_array( $method, [ self::GET, self::DELETE ], true ) ) {
			$url          .= '?' . http_build_query( $params );
			$redacted_url .= '?' . http_build_query( $redacted_params );
		} else {
			$headers['Idempotency-Key'] = $this->uuid();
			$body                       = wp_json_encode( $params );
			if ( ! $body ) {
				throw new API_Exception(
					__( 'Unable to encode body for request to WooCommerce Payments API.', 'woocommerce-payments' ),
					'wcpay_client_unable_to_encode_json',
					0
				);
			}
		}

		Logger::log( "REQUEST $method $redacted_url" );
		Logger::log(
			'HEADERS: '
			. var_export( $headers, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		);

		if ( null !== $body ) {
			Logger::log(
				'BODY: '
				. var_export( $redacted_params, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			);
		}

		$response = $this->http_client->remote_request(
			[
				'url'             => $url,
				'method'          => $method,
				'headers'         => apply_filters( 'wcpay_api_request_headers', $headers ),
				'timeout'         => self::API_TIMEOUT_SECONDS,
				'connect_timeout' => self::API_TIMEOUT_SECONDS,
			],
			$body,
			$is_site_specific,
			$use_user_token
		);

		$response_body = $this->extract_response_body( $response );
		Logger::log(
			'RESPONSE: '
			. var_export( WC_Payments_Utils::redact_array( $response_body, self::API_KEYS_TO_REDACT ), true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		);

		return $response_body;
	}

	/**
	 * Handles issues with level3 data and retries requests when necessary.
	 *
	 * @param array  $params           - Request parameters to send as either JSON or GET string. Defaults to test_mode=1 if either in dev or test mode, 0 otherwise.
	 * @param string $api              - The API endpoint to call.
	 * @param string $method           - The HTTP method to make the request with.
	 * @param bool   $is_site_specific - If true, the site ID will be included in the request url.
	 *
	 * @return array
	 * @throws API_Exception - If the account ID hasn't been set.
	 */
	private function request_with_level3_data( $params, $api, $method, $is_site_specific = true ) {
		// If level3 data is not present for some reason, simply proceed normally.
		if ( empty( $params['level3'] ) || ! is_array( $params['level3'] ) ) {
			return $this->request( $params, $api, $method, $is_site_specific );
		}

		// If level3 data doesn't contain any items, add a zero priced fee to meet Stripe's requirement.
		if ( ! isset( $params['level3']['line_items'] ) || ! is_array( $params['level3']['line_items'] ) || 0 === count( $params['level3']['line_items'] ) ) {
			$params['level3']['line_items'] = [
				[
					'discount_amount'     => 0,
					'product_code'        => 'zero-cost-fee',
					'product_description' => 'Zero cost fee',
					'quantity'            => 1,
					'tax_amount'          => 0,
					'unit_cost'           => 0,
				],
			];
		}

		try {
			return $this->request( $params, $api, $method, $is_site_specific );
		} catch ( API_Exception $e ) {
			if ( 'invalid_request_error' !== $e->get_error_code() ) {
				throw $e;
			}

			// phpcs:disable WordPress.PHP.DevelopmentFunctions

			// Log the issue so we could debug it.
			Logger::error(
				'Level3 data error: ' . PHP_EOL
				. print_r( $e->getMessage(), true ) . PHP_EOL
				. print_r( 'Level 3 data sent: ', true ) . PHP_EOL
				. print_r( $params['level3'], true )
			);

			// phpcs:enable WordPress.PHP.DevelopmentFunctions

			// Retry without level3 data.
			unset( $params['level3'] );
			return $this->request( $params, $api, $method, $is_site_specific );
		}
	}

	/**
	 * From a given response extract the body. Invalid HTTP codes will result in an error.
	 *
	 * @param array $response That was given to us by http_client remote_request.
	 *
	 * @return array $response_body
	 *
	 * @throws API_Exception Standard exception in case we can't extract the body.
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
			throw new API_Exception(
				$message,
				'wcpay_unparseable_or_null_body',
				$response_code
			);
		}

		// Check error codes for 4xx and 5xx responses.
		if ( 400 <= $response_code ) {
			$error_type = null;
			if ( isset( $response_body['code'] ) && 'amount_too_small' === $response_body['code'] ) {
				throw new Amount_Too_Small_Exception(
					$response_body['message'],
					$response_body['data']['minimum_amount'],
					$response_body['data']['currency'],
					$response_code
				);
			} elseif ( isset( $response_body['error'] ) ) {
				$error_code    = $response_body['error']['code'] ?? $response_body['error']['type'] ?? null;
				$error_message = $response_body['error']['message'] ?? null;
				$error_type    = $response_body['error']['type'] ?? null;
			} elseif ( isset( $response_body['code'] ) ) {
				$error_code    = $response_body['code'];
				$error_message = $response_body['message'];
			} else {
				$error_code    = 'wcpay_client_error_code_missing';
				$error_message = __( 'Server error. Please try again.', 'woocommerce-payments' );
			}

			$message = sprintf(
				// translators: This is an error API response.
				_x( 'Error: %1$s', 'API error message to throw as Exception', 'woocommerce-payments' ),
				$error_message
			);

			Logger::error( "$error_message ($error_code)" );
			throw new API_Exception( $message, $error_code, $response_code, $error_type );
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
		$order = $this->wcpay_db->order_from_charge_id( $charge_id );

		// Add order information to the `$transaction`.
		// If the order couldn't be retrieved, return an empty order.
		$object['order'] = null;
		if ( $order ) {
			$object['order'] = $this->build_order_info( $order );
		}

		return $object;
	}


	/**
	 * Creates the array representing order for frontend.
	 *
	 * @param WC_Order $order The order.
	 * @return array
	 */
	private function build_order_info( WC_Order $order ): array {
		$order_info = [
			'number'       => $order->get_order_number(),
			'url'          => $order->get_edit_order_url(),
			'customer_url' => $this->get_customer_url( $order ),
		];

		if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			$order_info['subscriptions'] = [];

			$subscriptions = wcs_get_subscriptions_for_order( $order, [ 'order_type' => [ 'parent', 'renewal' ] ] );
			foreach ( $subscriptions as $subscription ) {
				$order_info['subscriptions'][] = [
					'number' => $subscription->get_order_number(),
					'url'    => $subscription->get_edit_order_url(),
				];
			}
		}
		return $order_info;
	}

	/**
	 * Generates url to single customer in analytics table.
	 *
	 * @param WC_Order $order The Order.
	 * @return string|null
	 */
	private function get_customer_url( WC_Order $order ) {
		$customer_id = DataStore::get_existing_customer_id_from_order( $order );

		if ( ! $customer_id ) {
			return null;
		}

		return add_query_arg(
			[
				'page'      => 'wc-admin',
				'path'      => '/customers',
				'filter'    => 'single_customer',
				'customers' => $customer_id,
			],
			'admin.php'
		);
	}

	/**
	 * De-serialize a charge array into a charge object
	 *
	 * @param array $charge_array - The charge array to de-serialize.
	 *
	 * @return WC_Payments_API_Charge
	 * @throws API_Exception - Unable to deserialize charge array.
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
	 * @throws API_Exception - Unable to deserialize intention array.
	 */
	private function deserialize_intention_object_from_array( array $intention_array ) {
		// TODO: Throw an exception if the response array doesn't contain mandatory properties.
		$created = new DateTime();
		$created->setTimestamp( $intention_array['created'] );

		$charge             = 0 < $intention_array['charges']['total_count'] ? end( $intention_array['charges']['data'] ) : null;
		$next_action        = ! empty( $intention_array['next_action'] ) ? $intention_array['next_action'] : [];
		$last_payment_error = ! empty( $intention_array['last_payment_error'] ) ? $intention_array['last_payment_error'] : [];
		$metadata           = ! empty( $intention_array['metadata'] ) ? $intention_array['metadata'] : [];

		$intent = new WC_Payments_API_Intention(
			$intention_array['id'],
			$intention_array['amount'],
			$intention_array['currency'],
			$intention_array['customer'] ?? $charge['customer'] ?? null,
			$intention_array['payment_method'] ?? $charge['payment_method'] ?? $intention_array['source'] ?? null,
			$created,
			$intention_array['status'],
			$charge ? $charge['id'] : null,
			$intention_array['client_secret'],
			$next_action,
			$last_payment_error,
			$charge ? $charge['payment_method_details'] : null,
			$metadata
		);

		return $intent;
	}

	/**
	 * Returns a formatted intention description.
	 *
	 * @param  int $order_id The order ID.
	 * @return string        A formatted intention description.
	 */
	private function get_intent_description( int $order_id ): string {
		$domain_name = str_replace( [ 'https://', 'http://' ], '', get_site_url() );
		$blog_id     = $this->get_blog_id();

		// Forgo i18n as this is only visible in the Stripe dashboard.
		return sprintf(
			'Online Payment%s for %s%s',
			0 !== $order_id ? " for Order #$order_id" : '',
			$domain_name,
			null !== $blog_id ? " blog_id $blog_id" : ''
		);
	}

	/**
	 * Returns a v4 UUID.
	 *
	 * @return string
	 */
	private function uuid() {
		$arr    = array_values( unpack( 'N1a/n4b/N1c', random_bytes( 16 ) ) );
		$arr[2] = ( $arr[2] & 0x0fff ) | 0x4000;
		$arr[3] = ( $arr[3] & 0x3fff ) | 0x8000;
		return vsprintf( '%08x-%04x-%04x-%04x-%04x%08x', $arr );
	}


	/**
	 * Fetch readers charge summary.
	 *
	 * @param string $charge_date Charge date for readers.
	 *
	 * @return array reader objects.
	 */
	public function get_readers_charge_summary( string $charge_date ) : array {
		return $this->request( [ 'charge_date' => $charge_date ], self::READERS_CHARGE_SUMMARY, self::GET );
	}
}
