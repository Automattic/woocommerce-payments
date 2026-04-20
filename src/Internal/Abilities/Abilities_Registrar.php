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
		if ( ! class_exists( '\WC_REST_Payments_Reports_Transactions_Controller' ) ) {
			return new \WP_Error(
				'woopayments_not_initialized',
				__( 'WooPayments is not initialized.', 'woocommerce-payments' )
			);
		}

		$request = new \WP_REST_Request( 'GET', '/wc/v3/payments/reports/transactions' );
		if ( is_array( $input ) ) {
			foreach ( $input as $param => $value ) {
				$request->set_param( $param, $value );
			}
		}

		$controller = new \WC_REST_Payments_Reports_Transactions_Controller();
		$response   = $controller->get_transactions( $request );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response->get_data();
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
		if ( ! class_exists( '\WC_REST_Payments_Disputes_Controller' ) ) {
			return new \WP_Error(
				'woopayments_not_initialized',
				__( 'WooPayments is not initialized.', 'woocommerce-payments' )
			);
		}

		$request = new \WP_REST_Request( 'GET', '/wc/v3/payments/disputes' );
		if ( is_array( $input ) ) {
			foreach ( $input as $param => $value ) {
				$request->set_param( $param, $value );
			}
		}

		$controller = new \WC_REST_Payments_Disputes_Controller();
		$response   = $controller->get_disputes( $request );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return is_array( $response ) ? $response : [];
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
				'description'         => __( 'Lists WooPayments transactions using the stable, report-shaped schema (transaction_id, date, payment_id, channel, payment_method, type, currency, amount, fees, customer, deposit status). Supports date, pagination, and basic filter parameters.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'default'              => [],
					'properties'           => [
						'page'        => [
							'type'        => 'integer',
							'minimum'     => 1,
							'default'     => 1,
							'description' => __( 'Page number.', 'woocommerce-payments' ),
						],
						'per_page'    => [
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 100,
							'default'     => 25,
							'description' => __( 'Number of transactions per page.', 'woocommerce-payments' ),
						],
						'date_before' => [
							'type'        => 'string',
							'format'      => 'date-time',
							'description' => __( 'Filter transactions before this date.', 'woocommerce-payments' ),
						],
						'date_after'  => [
							'type'        => 'string',
							'format'      => 'date-time',
							'description' => __( 'Filter transactions after this date.', 'woocommerce-payments' ),
						],
						'match'       => [
							'type'        => 'string',
							'enum'        => [ 'and', 'or' ],
							'default'     => 'and',
							'description' => __( 'Logical operator applied when combining filters.', 'woocommerce-payments' ),
						],
						'sort'        => [
							'type'        => 'string',
							'default'     => 'date',
							'description' => __( 'Field on which to sort.', 'woocommerce-payments' ),
						],
						'direction'   => [
							'type'        => 'string',
							'enum'        => [ 'asc', 'desc' ],
							'default'     => 'desc',
							'description' => __( 'Sort direction.', 'woocommerce-payments' ),
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
				'description'         => __( 'Lists WooPayments disputes. Supports filtering by status (e.g. status_is=needs_response to answer "which disputes need a response?"), date range, store currency, and free-text search.', 'woocommerce-payments' ),
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
