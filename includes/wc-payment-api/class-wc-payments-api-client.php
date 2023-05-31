<?php
/**
 * WC_Payments_API_Client class
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

use WCPay\Constants\Payment_Intent_Status;
use WCPay\Exceptions\API_Exception;
use WCPay\Exceptions\Amount_Too_Small_Exception;
use WCPay\Exceptions\Connection_Exception;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\Fraud_Prevention\Buyer_Fingerprinting_Service;
use WCPay\Logger;
use Automattic\WooCommerce\Admin\API\Reports\Customers\DataStore;
use WCPay\Database_Cache;
use WCPay\Core\Server\Request;
use WCPay\Core\Server\Request\List_Fraud_Outcome_Transactions;

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

	const API_TIMEOUT_SECONDS      = 70;
	const API_RETRIES_LIMIT        = 3;
	const API_RETRIES_BACKOFF_MSEC = 250;

	const ACCOUNTS_API                 = 'accounts';
	const CAPABILITIES_API             = 'accounts/capabilities';
	const WOOPAY_API                   = 'accounts/platform_checkout';
	const APPLE_PAY_API                = 'apple_pay';
	const CHARGES_API                  = 'charges';
	const CONN_TOKENS_API              = 'terminal/connection_tokens';
	const TERMINAL_LOCATIONS_API       = 'terminal/locations';
	const CUSTOMERS_API                = 'customers';
	const CURRENCY_API                 = 'currency';
	const INTENTIONS_API               = 'intentions';
	const REFUNDS_API                  = 'refunds';
	const DEPOSITS_API                 = 'deposits';
	const TRANSACTIONS_API             = 'transactions';
	const DISPUTES_API                 = 'disputes';
	const FILES_API                    = 'files';
	const ONBOARDING_API               = 'onboarding';
	const TIMELINE_API                 = 'timeline';
	const PAYMENT_METHODS_API          = 'payment_methods';
	const SETUP_INTENTS_API            = 'setup_intents';
	const TRACKING_API                 = 'tracking';
	const PRODUCTS_API                 = 'products';
	const PRICES_API                   = 'products/prices';
	const INVOICES_API                 = 'invoices';
	const SUBSCRIPTIONS_API            = 'subscriptions';
	const SUBSCRIPTION_ITEMS_API       = 'subscriptions/items';
	const READERS_CHARGE_SUMMARY       = 'reader-charges/summary';
	const TERMINAL_READERS_API         = 'terminal/readers';
	const MINIMUM_RECURRING_AMOUNT_API = 'subscriptions/minimum_amount';
	const CAPITAL_API                  = 'capital';
	const WEBHOOK_FETCH_API            = 'webhook/failed_events';
	const DOCUMENTS_API                = 'documents';
	const VAT_API                      = 'vat';
	const LINKS_API                    = 'links';
	const AUTHORIZATIONS_API           = 'authorizations';
	const FRAUD_OUTCOMES_API           = 'fraud_outcomes';
	const FRAUD_RULESET_API            = 'fraud_ruleset';

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

	const EVENT_AUTHORIZED            = 'authorized';
	const EVENT_AUTHORIZATION_VOIDED  = 'authorization_voided';
	const EVENT_AUTHORIZATION_EXPIRED = 'authorization_expired';
	const EVENT_CAPTURED              = 'captured';
	const EVENT_PARTIAL_REFUND        = 'partial_refund';
	const EVENT_FULL_REFUND           = 'full_refund';
	const EVENT_REFUND_FAILURE        = 'refund_failed';
	const EVENT_FAILED                = 'failed';
	// const EVENT_BLOCKED                = 'blocked'; // no event for this.
	const EVENT_DISPUTE_NEEDS_RESPONSE = 'dispute_needs_response';
	const EVENT_DISPUTE_IN_REVIEW      = 'dispute_in_review';
	const EVENT_DISPUTE_WON            = 'dispute_won';
	const EVENT_DISPUTE_LOST           = 'dispute_lost';
	// const EVENT_DISPUTE_ACCEPTED       = 'dispute_accepted'; // set as 'lost' in the API.
	const EVENT_DISPUTE_WARNING_CLOSED  = 'dispute_warning_closed';
	const EVENT_DISPUTE_CHARGE_REFUNDED = 'dispute_charge_refunded';
	const EVENT_FINANCING_PAYDOWN       = 'financing_paydown';
	const ARN_UNAVAILABLE_STATUS        = 'unavailable';
	const EVENT_FRAUD_OUTCOME_REVIEW    = 'fraud_outcome_review';
	const EVENT_FRAUD_OUTCOME_BLOCK     = 'fraud_outcome_block';

	/**
	 * An array used to determine the order of events in case they share the same timestamp
	 *
	 * @var array
	 */
	private static $events_order = [
		self::EVENT_AUTHORIZED,
		self::EVENT_AUTHORIZATION_VOIDED,
		self::EVENT_AUTHORIZATION_EXPIRED,
		self::EVENT_FRAUD_OUTCOME_REVIEW,
		self::EVENT_FRAUD_OUTCOME_BLOCK,
		self::EVENT_CAPTURED,
		self::EVENT_PARTIAL_REFUND,
		self::EVENT_FULL_REFUND,
		self::EVENT_REFUND_FAILURE,
		self::EVENT_FAILED,
		// self::EVENT_BLOCKED, uncomment when needed.
		self::EVENT_DISPUTE_NEEDS_RESPONSE,
		self::EVENT_DISPUTE_IN_REVIEW,
		self::EVENT_DISPUTE_WON,
		self::EVENT_DISPUTE_LOST,
		// self::EVENT_DISPUTE_ACCEPTED, uncommented when needed.
		self::EVENT_FINANCING_PAYDOWN,
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
	 * Retrieves transaction list for a given fraud outcome status.
	 *
	 * @param List_Fraud_Outcome_Transactions $request Fraud outcome transactions request.
	 *
	 * @return array
	 */
	public function list_fraud_outcome_transactions( $request ) {
		$fraud_outcomes = $request->send( 'wcpay_list_fraud_outcome_transactions_request' );

		$page      = $request->get_param( 'page' );
		$page_size = $request->get_param( 'pagesize' );

		// Handles the pagination.
		$fraud_outcomes = array_slice( $fraud_outcomes, ( max( $page, 1 ) - 1 ) * $page_size, $page_size );

		return [
			'data' => $fraud_outcomes,
		];
	}

	/**
	 * Retrieves transactions summary for a given fraud outcome status.
	 *
	 * @param List_Fraud_Outcome_Transactions $request Fraud outcome transactions request.
	 *
	 * @return array
	 */
	public function list_fraud_outcome_transactions_summary( $request ) {
		$fraud_outcomes = $request->send( 'wcpay_list_fraud_outcome_transactions_summary_request' );

		$total      = 0;
		$currencies = [];

		foreach ( $fraud_outcomes as $outcome ) {
			$total       += $outcome['amount'];
			$currencies[] = strtolower( $outcome['currency'] );
		}

		return [
			'count'      => count( $fraud_outcomes ),
			'total'      => (int) $total,
			'currencies' => array_unique( $currencies ),
		];
	}

	/**
	 * Fetch transactions search options for provided query.
	 *
	 * @param List_Fraud_Outcome_Transactions $request Fraud outcome transactions request.
	 *
	 * @return array|WP_Error Search results.
	 */
	public function get_fraud_outcome_transactions_search_autocomplete( $request ) {
		$fraud_outcomes = $request->send( 'wcpay_get_fraud_outcome_transactions_search_autocomplete_request' );

		$search_term = $request->get_param( 'search_term' );

		$order = wc_get_order( $search_term );

		$results = array_filter(
			$fraud_outcomes,
			function ( $outcome ) use ( $search_term ) {
				return preg_match( "/{$search_term}/i", $outcome['customer_name'] );
			}
		);

		$results = array_map(
			function ( $result ) {
				return [
					'key'   => 'customer-' . $result['order_id'],
					'label' => $result['customer_name'],
				];
			},
			$fraud_outcomes
		);

		if ( $order ) {
			if ( function_exists( 'wcs_is_subscription' ) && wcs_is_subscription( $order ) ) {
				$prefix = __( 'Subscription #', 'woocommerce-payments' );
			} else {
				$prefix = __( 'Order #', 'woocommerce-payments' );
			}

			array_unshift(
				$results,
				[
					'key'   => 'order-' . $order->get_id(),
					'label' => $prefix . $search_term,
				]
			);
		}

		return $results;
	}

	/**
	 * Retrieves transactions summary for a given fraud outcome status.
	 *
	 * @param List_Fraud_Outcome_Transactions $request Fraud outcome transactions request.
	 *
	 * @return array
	 */
	public function get_fraud_outcome_transactions_export( $request ) {
		$fraud_outcomes = $request->send( 'wcpay_get_fraud_outcome_transactions_export_request' );

		return [
			'data' => $fraud_outcomes,
		];
	}

	/**
	 * Initiates transactions export via API.
	 *
	 * @param array  $filters    The filters to be used in the query.
	 * @param string $user_email The email to search for.
	 * @param string $deposit_id The deposit to filter on.
	 *
	 * @return array Export summary
	 *
	 * @throws API_Exception - Exception thrown on request failure.
	 */
	public function get_transactions_export( $filters = [], $user_email = '', $deposit_id = null ) {
		// Map Order # terms to the actual charge id to be used in the server.
		if ( ! empty( $filters['search'] ) ) {
			$filters['search'] = WC_Payments_Utils::map_search_orders_to_charge_ids( $filters['search'] );
		}
		if ( ! empty( $user_email ) ) {
			$filters['user_email'] = $user_email;
		}
		if ( ! empty( $deposit_id ) ) {
			$filters['deposit_id'] = $deposit_id;
		}

		return $this->request( $filters, self::TRANSACTIONS_API . '/download', self::POST );
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

		return $this->add_order_info_to_charge_object( $transaction['charge_id'], $transaction );
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
	 * Get summary of disputes.
	 *
	 * @param array $filters The filters to be used in the query.
	 *
	 * @return array
	 * @throws API_Exception - Exception thrown on request failure.
	 */
	public function get_disputes_summary( array $filters = [] ):array {
		return $this->request( [ $filters ], self::DISPUTES_API . '/summary', self::GET );
	}

	/**
	 * Gets a list of dispute statuses and a total count for each.
	 *
	 * @return array Dispute status counts in the format: [ '{status}' => count ].
	 * @throws API_Exception - Exception thrown on request failure.
	 */
	public function get_dispute_status_counts() {
		return $this->request( [], self::DISPUTES_API . '/status_counts', self::GET );
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
		return $this->add_order_info_to_charge_object( $charge_id, $dispute );
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
		// Invalidate the dispute status cache.
		\WC_Payments::get_database_cache()->delete( Database_Cache::DISPUTE_STATUS_COUNTS_KEY );

		if ( is_wp_error( $dispute ) ) {
			return $dispute;
		}

		$charge_id = is_array( $dispute['charge'] ) ? $dispute['charge']['id'] : $dispute['charge'];
		return $this->add_order_info_to_charge_object( $charge_id, $dispute );
	}

	/**
	 * Close dispute with provided id.
	 *
	 * @param string $dispute_id id of dispute to close.
	 * @return array dispute object.
	 */
	public function close_dispute( $dispute_id ) {
		$dispute = $this->request( [], self::DISPUTES_API . '/' . $dispute_id . '/close', self::POST );
		// Invalidate the dispute status cache.
		\WC_Payments::get_database_cache()->delete( Database_Cache::DISPUTE_STATUS_COUNTS_KEY );

		if ( is_wp_error( $dispute ) ) {
			return $dispute;
		}

		$charge_id = is_array( $dispute['charge'] ) ? $dispute['charge']['id'] : $dispute['charge'];
		return $this->add_order_info_to_charge_object( $charge_id, $dispute );
	}

	/**
	 * Initiates disputes export via API.
	 *
	 * @param array  $filters    The filters to be used in the query.
	 * @param string $user_email The email to search for.
	 *
	 * @return array Export summary
	 *
	 * @throws API_Exception - Exception thrown on request failure.
	 */
	public function get_disputes_export( $filters = [], $user_email = '' ) {
		if ( ! empty( $user_email ) ) {
			$filters['user_email'] = $user_email;
		}

		return $this->request( $filters, self::DISPUTES_API . '/download', self::POST );
	}

	/**
	 * Initiates deposits export via API.
	 *
	 * @param array  $filters    The filters to be used in the query.
	 * @param string $user_email The email to send export to.
	 *
	 * @return array Export summary
	 *
	 * @throws API_Exception - Exception thrown on request failure.
	 */
	public function get_deposits_export( $filters = [], $user_email = '' ) {
		if ( ! empty( $user_email ) ) {
			$filters['user_email'] = $user_email;
		}

		return $this->request( $filters, self::DEPOSITS_API . '/download', self::POST );
	}

	/**
	 * Upload file and return file object.
	 *
	 * @param WP_REST_Request $request request object received.
	 *
	 * @return array file object.
	 * @throws API_Exception - If request throws.
	 */
	public function upload_file( $request ) {
		$purpose     = $request->get_param( 'purpose' );
		$file_params = $request->get_file_params();
		$file_name   = $file_params['file']['name'];
		$file_type   = $file_params['file']['type'];
		$as_account  = (bool) $request->get_param( 'as_account' );

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
			'file_name'  => $file_name,
			'file_type'  => $file_type,
			'purpose'    => $purpose,
			'as_account' => $as_account,
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
	 * Retrieve a file content via API.
	 *
	 * @param string $file_id - API file id.
	 * @param bool   $as_account - add the current account to header request.
	 *
	 * @return array
	 * @throws API_Exception
	 */
	public function get_file_contents( string $file_id, bool $as_account = true ) : array {
		try {
			return $this->request( [ 'as_account' => $as_account ], self::FILES_API . '/' . $file_id . '/contents', self::GET );
		} catch ( API_Exception $e ) {
			Logger::error( 'Error retrieving file contents for ' . $file_id . '. ' . $e->getMessage() );
			return [];
		}
	}

	/**
	 * Retrieve a file details via API.
	 *
	 * @param string $file_id - API file id.
	 * @param bool   $as_account - add the current account to header request.
	 *
	 * @return array
	 * @throws API_Exception
	 */
	public function get_file( string $file_id, bool $as_account = true ) : array {
		return $this->request( [ 'as_account' => $as_account ], self::FILES_API . '/' . $file_id, self::GET );
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
	 * @param string $id The payment intention ID or order ID.
	 *
	 * @return array
	 *
	 * @throws Exception - Exception thrown on request failure.
	 */
	public function get_timeline( $id ) {
		$timeline = $this->request( [], self::TIMELINE_API . '/' . $id, self::GET );

		$has_fraud_outcome_event = false;

		if ( ! empty( $timeline ) && ! empty( $timeline['data'] ) && is_array( $timeline['data'] ) ) {
			foreach ( $timeline['data'] as $event ) {
				if ( in_array( $event['type'], [ self::EVENT_FRAUD_OUTCOME_REVIEW, self::EVENT_FRAUD_OUTCOME_BLOCK ], true ) ) {
					$has_fraud_outcome_event = true;
					break;
				}
			}
		}

		if ( $has_fraud_outcome_event ) {
			$order_id = $id;

			if ( ! is_numeric( $order_id ) ) {
				$intent   = $this->get_intent( $id );
				$order_id = $intent->get_metadata()['order_id'];
			}

			$order = wc_get_order( $order_id );

			if ( false === $order ) {
				return $timeline;
			}

			$manual_entry_meta = $order->get_meta( '_wcpay_fraud_outcome_manual_entry', true );

			if ( ! empty( $manual_entry_meta ) ) {
				$timeline['data'][] = $manual_entry_meta;

				// Sort by date desc, then by type desc as specified in events_order.
				usort(
					$timeline['data'],
					function( $a, $b ) {
						$result = $b['datetime'] <=> $a['datetime'];
						if ( 0 !== $result ) {
							return $result;
						}
						return array_search( $b['type'], self::$events_order, true ) <=> array_search( $a['type'], self::$events_order, true );
					}
				);
			}
		}

		return $timeline;
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
		if ( empty( $currency_from ) ) {
			throw new API_Exception(
				__( 'Currency From parameter is required', 'woocommerce-payments' ),
				'wcpay_mandatory_currency_from_missing',
				400
			);
		}

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
	 * Get current woopay eligibility
	 *
	 * @return array An array describing woopay eligibility.
	 *
	 * @throws API_Exception - Error contacting the API.
	 */
	public function get_woopay_eligibility() {
		return $this->request(
			[
				'test_mode' => WC_Payments::mode()->is_dev(), // only send a test mode request if in dev mode.
			],
			self::WOOPAY_API,
			self::GET
		);
	}

	/**
	 * Update woopay data
	 *
	 * @param array $data Data to update.
	 *
	 * @return array An array describing request result.
	 *
	 * @throws API_Exception - Error contacting the API.
	 */
	public function update_woopay( $data ) {
		return $this->request(
			array_merge(
				[ 'test_mode' => WC_Payments::mode()->is_dev() ],
				$data
			),
			self::WOOPAY_API,
			self::POST
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
	 * @param array  $site_data      - Data to track ToS agreement.
	 * @param array  $actioned_notes - Actioned WCPay note names to be sent to the on-boarding flow.
	 * @param array  $account_data   - Data to prefill the onboarding.
	 * @param bool   $progressive    - Whether we need to enable progressive onboarding prefill.
	 * @param bool   $collect_payout_requirements - Whether we need to redirect user to Stripe KYC to complete their payouts data.
	 *
	 * @return array An array containing the url and state fields.
	 *
	 * @throws API_Exception Exception thrown on request failure.
	 */
	public function get_onboarding_data( $return_url, array $site_data = [], array $actioned_notes = [], $account_data = [], bool $progressive = false, $collect_payout_requirements = false ) {
		$request_args = apply_filters(
			'wc_payments_get_onboarding_data_args',
			[
				'return_url'                  => $return_url,
				'site_data'                   => $site_data,
				'create_live_account'         => ! WC_Payments::mode()->is_dev(),
				'actioned_notes'              => $actioned_notes,
				'progressive'                 => $progressive,
				'collect_payout_requirements' => $collect_payout_requirements,
				'account_data'                => $account_data,
			]
		);

		return $this->request( $request_args, self::ONBOARDING_API . '/init', self::POST, true, true );
	}

	/**
	 * Get the fields data to be used by the onboarding flow.
	 *
	 * @param string $locale The locale to ask for from the server.
	 *
	 * @return array An array containing the fields data.
	 *
	 * @throws API_Exception Exception thrown on request failure.
	 */
	public function get_onboarding_fields_data( string $locale = '' ): array {
		$fields_data = $this->request(
			[
				'locale'    => $locale,
				'test_mode' => WC_Payments::mode()->is_test(),
			],
			self::ONBOARDING_API . '/fields_data',
			self::GET,
			false,
			true
		);

		if ( ! is_array( $fields_data ) ) {
			return [];
		}

		return $fields_data;
	}

	/**
	 * Get the business types, needed for our KYC onboarding flow.
	 *
	 * @return array An array containing the business types.
	 *
	 * @throws API_Exception Exception thrown on request failure.
	 */
	public function get_onboarding_business_types(): array {
		$business_types = $this->request(
			[],
			self::ONBOARDING_API . '/business_types',
			self::GET,
			true,
			true
		);

		if ( ! is_array( $business_types ) ) {
			return [];
		}

		return $business_types;
	}

	/**
	 * Get the required verification information, needed for our KYC onboarding flow.
	 *
	 * @param string      $country_code The country code.
	 * @param string      $type         The business type.
	 * @param string|null $structure    The business structure (optional).
	 *
	 * @return array An array containing the required verification information.
	 *
	 * @throws API_Exception Exception thrown on request failure.
	 */
	public function get_onboarding_required_verification_information( string $country_code, string $type, $structure = null ) {
		$params = [
			'country' => $country_code,
			'type'    => $type,
		];

		if ( ! is_null( $structure ) ) {
			$params = array_merge( $params, [ 'structure' => $structure ] );
		}

		return $this->request(
			$params,
			self::ONBOARDING_API . '/required_verification_information',
			self::GET,
			true,
			true
		);
	}

	/**
	 * Get a link's details from the server.
	 *
	 * @param array $args The arguments to be sent with the link request.
	 *
	 * @return array The link object with an url field.
	 *
	 * @throws API_Exception When something goes wrong with the request, or the link is not valid.
	 */
	public function get_link( array $args ) {
		return $this->request(
			$args,
			self::LINKS_API,
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
	 * Fetch an invoice.
	 *
	 * @param string $invoice_id ID of the invoice to get.
	 *
	 * @return array The invoice.
	 *
	 * @throws API_Exception If fetching the invoice fails.
	 */
	public function get_invoice( string $invoice_id ) {
		return $this->request(
			[],
			self::INVOICES_API . '/' . $invoice_id,
			self::GET
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
	 * Retrieves the list of failed webhook events and their data.
	 *
	 * @return array List of failed webhook events.
	 *
	 * @throws API_Exception If an error occurs.
	 */
	public function get_failed_webhook_events() {
		return $this->request( [], self::WEBHOOK_FETCH_API, self::POST );
	}

	/**
	 * Get summary of documents.
	 *
	 * @param array $filters The filters to be used in the query.
	 *
	 * @return array
	 * @throws API_Exception - Exception thrown on request failure.
	 */
	public function get_documents_summary( array $filters = [] ) {
		return $this->request( $filters, self::DOCUMENTS_API . '/summary', self::GET );
	}

	/**
	 * Request a document from the server and returns the full response.
	 *
	 * @param string $document_id The document's ID.
	 *
	 * @return array HTTP response on success.
	 *
	 * @throws API_Exception - If not connected or request failed.
	 */
	public function get_document( $document_id ) {
		return $this->request( [], self::DOCUMENTS_API . '/' . $document_id, self::GET, true, false, true );
	}

	/**
	 * Validates a VAT number on the server and returns the full response.
	 *
	 * @param string $vat_number The VAT number.
	 *
	 * @return array HTTP response on success.
	 *
	 * @throws API_Exception - If not connected or request failed.
	 */
	public function validate_vat( $vat_number ) {
		return $this->request( [], self::VAT_API . '/' . $vat_number, self::GET );
	}

	/**
	 * Saves the VAT details on the server and returns the full response.
	 *
	 * @param string $vat_number The VAT number.
	 * @param string $name       The company's name.
	 * @param string $address    The company's address.
	 *
	 * @return array HTTP response on success.
	 *
	 * @throws API_Exception - If not connected or request failed.
	 */
	public function save_vat_details( $vat_number, $name, $address ) {
		$response = $this->request(
			[
				'vat_number' => $vat_number,
				'name'       => $name,
				'address'    => $address,
			],
			self::VAT_API,
			self::POST
		);

		WC_Payments::get_account_service()->refresh_account_data();

		return $response;
	}

	/**
	 * Saves the ruleset config as the latest one for the account.
	 *
	 * @param   array $ruleset_config  The ruleset array.
	 *
	 * @return  array                  HTTP resposne on success.
	 *
	 * @throws API_Exception - If not connected or request failed.
	 */
	public function save_fraud_ruleset( $ruleset_config ) {
		$response = $this->request(
			[
				'ruleset_config' => $ruleset_config,
			],
			self::FRAUD_RULESET_API,
			self::POST
		);

		return $response;
	}

	/**
	 * Get the latest fraud ruleset for the account.
	 *
	 * @return  array          HTTP resposne on success.
	 *
	 * @throws API_Exception - If not connected or request failed.
	 */
	public function get_latest_fraud_ruleset() {
		$response = $this->request(
			[],
			self::FRAUD_RULESET_API,
			self::GET
		);

		return $response;
	}

	/**
	 * Gets the latest fraud outcome for a given payment intent id.
	 *
	 * @param string $id Payment intent id.
	 *
	 * @throws API_Exception - If not connected or request failed.
	 *
	 * @return array The response object.
	 */
	public function get_latest_fraud_outcome( $id ) {
		$response = $this->request(
			[],
			self::FRAUD_OUTCOMES_API . '/order_id/' . $id,
			self::GET
		);

		if ( is_array( $response ) && count( $response ) > 0 ) {
			return $response[0];
		}

		return $response;
	}

	/**
	 * Check if the merchant is eligible for Progressive Onboarding based on self-assessment information.
	 *
	 * @param array $business_info   Business information.
	 * @param array $store_info      Store information.
	 * @param array $woo_store_stats Optional. Stats about the WooCommerce store to given more context to the PO eligibility decision.
	 *
	 * @return array HTTP response on success.
	 *
	 * @throws API_Exception - If not connected to server or request failed.
	 */
	public function get_onboarding_po_eligible( array $business_info, array $store_info, array $woo_store_stats = [] ): array {
		return $this->request(
			[
				'business'        => $business_info,
				'store'           => $store_info,
				'woo_store_stats' => $woo_store_stats,
			],
			self::ONBOARDING_API . '/router/po_eligible',
			self::POST,
			true,
			true
		);
	}

	/**
	 * Sends a request object.
	 *
	 * @param  Request $request The request to send.
	 * @return array            A response object.
	 */
	public function send_request( Request $request ) {
		return $this->request(
			$request->get_params(),
			$request->get_api(),
			$request->get_method(),
			$request->is_site_specific(),
			$request->should_use_user_token(),
			$request->should_return_raw_response()
		);
	}

	/**
	 * Send the request to the WooCommerce Payment API
	 *
	 * @param array  $params           - Request parameters to send as either JSON or GET string. Defaults to test_mode=1 if either in dev or test mode, 0 otherwise.
	 * @param string $api              - The API endpoint to call.
	 * @param string $method           - The HTTP method to make the request with.
	 * @param bool   $is_site_specific - If true, the site ID will be included in the request url. Defaults to true.
	 * @param bool   $use_user_token   - If true, the request will be signed with the user token rather than blog token. Defaults to false.
	 * @param bool   $raw_response     - If true, the raw response will be returned. Defaults to false.
	 *
	 * @return array
	 * @throws API_Exception - If the account ID hasn't been set.
	 */
	protected function request( $params, $api, $method, $is_site_specific = true, $use_user_token = false, bool $raw_response = false ) {
		// Apply the default params that can be overridden by the calling method.
		$params = wp_parse_args(
			$params,
			[
				'test_mode' => WC_Payments::mode()->is_test(),
			]
		);

		$params = apply_filters( 'wcpay_api_request_params', $params, $api, $method );

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

		$env                    = [];
		$env['WP_User']         = is_user_logged_in() ? wp_get_current_user()->user_login : 'Guest (non logged-in user)';
		$env['HTTP_REFERER']    = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '--' ) );
		$env['HTTP_USER_AGENT'] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '--' ) );
		$env['REQUEST_URI']     = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '--' ) );
		$env['DOING_AJAX']      = defined( 'DOING_AJAX' ) && DOING_AJAX;
		$env['DOING_CRON']      = defined( 'DOING_CRON' ) && DOING_CRON;
		$env['WP_CLI']          = defined( 'WP_CLI' ) && WP_CLI;
		Logger::log(
			'ENVIRONMENT: '
			. var_export( $env, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		);

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

		$headers        = apply_filters( 'wcpay_api_request_headers', $headers );
		$stop_trying_at = time() + self::API_TIMEOUT_SECONDS;
		$retries        = 0;
		$retries_limit  = array_key_exists( 'Idempotency-Key', $headers ) ? self::API_RETRIES_LIMIT : 0;

		while ( true ) {
			$response_code  = null;
			$last_exception = null;

			try {
				$response = $this->http_client->remote_request(
					[
						'url'             => $url,
						'method'          => $method,
						'headers'         => $headers,
						'timeout'         => self::API_TIMEOUT_SECONDS,
						'connect_timeout' => self::API_TIMEOUT_SECONDS,
					],
					$body,
					$is_site_specific,
					$use_user_token
				);

				$response      = apply_filters( 'wcpay_api_request_response', $response, $method, $url, $api );
				$response_code = wp_remote_retrieve_response_code( $response );

				$this->check_response_for_errors( $response );
			} catch ( Connection_Exception $e ) {
				$last_exception = $e;
			} catch ( API_Exception $e ) {
				if ( isset( $params['level3'] ) && 'invalid_request_error' === $e->get_error_code() ) {
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
					return $this->request( $params, $api, $method, $is_site_specific, $use_user_token, $raw_response );
				}
				throw $e;
			}

			if ( $response_code || time() >= $stop_trying_at || $retries_limit === $retries ) {
				if ( null !== $last_exception ) {
					throw $last_exception;
				}
				break;
			}

			// Use exponential backoff to not overload backend.
			usleep( self::API_RETRIES_BACKOFF_MSEC * ( 2 ** $retries ) );
			$retries++;
		}

		// @todo We don't always return an array. `extract_response_body` can also return a string. We should standardize this!
		if ( ! $raw_response ) {
			$response_body = $this->extract_response_body( $response );
		} else {
			$response_body = $response;
		}

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
					'product_code'        => 'empty-order',
					'product_description' => 'The order is empty',
					'quantity'            => 1,
					'tax_amount'          => 0,
					'unit_cost'           => 0,
				],
			];
		}

		/**
		 * In case of invalid request errors, level3 data is now removed,
		 * and the request is retried within `request()` instead of here.
		 */
		return $this->request( $params, $api, $method, $is_site_specific );
	}

	/**
	 * From a given response extract the body.
	 *
	 * @param array $response That was given to us by http_client remote_request.
	 *
	 * @return mixed $response_body
	 */
	protected function extract_response_body( $response ) {
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( null === $response_body ) {
			return wp_remote_retrieve_body( $response );
		}

		// Make sure empty metadata serialized on the client as an empty object {} rather than array [].
		if ( isset( $response_body['metadata'] ) && empty( $response_body['metadata'] ) ) {
			$response_body['metadata'] = new stdClass();
		}

		return $response_body;
	}

	/**
	 * Checks if a response has any errors and throws the appropriate API_Exception.
	 *
	 * @param array $response That was given to us by http_client remote_request.
	 *
	 * @return void
	 *
	 * @throws API_Exception If there's something wrong with the response.
	 */
	protected function check_response_for_errors( $response ) {
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( ! $response_code ) {
			$response_code = 0;
		}

		$response_body_json = wp_remote_retrieve_body( $response );
		$response_body      = json_decode( $response_body_json, true );
		if ( null === $response_body && $this->is_json_response( $response ) ) {
			$message = __( 'Unable to decode response from WooCommerce Payments API', 'woocommerce-payments' );
			Logger::error( $message );
			throw new API_Exception(
				$message,
				'wcpay_unparseable_or_null_body',
				$response_code
			);
		} elseif ( null === $response_body && ! $this->is_json_response( $response ) ) {
			$response_body = wp_remote_retrieve_body( $response );
		}

		// Check error codes for 4xx and 5xx responses.
		if ( 400 <= $response_code ) {
			$error_type   = null;
			$decline_code = null;
			if ( isset( $response_body['code'] ) && 'amount_too_small' === $response_body['code'] ) {
				throw new Amount_Too_Small_Exception(
					$response_body['message'],
					$response_body['data']['minimum_amount'],
					$response_body['data']['currency'],
					$response_code
				);
			} elseif ( isset( $response_body['error'] ) ) {
				$decline_code = $response_body['error']['decline_code'] ?? '';
				$this->maybe_act_on_fraud_prevention( $decline_code );

				$error_code    = $response_body['error']['code'] ?? $response_body['error']['type'] ?? null;
				$error_message = $response_body['error']['message'] ?? null;
				$error_type    = $response_body['error']['type'] ?? null;
			} elseif ( isset( $response_body['code'] ) ) {
				$this->maybe_act_on_fraud_prevention( $response_body['code'] );

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
			throw new API_Exception( $message, $error_code, $response_code, $error_type, $decline_code );
		}
	}

	/**
	 * If error code indicates fraudulent activity, trigger fraud prevention measures.
	 *
	 * @param string $error_code Error code.
	 *
	 * @return void
	 */
	private function maybe_act_on_fraud_prevention( string $error_code ) {
		// Might be flagged by Stripe Radar or WCPay card testing prevention services.
		$is_fraudulent = 'fraudulent' === $error_code || 'wcpay_card_testing_prevention' === $error_code;
		if ( $is_fraudulent && WC()->session ) {
			$fraud_prevention_service = Fraud_Prevention_Service::get_instance();
			if ( $fraud_prevention_service->is_enabled() ) {
				$fraud_prevention_service->regenerate_token();
				// Here we tried triggering checkout refresh, but it clashes with AJAX handling.
			}
		}
	}

	/**
	 * Returns true if the response is JSON, based on the content-type header.
	 *
	 * @param array $response That was given to us by http_client remote_request.
	 *
	 * @return bool True if content-type is application/json, false otherwise.
	 */
	protected function is_json_response( $response ) {
		return 'application/json' === substr( wp_remote_retrieve_header( $response, 'content-type' ), 0, strlen( 'application/json' ) );
	}

	/**
	 * Adds additional info to charge object.
	 *
	 * @param array $charge - Charge object.
	 *
	 * @return array
	 */
	public function add_additional_info_to_charge( array $charge ) : array {
		$charge = $this->add_order_info_to_charge_object( $charge['id'], $charge );
		$charge = $this->add_formatted_address_to_charge_object( $charge );

		return $charge;
	}

	/**
	 * Adds the formatted address to the Charge object
	 *
	 * @param array $charge - Charge object.
	 *
	 * @return array
	 */
	public function add_formatted_address_to_charge_object( array $charge ) : array {
		$has_billing_details = isset( $charge['billing_details'] );

		if ( $has_billing_details ) {
			$raw_details     = $charge['billing_details']['address'];
			$billing_details = [];

			$billing_details['city']      = ( ! empty( $raw_details['city'] ) ) ? $raw_details['city'] : '';
			$billing_details['country']   = ( ! empty( $raw_details['country'] ) ) ? $raw_details['country'] : '';
			$billing_details['address_1'] = ( ! empty( $raw_details['line1'] ) ) ? $raw_details['line1'] : '';
			$billing_details['address_2'] = ( ! empty( $raw_details['line2'] ) ) ? $raw_details['line2'] : '';
			$billing_details['postcode']  = ( ! empty( $raw_details['postal_code'] ) ) ? $raw_details['postal_code'] : '';
			$billing_details['state']     = ( ! empty( $raw_details['state'] ) ) ? $raw_details['state'] : '';

			$charge['billing_details']['formatted_address'] = WC()->countries->get_formatted_address( $billing_details );
		}

		return $charge;
	}

	/**
	 * Adds additional info to intention object.
	 *
	 * @param string $intention_id Intention ID.
	 *
	 * @return array
	 */
	private function get_order_info_from_intention_object( $intention_id ) {
		$order  = $this->wcpay_db->order_from_intent_id( $intention_id );
		$object = $this->add_order_info_to_object( $order, [] );

		return $object['order'];
	}

	/**
	 * Adds order information to the charge object.
	 *
	 * @param string $charge_id Charge ID.
	 * @param array  $object    Object to add order information.
	 *
	 * @return array
	 */
	private function add_order_info_to_charge_object( $charge_id, $object ) {
		$order  = $this->wcpay_db->order_from_charge_id( $charge_id );
		$object = $this->add_order_info_to_object( $order, $object );

		return $object;
	}

	/**
	 * Returns a transaction with order information when it exists.
	 *
	 * @param  bool|\WC_Order|\WC_Order_Refund $order  Order object.
	 * @param  array                           $object Object to add order information.
	 *
	 * @return array new object with order information.
	 */
	private function add_order_info_to_object( $order, $object ) {
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
	public function build_order_info( WC_Order $order ): array {
		$order_info = [
			'number'              => $order->get_order_number(),
			'url'                 => $order->get_edit_order_url(),
			'customer_url'        => $this->get_customer_url( $order ),
			'fraud_meta_box_type' => $order->get_meta( '_wcpay_fraud_meta_box_type' ),
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

		$charge_array = $this->add_additional_info_to_charge( $charge_array );

		$charge = new WC_Payments_API_Charge(
			$charge_array['id'],
			$charge_array['amount'],
			$created,
			$charge_array['payment_method_details'] ?? null,
			$charge_array['payment_method'] ?? null,
			$charge_array['amount_captured'] ?? null,
			$charge_array['amount_refunded'] ?? null,
			$charge_array['application_fee_amount'] ?? null,
			$charge_array['balance_transaction'] ?? null,
			$charge_array['billing_details'] ?? null,
			$charge_array['currency'] ?? null,
			$charge_array['dispute'] ?? null,
			$charge_array['disputed'] ?? null,
			$charge_array['order'] ?? null,
			$charge_array['outcome'] ?? null,
			$charge_array['paid'] ?? null,
			$charge_array['paydown'] ?? null,
			$charge_array['payment_intent'] ?? null,
			$charge_array['refunded'] ?? null,
			$charge_array['refunds'] ?? null,
			$charge_array['status'] ?? null
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
	public function deserialize_intention_object_from_array( array $intention_array ) {
		// TODO: Throw an exception if the response array doesn't contain mandatory properties.
		$created = new DateTime();
		$created->setTimestamp( $intention_array['created'] );

		$charge_array         = 0 < $intention_array['charges']['total_count'] ? end( $intention_array['charges']['data'] ) : null;
		$next_action          = ! empty( $intention_array['next_action'] ) ? $intention_array['next_action'] : [];
		$last_payment_error   = ! empty( $intention_array['last_payment_error'] ) ? $intention_array['last_payment_error'] : [];
		$metadata             = ! empty( $intention_array['metadata'] ) ? $intention_array['metadata'] : [];
		$customer             = $intention_array['customer'] ?? $charge_array['customer'] ?? null;
		$payment_method       = $intention_array['payment_method'] ?? $intention_array['source'] ?? null;
		$processing           = $intention_array[ Payment_Intent_Status::PROCESSING ] ?? [];
		$payment_method_types = $intention_array['payment_method_types'] ?? [];

		$charge = ! empty( $charge_array ) ? self::deserialize_charge_object_from_array( $charge_array ) : null;
		$order  = $this->get_order_info_from_intention_object( $intention_array['id'] );

		$intent = new WC_Payments_API_Intention(
			$intention_array['id'],
			$intention_array['amount'],
			$intention_array['currency'],
			$customer,
			$payment_method,
			$created,
			$intention_array['status'],
			$intention_array['client_secret'],
			$charge,
			$next_action,
			$last_payment_error,
			$metadata,
			$processing,
			$payment_method_types,
			$order
		);

		return $intent;
	}

	/**
	 * Returns a formatted intention description.
	 *
	 * @param  string $order_number The order number (might be different from the ID).
	 * @return string               A formatted intention description.
	 */
	private function get_intent_description( $order_number ): string {
		$domain_name = str_replace( [ 'https://', 'http://' ], '', get_site_url() );
		$blog_id     = $this->get_blog_id();

		// Forgo i18n as this is only visible in the Stripe dashboard.
		return sprintf(
			'Online Payment%s for %s%s',
			0 !== $order_number ? " for Order #$order_number" : '',
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

	/**
	 * Fetches from the server the minimum amount that can be processed in recurring transactions for a given currency.
	 *
	 * @param string $currency The currency code.
	 *
	 * @return int The minimum amount that can be processed in cents (with no decimals).
	 *
	 * @throws API_Exception If an error occurs.
	 */
	public function get_currency_minimum_recurring_amount( $currency ) {
		return (int) $this->request(
			[],
			self::MINIMUM_RECURRING_AMOUNT_API . '/' . $currency,
			self::GET
		);
	}

	/**
	 * Fetch the summary of the currently active Capital loan.
	 *
	 * @return array summary object.
	 *
	 * @throws API_Exception If an error occurs.
	 */
	public function get_active_loan_summary() : array {
		return $this->request( [], self::CAPITAL_API . '/active_loan_summary', self::GET );
	}

	/**
	 * Fetch the past and present Capital loans.
	 *
	 * @return array List of capital loans.
	 *
	 * @throws API_Exception If an error occurs.
	 */
	public function get_loans() : array {
		return $this->request( [], self::CAPITAL_API . '/loans', self::GET );
	}

	/**
	 * Returns a list of fingerprinting metadata to attach to order.
	 *
	 * @param string $fingerprint User fingerprint.
	 *
	 * @return array List of fingerprinting metadata.
	 *
	 * @throws API_Exception If an error occurs.
	 */
	private function get_fingerprint_metadata( $fingerprint = '' ): array {
		$customer_fingerprint_metadata                                    = Buyer_Fingerprinting_Service::get_instance()->get_hashed_data_for_customer( $fingerprint );
		$customer_fingerprint_metadata['fraud_prevention_data_available'] = true;

		return $customer_fingerprint_metadata;
	}

	/**
	 * Return summary for authorizations.
	 *
	 * @return array     The authorizations summary.
	 * @throws API_Exception Exception thrown on request failure.
	 */
	public function get_authorizations_summary() {
		return $this->request( [], self::AUTHORIZATIONS_API . '/summary', self::GET );
	}

	/**
	 * Fetch a single authorizations with provided payment intent id.
	 *
	 * @param string $payment_intent_id id of requested transaction.
	 * @return array authorization object.
	 */
	public function get_authorization( string $payment_intent_id ) {
		return $this->request( [], self::AUTHORIZATIONS_API . '/' . $payment_intent_id, self::GET );
	}
}
