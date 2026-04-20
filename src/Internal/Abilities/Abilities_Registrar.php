<?php
/**
 * Class Abilities_Registrar
 *
 * @package WooCommerce\Payments
 */

// @phan-file-suppress PhanUndeclaredFunction, PhanUndeclaredClassMethod @phan-suppress-current-line UnusedSuppression -- Abilities API added in WP 6.9, but then we need a suppression for the WP 6.8 compat run. @todo Remove this line when we drop WP <6.9.

namespace WCPay\Internal\Abilities;

/**
 * Registers WooPayments abilities with the WordPress Abilities API.
 *
 * Concrete abilities are registered in follow-up phases; this scaffold only
 * wires the registration hooks and declares the `woopayments` category so
 * Phase 2 can ship independently.
 */
class Abilities_Registrar {

	/**
	 * The category slug used for every WooPayments ability.
	 *
	 * @var string
	 */
	const CATEGORY_SLUG = 'woopayments';

	/**
	 * Initialize the abilities registration.
	 *
	 * Mirrors the pattern used by Jetpack Forms: if the relevant Abilities API
	 * action has already fired we call the registrar directly, otherwise we
	 * hook it for when the API boots.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Register category.
		if ( did_action( 'wp_abilities_api_categories_init' ) ) {
			self::register_category();
		} else {
			add_action( 'wp_abilities_api_categories_init', [ __CLASS__, 'register_category' ] );
		}

		// Register abilities.
		if ( did_action( 'wp_abilities_api_init' ) ) {
			self::register_abilities();
		} else {
			add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
		}
	}

	/**
	 * Register the WooPayments ability category.
	 *
	 * @return void
	 */
	public static function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY_SLUG,
			[
				// "WooPayments" is a product name and should not be translated.
				'label'       => 'WooPayments',
				'description' => __( 'Abilities for managing WooPayments transactions, disputes, payouts, and account status.', 'woocommerce-payments' ),
			]
		);
	}

	/**
	 * Register all WooPayments abilities.
	 *
	 * Concrete abilities are registered in subsequent phases.
	 *
	 * @return void
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		self::register_get_account_status_ability();
		self::register_get_transactions_ability();
		self::register_get_disputes_ability();
		self::register_get_dispute_detail_ability();
		self::register_get_payouts_ability();
		self::register_get_payout_overview_ability();
		self::register_submit_dispute_evidence_ability();
	}

	/**
	 * Permission callback mirroring WC_Payments_REST_Controller::check_permission().
	 *
	 * Used as the `permission_callback` for every WooPayments ability so the
	 * authorization surface matches the existing admin REST API.
	 *
	 * @return bool
	 */
	public static function can_manage_payments(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Execute callback for woopayments/get-account-status.
	 *
	 * Delegates to WC_Payments_Account::get_cached_account_data(). Usually
	 * served from cache; on cache miss this may issue a remote request to
	 * the WooPayments server, so agents should not assume this ability is
	 * free to call repeatedly.
	 *
	 * @param mixed $input Optional; ability input. Unused for this ability (empty input_schema) but accepted to match the Abilities API execute_callback signature.
	 * @return array|\WP_Error Array of account data, or WP_Error when WooPayments
	 *                         has not finished initializing or the cached data
	 *                         is unavailable due to a remote error.
	 */
	public static function execute_get_account_status( $input = null ) {
		if ( ! class_exists( '\WC_Payments' ) ) {
			return new \WP_Error(
				'woopayments_not_initialized',
				__( 'WooPayments is not initialized.', 'woocommerce-payments' )
			);
		}

		$account = \WC_Payments::get_account_service();
		if ( ! $account ) {
			return new \WP_Error(
				'woopayments_not_initialized',
				__( 'WooPayments is not initialized.', 'woocommerce-payments' )
			);
		}

		$data = $account->get_cached_account_data();

		if ( false === $data ) {
			return new \WP_Error(
				'woopayments_account_data_unavailable',
				__( 'Unable to retrieve WooPayments account data. The cache may be stale or the remote service returned an error.', 'woocommerce-payments' )
			);
		}

		return is_array( $data ) ? $data : [];
	}

	/**
	 * Execute callback for woopayments/get-transactions.
	 *
	 * Delegates to WC_REST_Payments_Reports_Transactions_Controller::get_transactions()
	 * which produces the normalized, report-shaped transaction schema
	 * (transaction_id, date, payment_id, channel, payment_method, type,
	 * currency, amount, fees, customer, deposit status, etc.).
	 *
	 * @param mixed $input Optional; ability input matching the input_schema.
	 * @return array|\WP_Error Array of prepared transactions, or WP_Error when
	 *                         WooPayments is not initialized or the remote
	 *                         request fails.
	 */
	public static function execute_get_transactions( $input = null ) {
		$date_error = self::validate_iso8601_dates( $input, [ 'date_before', 'date_after' ] );
		if ( null !== $date_error ) {
			return $date_error;
		}

		return self::delegate_to_rest_controller(
			'WC_REST_Payments_Reports_Transactions_Controller',
			'get_transactions',
			'/wc/v3/payments/reports/transactions',
			is_array( $input ) ? $input : null
		);
	}

	/**
	 * Execute callback for woopayments/get-disputes.
	 *
	 * Delegates to WC_REST_Payments_Disputes_Controller::get_disputes() which
	 * returns the raw server response array from the WooPayments API. Critical
	 * for the canonical "which disputes need a response?" agent query via
	 * the status_is filter.
	 *
	 * @param mixed $input Optional; ability input matching the input_schema.
	 * @return array|\WP_Error Disputes list payload from the server, or WP_Error
	 *                         when WooPayments is not initialized or the remote
	 *                         request fails.
	 */
	public static function execute_get_disputes( $input = null ) {
		$date_error = self::validate_iso8601_dates( $input, [ 'date_before', 'date_after' ] );
		if ( null !== $date_error ) {
			return $date_error;
		}

		$input = self::translate_pagination_keys( is_array( $input ) ? $input : null );

		return self::delegate_to_rest_controller(
			'WC_REST_Payments_Disputes_Controller',
			'get_disputes',
			'/wc/v3/payments/disputes',
			$input
		);
	}

	/**
	 * Execute callback for woopayments/get-dispute-detail.
	 *
	 * Delegates to WC_REST_Payments_Disputes_Controller::get_dispute() which
	 * fetches a single dispute by ID via the WooPayments server. The natural
	 * prerequisite for submit-dispute-evidence so an agent can read the
	 * dispute reason, due-by date, and current status before responding.
	 *
	 * @param mixed $input Ability input; must include `dispute_id`.
	 * @return array|\WP_Error Dispute payload from the server, or WP_Error when
	 *                         WooPayments is not initialized, the dispute_id
	 *                         is missing, or the remote request fails.
	 */
	public static function execute_get_dispute_detail( $input = null ) {
		if ( ! is_array( $input ) || ! isset( $input['dispute_id'] ) || ! is_string( $input['dispute_id'] ) || '' === $input['dispute_id'] ) {
			return new \WP_Error(
				'woopayments_missing_dispute_id',
				__( 'A dispute_id is required to fetch dispute detail.', 'woocommerce-payments' )
			);
		}

		return self::delegate_to_rest_controller(
			'WC_REST_Payments_Disputes_Controller',
			'get_dispute',
			'/wc/v3/payments/disputes/' . rawurlencode( (string) $input['dispute_id'] ),
			$input
		);
	}

	/**
	 * Execute callback for woopayments/submit-dispute-evidence.
	 *
	 * Delegates to WC_REST_Payments_Disputes_Controller::update_dispute(),
	 * which accepts `dispute_id`, `evidence`, `submit`, and `metadata` and
	 * forwards them to the WooPayments server. Defaults `submit` to false on
	 * the input schema so evidence can be staged as a draft; agents should
	 * only pass `submit: true` after explicit merchant confirmation because
	 * once submitted, evidence cannot be retracted.
	 *
	 * Not idempotent — calling this twice with `submit: true` produces two
	 * submission attempts against Stripe. Agent retry logic must guard
	 * against double-calls.
	 *
	 * @param mixed $input Ability input; must include `dispute_id`.
	 * @return array|\WP_Error Dispute payload from the server, or WP_Error when
	 *                         WooPayments is not initialized, the dispute_id
	 *                         is missing, or the remote request fails.
	 */
	public static function execute_submit_dispute_evidence( $input = null ) {
		if ( ! is_array( $input ) ) {
			$input = [];
		}

		if ( ! isset( $input['dispute_id'] ) || ! is_string( $input['dispute_id'] ) || '' === $input['dispute_id'] ) {
			return new \WP_Error(
				'woopayments_missing_dispute_id',
				__( 'A dispute_id is required to submit dispute evidence.', 'woocommerce-payments' )
			);
		}

		return self::delegate_to_rest_controller(
			'WC_REST_Payments_Disputes_Controller',
			'update_dispute',
			'/wc/v3/payments/disputes/' . rawurlencode( $input['dispute_id'] ),
			$input,
			'POST'
		);
	}

	/**
	 * Execute callback for woopayments/get-payouts.
	 *
	 * Delegates to WC_REST_Payments_Deposits_Controller::get_deposits(). The
	 * REST endpoint is `deposits`; the user-facing term everywhere else in
	 * WooPayments is `payouts`, which is why the ability keeps the payouts
	 * name while wrapping the deposits controller.
	 *
	 * @param mixed $input Optional; ability input matching the input_schema.
	 * @return array|\WP_Error Payouts list payload from the server, or WP_Error
	 *                         when WooPayments is not initialized or the remote
	 *                         request fails.
	 */
	public static function execute_get_payouts( $input = null ) {
		$date_error = self::validate_iso8601_dates( $input, [ 'date_before', 'date_after' ] );
		if ( null !== $date_error ) {
			return $date_error;
		}

		$input = self::translate_pagination_keys( is_array( $input ) ? $input : null );

		return self::delegate_to_rest_controller(
			'WC_REST_Payments_Deposits_Controller',
			'get_deposits',
			'/wc/v3/payments/deposits',
			$input
		);
	}

	/**
	 * Execute callback for woopayments/get-payout-overview.
	 *
	 * Delegates to WC_REST_Payments_Deposits_Controller::get_all_deposits_overviews().
	 * Answers the canonical "when do I get paid?" agent question by returning
	 * the next scheduled payout across all enabled currencies. Zero-input — we
	 * accept $input for API-signature compatibility but ignore it.
	 *
	 * @param mixed $input Optional; unused for this ability (empty input_schema).
	 * @return array|\WP_Error Overview payload from the server, or WP_Error when
	 *                         WooPayments is not initialized or the remote
	 *                         request fails.
	 */
	public static function execute_get_payout_overview( $input = null ) {
		if ( ! class_exists( '\WC_REST_Payments_Deposits_Controller' ) ) {
			return new \WP_Error(
				'woopayments_not_initialized',
				__( 'WooPayments is not initialized.', 'woocommerce-payments' )
			);
		}

		$controller = new \WC_REST_Payments_Deposits_Controller();
		$response   = $controller->get_all_deposits_overviews();

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return is_array( $response ) ? $response : [];
	}

	/**
	 * Delegate an ability's execute callback to a WooPayments REST controller.
	 *
	 * Builds a WP_REST_Request from the ability's input, instantiates the
	 * backing controller, invokes the named method, and normalizes the return
	 * so the caller always sees an array|\WP_Error. Controllers in this plugin
	 * return either a WP_REST_Response (e.g. the reports endpoints) or a raw
	 * array from the server's transport layer (e.g. List_Disputes), so the
	 * helper unwraps both shapes.
	 *
	 * Not used by zero-arg abilities — those call their controller directly so
	 * we don't invent a synthetic WP_REST_Request just to discard it.
	 *
	 * @param string     $controller_class Fully-qualified controller class name (no leading backslash).
	 * @param string     $method           Controller method to invoke on the built request.
	 * @param string     $route            REST route string used when constructing WP_REST_Request.
	 * @param array|null $input            Ability input; each key/value becomes a request param.
	 * @param string     $http_method      HTTP method for the WP_REST_Request. Defaults to 'GET'; Phase 5's write ability will pass 'POST'.
	 * @return array|\WP_Error             Response payload as an array, or WP_Error on failure.
	 */
	private static function delegate_to_rest_controller(
		string $controller_class,
		string $method,
		string $route,
		?array $input,
		string $http_method = 'GET'
	) {
		$fqcn = '\\' . $controller_class;
		if ( ! class_exists( $fqcn ) ) {
			return new \WP_Error(
				'woopayments_not_initialized',
				__( 'WooPayments is not initialized.', 'woocommerce-payments' )
			);
		}

		$request = new \WP_REST_Request( $http_method, $route );
		if ( null !== $input ) {
			foreach ( $input as $param => $value ) {
				$request->set_param( $param, $value );
			}
		}

		$controller = new $fqcn();
		$response   = $controller->{$method}( $request );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response instanceof \WP_REST_Response ) {
			$data = $response->get_data();
			return is_array( $data ) ? $data : [];
		}

		return is_array( $response ) ? $response : [];
	}

	/**
	 * Translate pagination keys for abilities that back Paginated::from_rest_request —
	 * the shared WooPayments paginated request class reads `pagesize`, not `per_page`.
	 * Abilities expose `per_page` for consistency; this helper bridges the two.
	 *
	 * @param array|null $input Ability input (or null).
	 * @return array|null       Input with `per_page` rewritten to `pagesize` when present.
	 */
	private static function translate_pagination_keys( $input ) {
		if ( ! is_array( $input ) ) {
			return $input;
		}
		if ( isset( $input['per_page'] ) ) {
			$input['pagesize'] = $input['per_page'];
			unset( $input['per_page'] );
		}
		return $input;
	}

	/**
	 * Validate optional ISO 8601 date-time strings in ability input.
	 *
	 * Returns a WP_Error on malformed input, or null if all dates are valid / absent.
	 *
	 * @param mixed    $input Ability input; non-array input is treated as having no dates to validate.
	 * @param string[] $keys  Input keys to check for ISO 8601 date-time values.
	 * @return \WP_Error|null WP_Error when any listed key holds a malformed value, null otherwise.
	 */
	private static function validate_iso8601_dates( $input, array $keys ) {
		if ( ! is_array( $input ) ) {
			return null;
		}
		foreach ( $keys as $key ) {
			if ( ! isset( $input[ $key ] ) || '' === $input[ $key ] ) {
				continue;
			}
			$value = $input[ $key ];
			if ( ! is_string( $value ) ) {
				return new \WP_Error(
					'woopayments_invalid_date',
					sprintf(
						/* translators: %s: input parameter name. */
						__( 'The "%s" parameter must be an ISO 8601 date-time string.', 'woocommerce-payments' ),
						$key
					)
				);
			}
			// Accept either a full date-time or a bare date — both are common agent inputs
			// and the backing endpoints will accept either. Reject anything else.
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2}))?$/', $value ) ) {
				return new \WP_Error(
					'woopayments_invalid_date',
					sprintf(
						/* translators: %s: input parameter name. */
						__( 'The "%s" parameter must be an ISO 8601 date-time string.', 'woocommerce-payments' ),
						$key
					)
				);
			}
		}
		return null;
	}

	/**
	 * Register the woopayments/get-transactions ability.
	 *
	 * Lists WooPayments transactions using the report-shaped schema so agents
	 * receive a stable, documented payload (transaction_id, date, payment_id,
	 * channel, payment_method, type, amount, fees, customer, deposit status)
	 * rather than the raw Stripe balance transaction structure.
	 *
	 * @return void
	 */
	private static function register_get_transactions_ability(): void {
		wp_register_ability(
			'woopayments/get-transactions',
			[
				'label'               => __( 'List WooPayments transactions', 'woocommerce-payments' ),
				'description'         => __( 'Lists WooPayments transactions using the stable, report-shaped schema (transaction_id, date, payment_id, channel, payment_method, type, currency, amount, fees, customer, deposit status). Supports date, pagination, and basic filter parameters. Returns a single page (default 25, max 100). Iterate by incrementing `page`, or narrow with `date_after`/`date_before` for large result sets.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'default'              => [],
					'properties'           => [
						'page'                => [
							'type'        => 'integer',
							'minimum'     => 1,
							'default'     => 1,
							'description' => __( 'Page number.', 'woocommerce-payments' ),
						],
						'per_page'            => [
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 100,
							'default'     => 25,
							'description' => __( 'Number of transactions per page.', 'woocommerce-payments' ),
						],
						'date_before'         => [
							'type'        => 'string',
							'format'      => 'date-time',
							'description' => __( 'Filter transactions before this date.', 'woocommerce-payments' ),
						],
						'date_after'          => [
							'type'        => 'string',
							'format'      => 'date-time',
							'description' => __( 'Filter transactions after this date.', 'woocommerce-payments' ),
						],
						'match'               => [
							'type'        => 'string',
							'enum'        => [ 'and', 'or' ],
							'default'     => 'and',
							'description' => __( 'Logical operator applied when combining filters.', 'woocommerce-payments' ),
						],
						'sort'                => [
							'type'        => 'string',
							'default'     => 'date',
							'description' => __( 'Field on which to sort.', 'woocommerce-payments' ),
						],
						'direction'           => [
							'type'        => 'string',
							'enum'        => [ 'asc', 'desc' ],
							'default'     => 'desc',
							'description' => __( 'Sort direction.', 'woocommerce-payments' ),
						],
						// Canonical agent lookups — the broader List_Transactions filter surface
						// (type_is_in, source_device_is, channel_is, store_currency_is, deposit_id, etc.)
						// is deliberately out of scope for the initial shipment and can be added in a follow-up.
						'type'                => [
							'type'        => 'string',
							'description' => __( 'Filter to transactions of a specific type (e.g. "charge", "refund", "payment"). Validation is delegated to the backing controller.', 'woocommerce-payments' ),
						],
						'order_id'            => [
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Filter to transactions linked to a specific WooCommerce order ID.', 'woocommerce-payments' ),
						],
						'customer_email'      => [
							'type'        => 'string',
							'format'      => 'email',
							'description' => __( 'Filter to transactions for a specific customer email address.', 'woocommerce-payments' ),
						],
						'payment_method_type' => [
							'type'        => 'string',
							'description' => __( 'Filter to transactions paid with a specific payment method type (e.g. "card", "link").', 'woocommerce-payments' ),
						],
					],
					'additionalProperties' => false,
				],
				'execute_callback'    => [ __CLASS__, 'execute_get_transactions' ],
				'permission_callback' => [ __CLASS__, 'can_manage_payments' ],
				// output_schema deliberately omitted — the transactions report schema is documented on the backing REST controller and duplicating it here would couple this registrar to any future upstream change.
				'meta'                => [
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
					'show_in_rest' => true,
				],
			]
		);
	}

	/**
	 * Register the woopayments/get-disputes ability.
	 *
	 * Lists WooPayments disputes. Supports filtering by status — essential for
	 * the canonical "which disputes need a response?" agent query
	 * (status_is=needs_response or warning_needs_response).
	 *
	 * @return void
	 */
	private static function register_get_disputes_ability(): void {
		$dispute_statuses = [
			'warning_needs_response',
			'warning_under_review',
			'warning_closed',
			'needs_response',
			'under_review',
			'charge_refunded',
			'won',
			'lost',
		];

		wp_register_ability(
			'woopayments/get-disputes',
			[
				'label'               => __( 'List WooPayments disputes', 'woocommerce-payments' ),
				'description'         => __( 'Lists WooPayments disputes. Supports filtering by status (e.g. status_is=needs_response to answer "which disputes need a response?"), date range, store currency, and free-text search. Returns a single page (default 25, max 100). Iterate by incrementing `page`, or narrow with `date_after`/`date_before` for large result sets.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'default'              => [],
					'properties'           => [
						'page'              => [
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Page number.', 'woocommerce-payments' ),
						],
						'per_page'          => [
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 100,
							'description' => __( 'Number of disputes per page.', 'woocommerce-payments' ),
						],
						'match'             => [
							'type'        => 'string',
							'enum'        => [ 'and', 'or' ],
							'description' => __( 'Logical operator applied when combining filters.', 'woocommerce-payments' ),
						],
						'store_currency_is' => [
							'type'        => 'string',
							'description' => __( 'Filter by store currency code (e.g. "usd").', 'woocommerce-payments' ),
						],
						'date_before'       => [
							'type'        => 'string',
							'format'      => 'date-time',
							'description' => __( 'Filter disputes created before this date.', 'woocommerce-payments' ),
						],
						'date_after'        => [
							'type'        => 'string',
							'format'      => 'date-time',
							'description' => __( 'Filter disputes created after this date.', 'woocommerce-payments' ),
						],
						'search'            => [
							'type'        => 'string',
							'description' => __( 'Free-text search term.', 'woocommerce-payments' ),
						],
						'status_is'         => [
							'type'        => 'string',
							'enum'        => $dispute_statuses,
							'description' => __( 'Filter to disputes whose status equals this value.', 'woocommerce-payments' ),
						],
						'status_is_not'     => [
							'type'        => 'string',
							'enum'        => $dispute_statuses,
							'description' => __( 'Filter to disputes whose status does not equal this value.', 'woocommerce-payments' ),
						],
					],
					'additionalProperties' => false,
				],
				'execute_callback'    => [ __CLASS__, 'execute_get_disputes' ],
				'permission_callback' => [ __CLASS__, 'can_manage_payments' ],
				// output_schema deliberately omitted — the disputes payload shape comes straight from the WooPayments server and we don't want to couple to a specific structure here.
				'meta'                => [
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
					'show_in_rest' => true,
				],
			]
		);
	}

	/**
	 * Register the woopayments/get-dispute-detail ability.
	 *
	 * Fetches a single WooPayments dispute by ID. The natural prerequisite for
	 * submit-dispute-evidence (Phase 5) — an agent reads the dispute reason,
	 * due-by date, and current status here before deciding how to respond.
	 *
	 * @return void
	 */
	private static function register_get_dispute_detail_ability(): void {
		wp_register_ability(
			'woopayments/get-dispute-detail',
			[
				'label'               => __( 'Get WooPayments dispute detail', 'woocommerce-payments' ),
				'description'         => __( 'Fetches a single WooPayments dispute by ID. Returns the full dispute payload (status, reason, due-by date, evidence due, amount, etc.). Natural prerequisite for submit-dispute-evidence.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'dispute_id' ],
					'properties'           => [
						'dispute_id' => [
							'type'        => 'string',
							'description' => __( 'The WooPayments dispute ID (e.g. "dp_1ABCxyz...").', 'woocommerce-payments' ),
						],
					],
					'additionalProperties' => false,
				],
				'execute_callback'    => [ __CLASS__, 'execute_get_dispute_detail' ],
				'permission_callback' => [ __CLASS__, 'can_manage_payments' ],
				// output_schema deliberately omitted — the dispute payload shape comes straight from the WooPayments server and we don't want to couple to a specific structure here.
				'meta'                => [
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
					'show_in_rest' => true,
				],
			]
		);
	}

	/**
	 * Register the woopayments/submit-dispute-evidence ability.
	 *
	 * First write ability in the WooPayments abilities surface. Attaches or
	 * finalizes evidence for an open dispute. Chosen over create-refund and
	 * accept-dispute for MVP because submitting evidence doesn't move money —
	 * it's additive, not destructive.
	 *
	 * Annotations: readonly:false, destructive:false, idempotent:false. The
	 * non-idempotent flag is load-bearing — duplicate submits to Stripe
	 * produce duplicate attempts; agent retry logic must guard double-calls.
	 *
	 * @return void
	 */
	private static function register_submit_dispute_evidence_ability(): void {
		wp_register_ability(
			'woopayments/submit-dispute-evidence',
			[
				'label'               => __( 'Submit WooPayments dispute evidence', 'woocommerce-payments' ),
				'description'         => __( 'Attach or update evidence for an open WooPayments dispute. Pass submit:true only after explicit merchant confirmation — once submitted, evidence cannot be retracted. Not idempotent: calling twice with submit:true produces duplicate submission attempts, so agent retry logic must guard against double-calls.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'dispute_id' ],
					'properties'           => [
						'dispute_id' => [
							'type'        => 'string',
							'description' => __( 'The WooPayments dispute identifier to submit evidence for. Starts with "dp_".', 'woocommerce-payments' ),
						],
						'evidence'   => [
							'type'                 => 'object',
							'description'          => __( 'The evidence payload. Accepts fields documented by Stripe\'s dispute evidence object (e.g. product_description, customer_communication, receipt, shipping_documentation). Unknown fields are forwarded to Stripe.', 'woocommerce-payments' ),
							'additionalProperties' => true,
						],
						'submit'     => [
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'If true, finalize and submit the evidence to Stripe. If false (default), save as draft without submitting. Agents should default to false and only submit after explicit merchant confirmation.', 'woocommerce-payments' ),
						],
						'metadata'   => [
							'type'                 => 'object',
							'description'          => __( 'Optional metadata key/value pairs to attach to the dispute.', 'woocommerce-payments' ),
							'additionalProperties' => [ 'type' => 'string' ],
						],
					],
					'additionalProperties' => false,
				],
				'execute_callback'    => [ __CLASS__, 'execute_submit_dispute_evidence' ],
				'permission_callback' => [ __CLASS__, 'can_manage_payments' ],
				// output_schema deliberately omitted — the dispute payload shape comes straight from the WooPayments server and we don't want to couple to a specific structure here.
				'meta'                => [
					'annotations'  => [
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					],
					'show_in_rest' => true,
				],
			]
		);
	}

	/**
	 * Register the woopayments/get-payouts ability.
	 *
	 * Lists WooPayments payouts. The backing REST endpoint is `deposits`, but
	 * user-facing naming is `payouts` — agents and merchants both speak
	 * "payouts", so this ability uses that term.
	 *
	 * @return void
	 */
	private static function register_get_payouts_ability(): void {
		$payout_statuses = [
			'paid',
			'pending',
			'in_transit',
			'canceled',
			'failed',
		];

		wp_register_ability(
			'woopayments/get-payouts',
			[
				'label'               => __( 'List WooPayments payouts', 'woocommerce-payments' ),
				'description'         => __( 'Lists WooPayments payouts (funds transfers from the WooPayments balance to the merchant\'s bank account). Supports filtering by status, store currency, and date range. Returns a single page (default 25, max 100). Iterate by incrementing `page`, or narrow with `date_after`/`date_before` for large result sets.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'default'              => [],
					'properties'           => [
						'page'              => [
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Page number.', 'woocommerce-payments' ),
						],
						'per_page'          => [
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 100,
							'description' => __( 'Number of payouts per page.', 'woocommerce-payments' ),
						],
						'match'             => [
							'type'        => 'string',
							'enum'        => [ 'and', 'or' ],
							'description' => __( 'Logical operator applied when combining filters.', 'woocommerce-payments' ),
						],
						'store_currency_is' => [
							'type'        => 'string',
							'description' => __( 'Filter by store currency code (e.g. "usd").', 'woocommerce-payments' ),
						],
						'date_before'       => [
							'type'        => 'string',
							'format'      => 'date-time',
							'description' => __( 'Filter payouts before this date.', 'woocommerce-payments' ),
						],
						'date_after'        => [
							'type'        => 'string',
							'format'      => 'date-time',
							'description' => __( 'Filter payouts after this date.', 'woocommerce-payments' ),
						],
						'status_is'         => [
							'type'        => 'string',
							'enum'        => $payout_statuses,
							'description' => __( 'Filter to payouts whose status equals this value.', 'woocommerce-payments' ),
						],
						'status_is_not'     => [
							'type'        => 'string',
							'enum'        => $payout_statuses,
							'description' => __( 'Filter to payouts whose status does not equal this value.', 'woocommerce-payments' ),
						],
					],
					'additionalProperties' => false,
				],
				'execute_callback'    => [ __CLASS__, 'execute_get_payouts' ],
				'permission_callback' => [ __CLASS__, 'can_manage_payments' ],
				// output_schema deliberately omitted — the payouts payload shape comes straight from the WooPayments server and we don't want to couple to a specific structure here.
				'meta'                => [
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
					'show_in_rest' => true,
				],
			]
		);
	}

	/**
	 * Register the woopayments/get-payout-overview ability.
	 *
	 * Zero-input ability that answers the canonical "when do I get paid?"
	 * agent question by returning the next scheduled payout across every
	 * enabled store currency, plus the corresponding balances.
	 *
	 * @return void
	 */
	private static function register_get_payout_overview_ability(): void {
		wp_register_ability(
			'woopayments/get-payout-overview',
			[
				'label'               => __( 'Get WooPayments payout overview', 'woocommerce-payments' ),
				'description'         => __( 'Returns the next scheduled WooPayments payout across every enabled store currency, along with the corresponding pending and available balances. Answers "when do I get paid?" in a single call.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'default'              => [],
					'properties'           => [],
					'additionalProperties' => false,
				],
				'execute_callback'    => [ __CLASS__, 'execute_get_payout_overview' ],
				'permission_callback' => [ __CLASS__, 'can_manage_payments' ],
				// output_schema deliberately omitted — the overview payload shape comes straight from the WooPayments server and we don't want to couple to a specific structure here.
				'meta'                => [
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
					'show_in_rest' => true,
				],
			]
		);
	}

	/**
	 * Register the woopayments/get-account-status ability.
	 *
	 * Reference implementation for the Abilities Everywhere initiative: a
	 * read-only ability an agent should call before any write so it understands
	 * what the merchant's WooPayments account can currently do (KYC state,
	 * enabled capabilities, deposit schedule, required actions).
	 *
	 * @return void
	 */
	private static function register_get_account_status_ability(): void {
		wp_register_ability(
			'woopayments/get-account-status',
			[
				'label'               => __( 'Get WooPayments account status', 'woocommerce-payments' ),
				'description'         => __( "Returns the merchant's WooPayments account status, including KYC state, enabled capabilities, deposit schedule, and any required actions. Agents should call this before any write ability to understand what the merchant can currently do.", 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'default'              => [],
					'properties'           => [],
					'additionalProperties' => false,
				],
				'execute_callback'    => [ __CLASS__, 'execute_get_account_status' ],
				'permission_callback' => [ __CLASS__, 'can_manage_payments' ],
				// output_schema deliberately omitted — the account payload shape drifts with Stripe's API and we don't want to couple to a specific structure here.
				'meta'                => [
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
					'show_in_rest' => true,
				],
			]
		);
	}
}
