<?php
/**
 * Class Abilities_Registrar
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities;

use WP_Error;
use WP_REST_Request;

/**
 * Registers WooPayments abilities with the WordPress Abilities API.
 *
 * The Abilities API ships in WordPress 6.9. This class no-ops gracefully on
 * older WordPress versions so the plugin keeps loading on the L-2 baseline.
 */
class Abilities_Registrar {

	/**
	 * Category identifier for all WooPayments-registered abilities.
	 */
	const CATEGORY_ID = 'woocommerce-payments';

	/**
	 * Ability ID for the transactions reader.
	 */
	const ABILITY_GET_TRANSACTIONS = 'woocommerce-payments/get-transactions';

	/**
	 * Capability the ability requires. Mirrors the REST controller it delegates to.
	 */
	const REQUIRED_CAPABILITY = 'manage_woocommerce';

	/**
	 * Wire up the Abilities API hooks. Safe to call before WordPress 6.9 — the
	 * registration callbacks themselves bail when the API is unavailable.
	 */
	public static function init() {
		add_action( 'wp_abilities_api_categories_init', [ __CLASS__, 'register_categories' ] );
		add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
	}

	/**
	 * Register the WooPayments ability category.
	 */
	public static function register_categories() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY_ID,
			[
				'label'       => __( 'WooPayments', 'woocommerce-payments' ),
				'description' => __( 'Abilities exposed by the WooPayments plugin: read merchant data such as transactions, deposits, and disputes.', 'woocommerce-payments' ),
			]
		);
	}

	/**
	 * Register WooPayments abilities.
	 */
	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			self::ABILITY_GET_TRANSACTIONS,
			[
				'label'               => __( 'Get WooPayments transactions', 'woocommerce-payments' ),
				'description'         => __( 'List or fetch WooPayments transactions with filters for date, order, customer, payment method, and type.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_ID,
				'execute_callback'    => [ __CLASS__, 'execute_get_transactions' ],
				'permission_callback' => [ __CLASS__, 'check_permission_get_transactions' ],
				'input_schema'        => self::get_transactions_input_schema(),
				'output_schema'       => self::get_transactions_output_schema(),
				'meta'                => [
					'show_in_rest' => true,
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
				],
			]
		);
	}

	/**
	 * Permission check for the get-transactions ability.
	 *
	 * Mirrors WC_Payments_REST_Controller::check_permission(): callers need the
	 * same capability that gates the REST endpoint we delegate to.
	 *
	 * @return bool
	 */
	public static function check_permission_get_transactions() {
		return current_user_can( self::REQUIRED_CAPABILITY );
	}

	/**
	 * Execute the get-transactions ability by delegating to the existing REST
	 * controller. The handler at /wc/v3/payments/reports/transactions is a pure
	 * data-fetch with no side effects, so delegation keeps this layer thin and
	 * drift-resistant.
	 *
	 * When the caller supplies `transaction_id`, route to the singular endpoint
	 * to mirror the same internal filter the REST controller applies for /{id}.
	 *
	 * @param mixed $input Validated input matching `get_transactions_input_schema()`.
	 *
	 * @return mixed Response data on success, WP_Error on failure.
	 */
	public static function execute_get_transactions( $input = null ) {
		$params         = is_array( $input ) ? $input : [];
		$transaction_id = isset( $params['transaction_id'] ) ? (string) $params['transaction_id'] : '';
		unset( $params['transaction_id'] );

		if ( '' !== $transaction_id ) {
			$request = new WP_REST_Request( 'GET', '/wc/v3/payments/reports/transactions/' . $transaction_id );
		} else {
			$request = new WP_REST_Request( 'GET', '/wc/v3/payments/reports/transactions' );
			$request->set_query_params( $params );
		}

		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			return $response->as_error();
		}

		$data = $response->get_data();

		// Singular endpoint returns a single object (or an empty array for
		// "not found"). Normalize to a list so the ability's contract is a
		// consistent array of transactions regardless of input shape.
		if ( '' !== $transaction_id ) {
			if ( empty( $data ) ) {
				return [];
			}
			return [ $data ];
		}

		return $data;
	}

	/**
	 * Input schema for the get-transactions ability. Mirrors the REST
	 * controller's `get_collection_params()` plus a `transaction_id` filter
	 * that routes to the singular endpoint.
	 *
	 * @return array
	 */
	private static function get_transactions_input_schema() {
		return [
			'type'       => 'object',
			'properties' => [
				'transaction_id'      => [
					'type'        => 'string',
					'description' => __( 'Fetch a single transaction by ID. When set, other filters are ignored.', 'woocommerce-payments' ),
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
				'date_between'        => [
					'type'        => 'array',
					'items'       => [
						'type'   => 'string',
						'format' => 'date-time',
					],
					'description' => __( 'Filter transactions between these two dates.', 'woocommerce-payments' ),
				],
				'order_id'            => [
					'type'        => 'integer',
					'description' => __( 'Filter transactions by associated WooCommerce order ID.', 'woocommerce-payments' ),
				],
				'deposit_id'          => [
					'type'        => 'string',
					'description' => __( 'Filter transactions by associated deposit ID.', 'woocommerce-payments' ),
				],
				'customer_email'      => [
					'type'        => 'string',
					'description' => __( 'Filter transactions by customer email.', 'woocommerce-payments' ),
				],
				'payment_method_type' => [
					'type'        => 'string',
					'description' => __( 'Filter transactions by payment method type (e.g. card, ideal).', 'woocommerce-payments' ),
				],
				'type'                => [
					'type'        => 'string',
					'enum'        => [
						'charge',
						'payment',
						'payment_failure_refund',
						'payment_refund',
						'refund',
						'refund_failure',
						'dispute',
						'dispute_reversal',
						'card_reader_fee',
						'financing_payout',
						'financing_paydown',
						'fee_refund',
					],
					'description' => __( 'Filter transactions by type.', 'woocommerce-payments' ),
				],
				'match'               => [
					'type'        => 'string',
					'description' => __( 'Match strategy applied across filters.', 'woocommerce-payments' ),
				],
				'user_timezone'       => [
					'type'        => 'string',
					'description' => __( 'Timezone applied to date filtering.', 'woocommerce-payments' ),
				],
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
					'description' => __( 'Page size.', 'woocommerce-payments' ),
				],
				'sort'                => [
					'type'        => 'string',
					'default'     => 'date',
					'description' => __( 'Field to sort by.', 'woocommerce-payments' ),
				],
				'direction'           => [
					'type'        => 'string',
					'enum'        => [ 'asc', 'desc' ],
					'default'     => 'desc',
					'description' => __( 'Sort direction.', 'woocommerce-payments' ),
				],
			],
		];
	}

	/**
	 * Output schema for the get-transactions ability. Always an array of
	 * transactions — the singular fetch is normalised in
	 * `execute_get_transactions()` to a 0- or 1-element array so callers
	 * always work with the same shape.
	 *
	 * @return array
	 */
	private static function get_transactions_output_schema() {
		return [
			'type'  => 'array',
			'items' => [
				'type'       => 'object',
				'properties' => [
					'transaction_id'       => [ 'type' => 'string' ],
					'date'                 => [
						'type'   => 'string',
						'format' => 'date-time',
					],
					'payment_id'           => [ 'type' => [ 'string', 'null' ] ],
					'channel'              => [ 'type' => [ 'string', 'null' ] ],
					'payment_method'       => [
						'type'       => 'object',
						'properties' => [
							'type' => [ 'type' => [ 'string', 'null' ] ],
						],
					],
					'type'                 => [ 'type' => 'string' ],
					'transaction_currency' => [ 'type' => [ 'string', 'null' ] ],
					'amount'               => [ 'type' => [ 'number', 'null' ] ],
					'exchange_rate'        => [ 'type' => [ 'number', 'null' ] ],
					'deposit_currency'     => [ 'type' => [ 'string', 'null' ] ],
					'fees'                 => [ 'type' => [ 'number', 'null' ] ],
					'customer'             => [
						'type'       => 'object',
						'properties' => [
							'name'    => [ 'type' => [ 'string', 'null' ] ],
							'email'   => [ 'type' => [ 'string', 'null' ] ],
							'country' => [ 'type' => [ 'string', 'null' ] ],
						],
					],
					'net_amount'           => [ 'type' => [ 'number', 'null' ] ],
					'order_id'             => [ 'type' => [ 'integer', 'null' ] ],
					'risk_level'           => [ 'type' => [ 'number', 'null' ] ],
					'deposit_date'         => [ 'type' => [ 'string', 'null' ] ],
					'deposit_id'           => [ 'type' => [ 'string', 'null' ] ],
					'deposit_status'       => [ 'type' => [ 'string', 'null' ] ],
				],
			],
		];
	}
}
