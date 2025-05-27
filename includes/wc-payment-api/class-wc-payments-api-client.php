<?php
/**
 * WC_Payments_API_Client class
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

use WCPay\Constants\Intent_Status;
use WCPay\Exceptions\API_Exception;
use WCPay\Exceptions\API_Merchant_Exception;
use WCPay\Exceptions\Amount_Too_Small_Exception;
use WCPay\Exceptions\Amount_Too_Large_Exception;
use WCPay\Exceptions\Connection_Exception;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\Fraud_Prevention\Buyer_Fingerprinting_Service;
use WCPay\Logger;
use Automattic\WooCommerce\Admin\API\Reports\Customers\DataStore;
use WCPay\Constants\Currency_Code;
use WCPay\Database_Cache;
use WCPay\Core\Server\Request;
use WCPay\Core\Server\Request\List_Fraud_Outcome_Transactions;
use WCPay\Exceptions\Cannot_Combine_Currencies_Exception;
use WCPay\MultiCurrency\Interfaces\MultiCurrencyApiClientInterface;

/**
 * Communicates with WooCommerce Payments API.
 */
class WC_Payments_API_Client implements MultiCurrencyApiClientInterface {

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
	const WOOPAY_ACCOUNTS_API          = 'accounts/platform_checkout';
	const WOOPAY_COMPATIBILITY_API     = 'woopay/compatibility';
	const DOMAIN_REGISTRATION_API      = 'payment_method_domains';
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
	const PAYMENT_PROCESS_CONFIG_API   = 'payment_process_config';
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
	const FRAUD_SERVICES_API           = 'accounts/fraud_services';
	const FRAUD_OUTCOMES_API           = 'fraud_outcomes';
	const FRAUD_RULESET_API            = 'fraud_ruleset';
	const COMPATIBILITY_API            = 'compatibility';
	const REPORTING_API                = 'reporting/payment_activity';
	const RECOMMENDED_PAYMENT_METHODS  = 'payment_methods/recommended';

	/**
	 * Common keys in API requests/responses that we might want to redact.
	 */
	const API_KEYS_TO_REDACT = [
		'client_secret',
		'email',
		'name',
		'first_name',
		'last_name',
		'phone',
		'company',
		'address_1',
		'address_2',
		'line1',
		'line2',
		'postal_code',
		'postcode',
		'state',
		'city',
		'country',
		'company',
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
	public function is_server_connected(): bool {
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
	 * @return WC_Payments_API_Payment_Intention intention object.
	 * @throws API_Exception - Exception thrown in case route validation fails.
	 */
	public function get_intent( $intent_id ) {
		if ( ! preg_match( '/^\w+$/', $intent_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

		$intent = $this->request( [], self::INTENTIONS_API . '/' . $intent_id, self::GET );

		return $this->deserialize_payment_intention_object_from_array( $intent );
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
	 * Trigger a manual deposit.
	 *
	 * @param string $type Type of deposit. Only "instant" is supported for now.
	 * @param string $currency The deposit currency.
	 * @return array The new deposit object.
	 * @throws API_Exception - Exception thrown on request failure.
	 */
	public function manual_deposit( $type, $currency ) {
		return $this->request(
			[
				'type'     => $type,
				'currency' => $currency,
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
		// TODO: Refactor this.
		$request->assign_hook( 'wcpay_list_fraud_outcome_transactions_request' );
		$fraud_outcomes = $request->send();

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
		// TODO: Refactor this.
		$request->assign_hook( 'wcpay_list_fraud_outcome_transactions_summary_request' );
		$fraud_outcomes = $request->send();

		$total      = 0;
		$currencies = [];

		foreach ( $fraud_outcomes as $outcome ) {
			$total       += $outcome['amount'];
			$currencies[] = strtolower( $outcome['currency'] );
		}

		return [
			'count'      => is_countable( $fraud_outcomes ) ? count( $fraud_outcomes ) : 0,
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
		// TODO: Refactor this.
		$request->assign_hook( 'wcpay_get_fraud_outcome_transactions_search_autocomplete_request' );
		$fraud_outcomes = $request->send();

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
		// TODO: Refactor this.
		$request->assign_hook( 'wcpay_get_fraud_outcome_transactions_export_request' );
		$fraud_outcomes = $request->send();

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
	 * @param string $locale     Site locale.
	 *
	 * @return array Export summary
	 *
	 * @throws API_Exception - Exception thrown on request failure.
	 */
	public function get_transactions_export( $filters = [], $user_email = '', $deposit_id = null, $locale = null ) {
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
		if ( ! empty( $locale ) ) {
			$filters['locale'] = $locale;
		}

		return $this->request( $filters, self::TRANSACTIONS_API . '/download', self::POST );
	}

	/**
	 * Get the transactions export URL for a given export ID, if available.
	 *
	 * @param string $export_id The export ID.
	 *
	 * @return array The export URL response.
	 * @throws API_Exception - Exception thrown on request failure.
	 */
	public function get_transactions_export_url( string $export_id ): array {
		return $this->request( [], self::TRANSACTIONS_API . "/download/{$export_id}", self::GET );
	}

	/**
	 * Get the disputes export URL for a given export ID, if available.
	 *
	 * @param string $export_id The export ID.
	 *
	 * @return array The export URL response.
	 * @throws API_Exception - Exception thrown on request failure.
	 */
	public function get_disputes_export_url( string $export_id ): array {
		return $this->request( [], self::DISPUTES_API . "/download/{$export_id}", self::GET );
	}

	/**
	 * Get the payouts export URL for a given export ID, if available.
	 *
	 * @param string $export_id The export ID.
	 *
	 * @return array The export URL response.
	 * @throws API_Exception - Exception thrown on request failure.
	 */
	public function get_payouts_export_url( string $export_id ): array {
		return $this->request( [], self::DEPOSITS_API . "/download/{$export_id}", self::GET );
	}

	/**
	 * Fetch account recommended payment methods data for a given country.
	 *
	 * @param string $country_code The account's business location country code. Provide a 2-letter ISO country code.
	 * @param string $locale       Optional. The locale to instruct the platform to use for i18n.
	 *
	 * @return array The recommended payment methods data.
	 * @throws API_Exception Exception thrown on request failure.
	 */
	public function get_recommended_payment_methods( string $country_code, string $locale = '' ): array {
		// We can't use the request method here because this route doesn't require a connected store
		// and we request this data pre-onboarding.
		// By this point, we have an expired transient or the store context has changed.
		// Query for incentives by calling the WooPayments API.
		$url = add_query_arg(
			[
				'country_code' => $country_code,
				'locale'       => $locale,
			],
			self::ENDPOINT_BASE . '/' . self::ENDPOINT_REST_BASE . '/' . self::RECOMMENDED_PAYMENT_METHODS,
		);

		$response = wp_remote_get(
			$url,
			[
				'headers'    => apply_filters(
					'wcpay_api_request_headers',
					[
						'Content-type' => 'application/json; charset=utf-8',
					]
				),
				'user-agent' => $this->user_agent,
				'timeout'    => self::API_TIMEOUT_SECONDS,
				'sslverify'  => false,
			]
		);

		if ( is_wp_error( $response ) ) {
			Logger::error( 'HTTP_REQUEST_ERROR ' . var_export( $response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			$message = sprintf(
			// translators: %1: original error message.
				__( 'Http request failed. Reason: %1$s', 'woocommerce-payments' ),
				$response->get_error_message()
			);
			throw new API_Exception( $message, 'wcpay_http_request_failed', 500 );
		}

		$results = [];
		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			// Decode the results, falling back to an empty array.
			$results = $this->extract_response_body( $response );
			if ( ! is_array( $results ) ) {
				$results = [];
			}
		}

		return $results;
	}

	/**
	 * Fetch a single transaction with provided id.
	 *
	 * @param string $transaction_id id of requested transaction.
	 * @return array transaction object.
	 * @throws API_Exception - Exception thrown in case route validation fails.
	 */
	public function get_transaction( $transaction_id ) {
		if ( ! preg_match( '/^\w+$/', $transaction_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

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
	public function get_disputes_summary( array $filters = [] ): array {
		return $this->request( [ $filters ], self::DISPUTES_API . '/summary', self::GET );
	}

	/**
	 * Fetch disputes by provided query.
	 *
	 * @param array $filters Query to be used to get disputes.
	 * @return array Disputes.
	 * @throws API_Exception - Exception thrown on request failure.
	 */
	public function get_disputes( array $filters = [] ) {
		return $this->request( $filters, self::DISPUTES_API, self::GET );
	}

	/**
	 * Fetch a single dispute with provided id.
	 *
	 * @param string $dispute_id id of requested dispute.
	 * @return array dispute object.
	 * @throws API_Exception - Exception thrown in case route validation fails.
	 */
	public function get_dispute( $dispute_id ) {
		if ( ! preg_match( '/^\w+$/', $dispute_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

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
	 * @throws API_Exception - Exception thrown in case route validation fails.
	 */
	public function update_dispute( $dispute_id, $evidence, $submit, $metadata ) {
		if ( ! preg_match( '/^\w+$/', $dispute_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

		$request = [
			'evidence' => $evidence,
			'submit'   => $submit,
			'metadata' => $metadata,
		];

		$dispute = $this->request( $request, self::DISPUTES_API . '/' . $dispute_id, self::POST );
		// Invalidate the dispute caches.
		\WC_Payments::get_database_cache()->delete( Database_Cache::DISPUTE_STATUS_COUNTS_KEY );
		\WC_Payments::get_database_cache()->delete( Database_Cache::ACTIVE_DISPUTES_KEY );

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
	 *
	 * @throws API_Exception - Exception thrown in case route validation fails.
	 */
	public function close_dispute( $dispute_id ) {
		if ( ! preg_match( '/^\w+$/', $dispute_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

		$dispute = $this->request( [], self::DISPUTES_API . '/' . $dispute_id . '/close', self::POST );
		// Invalidate the dispute caches.
		\WC_Payments::get_database_cache()->delete( Database_Cache::DISPUTE_STATUS_COUNTS_KEY );
		\WC_Payments::get_database_cache()->delete( Database_Cache::ACTIVE_DISPUTES_KEY );

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
	 * @param string $locale Site locale.
	 *
	 * @return array Export summary
	 *
	 * @throws API_Exception - Exception thrown on request failure.
	 */
	public function get_disputes_export( $filters = [], $user_email = '', $locale = null ) {
		if ( ! empty( $user_email ) ) {
			$filters['user_email'] = $user_email;
		}
		if ( ! empty( $locale ) ) {
			$filters['locale'] = $locale;
		}

		return $this->request( $filters, self::DISPUTES_API . '/download', self::POST );
	}

	/**
	 * Initiates deposits export via API.
	 *
	 * @param array  $filters    The filters to be used in the query.
	 * @param string $user_email The email to send export to.
	 * @param string $locale Site locale.
	 *
	 * @return array Export summary
	 *
	 * @throws API_Exception - Exception thrown on request failure.
	 */
	public function get_deposits_export( $filters = [], $user_email = '', $locale = null ) {
		if ( ! empty( $user_email ) ) {
			$filters['user_email'] = $user_email;
		}
		if ( ! empty( $locale ) ) {
			$filters['locale'] = $locale;
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
	public function get_file_contents( string $file_id, bool $as_account = true ): array {
		try {
			if ( ! preg_match( '/^\w+$/', $file_id ) ) {
				throw new API_Exception(
					__( 'Route param validation failed.', 'woocommerce-payments' ),
					'wcpay_route_validation_failure',
					400
				);
			}

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
	public function get_file( string $file_id, bool $as_account = true ): array {
		if ( ! preg_match( '/^\w+$/', $file_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}
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
	 * @throws API_Exception - Exception thrown in case route validation fails.
	 */
	public function get_timeline( $id ) {
		if ( ! preg_match( '/^\w+$/', $id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

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
					function ( $a, $b ) {
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
	public function get_currency_rates( string $currency_from, $currencies_to = null ): array {
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
				'test_mode' => WC_Payments::mode()->is_test_mode_onboarding(), // only send a test mode request if in test mode onboarding.
			],
			self::WOOPAY_ACCOUNTS_API,
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
				[ 'test_mode' => WC_Payments::mode()->is_test_mode_onboarding() ],
				$data
			),
			self::WOOPAY_ACCOUNTS_API,
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
	 * @param bool    $live_account                Whether to get the onboarding data for a live mode or test mode account.
	 * @param string  $return_url                  URL to redirect to at the end of the flow.
	 * @param array   $site_data                   Data to track ToS agreement.
	 * @param array   $user_data                   Data about the user doing the onboarding (location and device).
	 * @param array   $account_data                Data to prefill the onboarding.
	 * @param array   $actioned_notes              Actioned WCPay note names to be sent to the onboarding flow.
	 * @param bool    $progressive                 Whether we need to enable progressive onboarding prefill.
	 * @param bool    $collect_payout_requirements Whether we need to redirect user to Stripe KYC to complete their payouts data.
	 * @param ?string $referral_code              Referral code to be used for onboarding.
	 *
	 * @return array An array containing the url and state fields.
	 * @throws API_Exception Exception thrown on request failure.
	 */
	public function get_onboarding_data(
		bool $live_account,
		string $return_url,
		array $site_data = [],
		array $user_data = [],
		array $account_data = [],
		array $actioned_notes = [],
		bool $progressive = false,
		bool $collect_payout_requirements = false,
		?string $referral_code = null
	): array {
		$request_args = apply_filters(
			'wc_payments_get_onboarding_data_args',
			[
				'return_url'                  => $return_url,
				'site_data'                   => $site_data,
				'user_data'                   => $user_data,
				'account_data'                => $account_data,
				'actioned_notes'              => $actioned_notes,
				'create_live_account'         => $live_account,
				'progressive'                 => $progressive,
				'collect_payout_requirements' => $collect_payout_requirements,
			]
		);

		$request_args['referral_code'] = $referral_code;

		return $this->request( $request_args, self::ONBOARDING_API . '/init', self::POST, true, true );
	}

	/**
	 * Initialize the onboarding embedded KYC flow, returning a session object which is used by the frontend.
	 *
	 * @param bool    $live_account Whether to create live account.
	 * @param array   $site_data Site data.
	 * @param array   $user_data User data.
	 * @param array   $account_data Account data to be prefilled.
	 * @param array   $actioned_notes Actioned notes to be sent.
	 * @param bool    $progressive Whether progressive onboarding should be enabled for this onboarding.
	 * @param ?string $referral_code Referral code to be used for onboarding.
	 *
	 * @return array
	 *
	 * @throws API_Exception
	 */
	public function initialize_onboarding_embedded_kyc(
		bool $live_account,
		array $site_data = [],
		array $user_data = [],
		array $account_data = [],
		array $actioned_notes = [],
		bool $progressive = false,
		?string $referral_code = null
	): array {
		$request_args = apply_filters(
			'wc_payments_get_onboarding_data_args',
			[
				'site_data'           => $site_data,
				'user_data'           => $user_data,
				'account_data'        => $account_data,
				'actioned_notes'      => $actioned_notes,
				'create_live_account' => $live_account,
				'progressive'         => $progressive,
			]
		);

		$request_args['referral_code'] = $referral_code;

		$session = $this->request( $request_args, self::ONBOARDING_API . '/embedded', self::POST, true, true );

		if ( ! is_array( $session ) ) {
			return [];
		}

		return $session;
	}

	/**
	 * Fetch the embedded account session object utilized by the frontend.
	 *
	 * @return array
	 *
	 * @throws API_Exception
	 */
	public function create_embedded_account_session(): array {
		$session = $this->request( [], self::ACCOUNTS_API . '/embedded/session', self::POST, true, true );

		if ( ! is_array( $session ) ) {
			return [];
		}

		return $session;
	}

	/**
	 * Finalize the onboarding embedded KYC flow.
	 *
	 * @param string $locale         The locale to use to i18n the data.
	 * @param string $source         The source of the onboarding flow.
	 * @param array  $actioned_notes The actioned notes on the account related to this onboarding.
	 * @return array
	 *
	 * @throws API_Exception
	 */
	public function finalize_onboarding_embedded_kyc( string $locale, string $source, array $actioned_notes ): array {
		$request_args = [
			'locale'         => $locale,
			'source'         => $source,
			'actioned_notes' => $actioned_notes,
		];

		return $this->request( $request_args, self::ONBOARDING_API . '/embedded/finalize', self::POST, true, true );
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
			throw new API_Exception(
				__( 'Onboarding field data could not be retrieved', 'woocommerce-payments' ),
				'wcpay_onboarding_fields_data_error',
				400
			);
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
	 * Get a link's details from the server.
	 *
	 * @param array $args The arguments to be sent with the link request.
	 *
	 * @return array The link object with an url field.
	 *
	 * @throws API_Exception When something goes wrong with the request, when type is not defined or valid, or the link is not valid.
	 */
	public function get_link( array $args ) {
		if ( ! isset( $args['type'] ) && ! in_array( $args['type'], [ 'login_link', 'complete_kyc_link' ], true ) ) {
			throw new API_Exception(
				__( 'Link type is required', 'woocommerce-payments' ),
				'wcpay_unknown_link_type',
				400
			);
		}
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

		if ( ! preg_match( '/^\w+$/', $customer_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
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
	 * Fetch a product.
	 *
	 * @param string $product_id ID of the product to get.
	 *
	 * @return array The product.
	 *
	 * @throws API_Exception If fetching the product fails.
	 */
	public function get_product_by_id( string $product_id ): array {
		return $this->request(
			[],
			self::PRODUCTS_API . '/' . $product_id,
			self::GET
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
	public function update_product( string $product_id, array $product_data = [] ): array {
		if ( null === $product_id || '' === trim( $product_id ) ) {
			throw new API_Exception(
				__( 'Product ID is required', 'woocommerce-payments' ),
				'wcpay_mandatory_product_id_missing',
				400
			);
		}

		if ( ! preg_match( '/^\w+$/', $product_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
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

		if ( ! preg_match( '/^\w+$/', $price_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
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
		if ( ! preg_match( '/^\w+$/', $invoice_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

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
		if ( ! preg_match( '/^\w+$/', $invoice_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

		return $this->request(
			$data,
			self::INVOICES_API . '/' . $invoice_id . '/pay',
			self::POST
		);
	}

	/**
	 * Updates an invoice.
	 *
	 * @param string $invoice_id ID of the invoice to update.
	 * @param array  $data       Parameters to send to the invoice endpoint. Optional. Default is an empty array.
	 * @return array
	 *
	 * @throws API_Exception Error updating the invoice.
	 */
	public function update_invoice( string $invoice_id, array $data = [] ) {
		if ( ! preg_match( '/^\w+$/', $invoice_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

		return $this->request(
			$data,
			self::INVOICES_API . '/' . $invoice_id,
			self::POST
		);
	}

	/**
	 * Updates a charge.
	 *
	 * @param string $charge_id ID of the charge to update.
	 * @param array  $data arameters to send to the transaction endpoint. Optional. Default is an empty array.
	 *
	 * @return array
	 * @throws API_Exception
	 */
	public function update_charge( string $charge_id, array $data = [] ) {
		if ( ! preg_match( '/^\w+$/', $charge_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

		return $this->request(
			$data,
			self::CHARGES_API . '/' . $charge_id,
			self::POST
		);
	}

	/**
	 * Fetch a charge by id.
	 *
	 * @param string $charge_id Charge id.
	 *
	 * @return array
	 * @throws API_Exception
	 */
	public function get_charge( string $charge_id ) {
		if ( ! preg_match( '/^\w+$/', $charge_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

		return $this->request(
			[],
			self::CHARGES_API . '/' . $charge_id,
			self::GET
		);
	}

	/**
	 * Updates a transaction.
	 *
	 * @param string $transaction_id Transaction id.
	 * @param array  $data Data to be updated.
	 *
	 * @return array
	 * @throws API_Exception
	 */
	public function update_transaction( string $transaction_id, array $data = [] ) {
		if ( ! preg_match( '/^\w+$/', $transaction_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

		return $this->request(
			$data,
			self::TRANSACTIONS_API . '/' . $transaction_id,
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
		if ( ! preg_match( '/^\w+$/', $wcpay_subscription_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

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
		if ( ! preg_match( '/^\w+$/', $wcpay_subscription_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

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
		if ( ! preg_match( '/^\w+$/', $wcpay_subscription_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

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
		if ( ! preg_match( '/^\w+$/', $wcpay_subscription_item_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

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
		if ( ! preg_match( '/^\w+$/', $payment_method_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

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
		if ( ! preg_match( '/^\w+$/', $payment_method_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

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
		if ( ! preg_match( '/^\w+$/', $customer_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

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
		if ( ! preg_match( '/^\w+$/', $payment_method_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

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
	 * Registers a Payment Method Domain.
	 *
	 * @param string $domain_name Domain name which to register for the account.
	 *
	 * @return array An array containing an id and the bool property 'enabled' indicating
	 * whether the domain is enabled for the account. Each Payment Method
	 * (apple_pay, google_pay, link, paypal) in the array have a 'status'
	 * property with the possible values 'active' and 'inactive'.
	 *
	 * @throws API_Exception If an error occurs.
	 */
	public function register_domain( $domain_name ) {
		return $this->request(
			[
				'domain_name' => $domain_name,
				// The value needs to be a string.
				// If it's a boolean, it gets serialized as an integer (1), causing an invalid request error.
				'enabled'     => 'true',
			],
			self::DOMAIN_REGISTRATION_API,
			self::POST
		);
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
	public function register_terminal_reader( string $location, string $registration_code, ?string $label = null, ?array $metadata = null ) {
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
		if ( ! preg_match( '/^\w+$/', $location_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

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
		if ( ! preg_match( '/^\w+$/', $location_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

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
		if ( ! preg_match( '/^[\w-]+$/', $document_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

		return $this->request( [], self::DOCUMENTS_API . '/' . $document_id, self::GET, true, false, true );
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
		if ( ! preg_match( '/^\w+$/', $id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

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
	 * Sends the compatibility data to the server to be saved to the account.
	 *
	 * @param array $compatibility_data The array containing the data.
	 *
	 * @return array HTTP response on success.
	 *
	 * @throws API_Exception - If not connected or request failed.
	 */
	public function update_compatibility_data( $compatibility_data ) {
		$response = $this->request(
			[
				'compatibility_data' => $compatibility_data,
			],
			self::COMPATIBILITY_API,
			self::POST
		);

		return $response;
	}

	/**
	 * Get tracking info for the site.
	 *
	 * @return  array  Tracking info.
	 *
	 * @throws API_Exception - If not connected or request failed.
	 */
	public function get_tracking_info() {
		return $this->request(
			[],
			self::TRACKING_API . '/info',
			self::GET,
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

		$headers        = apply_filters( 'wcpay_api_request_headers', $headers );
		$stop_trying_at = time() + self::API_TIMEOUT_SECONDS;
		$retries        = 0;
		$retries_limit  = array_key_exists( 'Idempotency-Key', $headers ) ? self::API_RETRIES_LIMIT : 0;

		while ( true ) {
			$response_code  = null;
			$last_exception = null;

			// The header intention is to give us insights into request latency between store and backend.
			$headers['X-Request-Initiated'] = microtime( true );

			$request_args = [
				'url'             => $url,
				'method'          => $method,
				'headers'         => $headers,
				'timeout'         => self::API_TIMEOUT_SECONDS,
				'connect_timeout' => self::API_TIMEOUT_SECONDS,
			];

			$log_request_id = uniqid();

			Logger::info(
				sprintf( 'API REQUEST (%s): %s %s', $log_request_id, $method, $redacted_url ),
				[
					'request' => $request_args,
					null !== $body ? [ 'body' => $redacted_params ] : [],
				]
			);

			try {
				$response = $this->http_client->remote_request( $request_args, $body, $is_site_specific, $use_user_token );

				$response      = apply_filters( 'wcpay_api_request_response', $response, $method, $url, $api );
				$response_code = wp_remote_retrieve_response_code( $response );

				$this->check_response_for_errors( $response );
			} catch ( Connection_Exception $e ) {
				$last_exception = $e;
			}

			if ( $response_code || time() >= $stop_trying_at || $retries_limit === $retries ) {
				if ( null !== $last_exception ) {
					throw $last_exception;
				}
				break;
			}

			// Use exponential backoff to not overload backend.
			usleep( self::API_RETRIES_BACKOFF_MSEC * ( 2 ** $retries ) );
			++$retries;
		}

		// @todo We don't always return an array. `extract_response_body` can also return a string. We should standardize this!
		if ( ! $raw_response ) {
			$response_body = $this->extract_response_body( $response );
		} else {
			$response_body = $response;
		}

		Logger::info(
			sprintf( 'API RESPONSE (%s): %s %s', $log_request_id, $method, $redacted_url ),
			[
				'body' => WC_Payments_Utils::redact_array( $response_body, self::API_KEYS_TO_REDACT ),
			]
		);

		return $response_body;
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
				$response_body_error_code = $response_body['error']['code'] ?? $response_body['error']['message_code'] ?? null;
				$payment_intent_status    = $response_body['error']['payment_intent']['status'] ?? null;

				// We redact the API error message to prevent prompting the merchant to contact Stripe support
				// when attempting to manually capture an amount greater than what's authorized. Contacting support is unnecessary in this scenario.
				if ( 'amount_too_large' === $response_body_error_code && Intent_Status::REQUIRES_CAPTURE === $payment_intent_status ) {
					throw new Amount_Too_Large_Exception(
						// translators: This is an error API response.
						__( 'Error: The payment could not be captured because the requested capture amount is greater than the amount you can capture for this charge.', 'woocommerce-payments' ),
						$response_code
					);
				}
				$decline_code = $response_body['error']['decline_code'] ?? '';
				$this->maybe_act_on_fraud_prevention( $decline_code );

				$error_code    = $response_body_error_code ?? $response_body['error']['type'] ?? null;
				$error_message = $response_body['error']['message'] ?? null;
				$error_type    = $response_body['error']['type'] ?? null;
			} elseif ( isset( $response_body['code'] ) ) {
				$this->maybe_act_on_fraud_prevention( $response_body['code'] );

				if (
					'invalid_request_error' === $response_body['code']
					&& 0 === strpos( $response_body['message'], 'You cannot combine currencies on a single customer.' )
				) {
					// Get the currency, which is the last part of the error message,
					// and remove the period from the end of the error message.
					$message  = $response_body['message'];
					$currency = substr( $message, -4 );
					$currency = strtoupper( substr( $currency, 0, 3 ) );

					// Only throw the error if we can find a valid currency.
					if ( false !== Currency_Code::search( $currency ) ) {
						throw new Cannot_Combine_Currencies_Exception(
							$message,
							$currency,
							$response_code
						);
					}
				}

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

			if ( 'card_declined' === $error_code && isset( $response_body['error']['payment_intent']['charges']['data'][0]['outcome']['seller_message'] ) ) {
				$merchant_message = $response_body['error']['payment_intent']['charges']['data'][0]['outcome']['seller_message'];

				throw new API_Merchant_Exception( $message, $error_code, $response_code, $merchant_message, $error_type, $decline_code );
			}

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
	public function add_additional_info_to_charge( array $charge ): array {
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
	public function add_formatted_address_to_charge_object( array $charge ): array {
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
	 * @param array  $entity    Object to add order information.
	 *
	 * @return array
	 */
	private function add_order_info_to_charge_object( $charge_id, $entity ) {
		$order  = $this->wcpay_db->order_from_charge_id( $charge_id );
		$entity = $this->add_order_info_to_object( $order, $entity );

		return $entity;
	}

	/**
	 * Returns a transaction with order information when it exists.
	 *
	 * @param  bool|\WC_Order|\WC_Order_Refund $order  Order object.
	 * @param  array                           $entity Object to add order information.
	 *
	 * @return array new object with order information.
	 */
	private function add_order_info_to_object( $order, $entity ) {
		// Add order information to the `$transaction`.
		// If the order couldn't be retrieved, return an empty order.
		$entity['order'] = [];

		if ( $order ) {
			$entity['order'] = $this->build_order_info( $order );
		}

		return $entity;
	}

	/**
	 * Creates the array representing order for frontend.
	 *
	 * @param WC_Order $order The order.
	 * @return array
	 */
	public function build_order_info( WC_Order $order ): array {
		$order_info = [
			'id'                  => $order->get_id(),
			'number'              => $order->get_order_number(),
			'url'                 => $order->get_edit_order_url(),
			'customer_url'        => $this->get_customer_url( $order ),
			'customer_name'       => trim( $order->get_formatted_billing_full_name() ),
			'customer_email'      => $order->get_billing_email(),
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
	 * De-serialize an intention array into a payment intention object
	 *
	 * @param array $intention_array - The intention array to de-serialize.
	 *
	 * @return WC_Payments_API_Payment_Intention
	 * @throws API_Exception - Unable to deserialize intention array.
	 */
	public function deserialize_payment_intention_object_from_array( array $intention_array ) {
		// TODO: Throw an exception if the response array doesn't contain mandatory properties.
		$created = new DateTime();
		$created->setTimestamp( $intention_array['created'] );

		// Metadata can be an empty stdClass object, so we need to check array type too.
		// See https://github.com/Automattic/woocommerce-payments/pull/419/commits/c2c8438c3ed7be6d604435e059209fb87fb6d0c4.
		$raw_metadata           = $intention_array['metadata'];
		$metadata               = is_array( $raw_metadata ) && ! empty( $raw_metadata )
			? $raw_metadata
			: [];
		$charge_array           = 0 < $intention_array['charges']['total_count'] ? end( $intention_array['charges']['data'] ) : null;
		$next_action            = $intention_array['next_action'] ?? [];
		$last_payment_error     = $intention_array['last_payment_error'] ?? [];
		$customer               = $intention_array['customer'] ?? $charge_array['customer'] ?? null;
		$payment_method         = $intention_array['payment_method'] ?? $intention_array['source'] ?? null;
		$processing             = $intention_array[ Intent_Status::PROCESSING ] ?? [];
		$payment_method_types   = $intention_array['payment_method_types'] ?? [];
		$payment_method_options = $intention_array['payment_method_options'] ?? [];

		$charge = ! empty( $charge_array ) ? self::deserialize_charge_object_from_array( $charge_array ) : null;
		$order  = $this->get_order_info_from_intention_object( $intention_array['id'] );

		$intent = new WC_Payments_API_Payment_Intention(
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
			$payment_method_options,
			$order
		);

		return $intent;
	}

	/**
	 * De-serialize an intention array into a setup intention object
	 *
	 * @param array $intention_array - The intention array to de-serialize.
	 *
	 * @return WC_Payments_API_Setup_Intention
	 * @throws API_Exception - Unable to deserialize intention array.
	 */
	public function deserialize_setup_intention_object_from_array( array $intention_array ): WC_Payments_API_Setup_Intention {
		$created = new DateTime();
		$created->setTimestamp( $intention_array['created'] );

		// Metadata can be an empty stdClass object, so we need to check array type too.
		// See https://github.com/Automattic/woocommerce-payments/pull/419/commits/c2c8438c3ed7be6d604435e059209fb87fb6d0c4.
		$raw_metadata           = $intention_array['metadata'];
		$metadata               = is_array( $raw_metadata ) && ! empty( $raw_metadata )
			? $raw_metadata
			: [];
		$next_action            = $intention_array['next_action'] ?? [];
		$last_setup_error       = $intention_array['last_setup_error'] ?? [];
		$customer               = $intention_array['customer'] ?? null;
		$payment_method         = $intention_array['payment_method'] ?? $intention_array['source'] ?? null;
		$payment_method_types   = $intention_array['payment_method_types'] ?? [];
		$payment_method_options = $intention_array['payment_method_options'] ?? [];

		$order = $this->get_order_info_from_intention_object( $intention_array['id'] );

		$intent = new WC_Payments_API_Setup_Intention(
			$intention_array['id'],
			$customer,
			$payment_method,
			$created,
			$intention_array['status'],
			$intention_array['client_secret'],
			$next_action,
			$last_setup_error,
			$metadata,
			$payment_method_types,
			$payment_method_options,
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
	 * @param string      $charge_date    Charge date for readers.
	 * @param string|null $transaction_id Optional transaction ID to filter results.
	 *
	 * @return array reader objects.
	 */
	public function get_readers_charge_summary( string $charge_date, ?string $transaction_id = null ): array {
		$params = [ 'charge_date' => $charge_date ];
		if ( $transaction_id ) {
			$params['transaction_id'] = $transaction_id;
		}
		return $this->request( $params, self::READERS_CHARGE_SUMMARY, self::GET );
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
		if ( ! preg_match( '/^\w+$/', $currency ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

		return (int) $this->request(
			[],
			self::MINIMUM_RECURRING_AMOUNT_API . '/' . $currency,
			self::GET
		);
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
	 * @throws API_Exception - Exception thrown in case route validation fails.
	 */
	public function get_authorization( string $payment_intent_id ) {
		if ( ! preg_match( '/^\w+$/', $payment_intent_id ) ) {
			throw new API_Exception(
				__( 'Route param validation failed.', 'woocommerce-payments' ),
				'wcpay_route_validation_failure',
				400
			);
		}

		return $this->request( [], self::AUTHORIZATIONS_API . '/' . $payment_intent_id, self::GET );
	}

	/**
	 * Gets the WooPay compatibility list.
	 *
	 * @return array of incompatible extensions, adapted extensions and available countries.
	 * @throws API_Exception When request fails.
	 */
	public function get_woopay_compatibility() {
		return $this->request(
			[],
			self::WOOPAY_COMPATIBILITY_API,
			self::GET,
			false
		);
	}

	/**
	 * Delete account.
	 *
	 * @param bool $test_mode Whether we are in test mode or not.
	 *
	 * @return array
	 * @throws API_Exception
	 */
	public function delete_account( bool $test_mode = false ) {
		return $this->request(
			[
				'test_mode' => $test_mode,
			],
			self::ACCOUNTS_API . '/delete',
			self::POST,
			true,
			true
		);
	}
}
