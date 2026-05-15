<?php
/**
 * Get Transactions ability definition.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/get-transactions` ability.
 *
 * Paginated list of transactions. Returns the WC 10.9 paginated envelope:
 * `{ transactions: [...], total_pages, page, per_page }`. `page` / `per_page`
 * echo what the ability sent to the controller (the caller's input, or this
 * class's defaults).
 *
 * @see \WC_REST_Payments_Transactions_Controller::get_transactions()
 */
class GetTransactions extends AbstractWCPayAbility implements AbilityDefinition {

	private const DEFAULT_PER_PAGE = 25;
	private const MAX_PER_PAGE     = 100;

	/**
	 * Return the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-payments/get-transactions';
	}

	/**
	 * Return registration args for this ability.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		$filter_properties = [
			'match'                => [
				'type'        => 'string',
				'description' => 'Filter join mode (any|all).',
			],
			'date_before'          => [
				'type'        => 'string',
				'description' => 'ISO-8601 date upper bound.',
			],
			'date_after'           => [
				'type'        => 'string',
				'description' => 'ISO-8601 date lower bound.',
			],
			'date_between'         => [
				'type'        => 'array',
				'items'       => [ 'type' => 'string' ],
				'description' => 'Two-element ISO date range [start, end].',
			],
			'type_is'              => [
				'type'        => 'string',
				'description' => 'Transaction type filter (charge|refund|dispute|adjustment|…).',
			],
			'source_device_is'     => [ 'type' => 'string' ],
			'channel_is'           => [ 'type' => 'string' ],
			'customer_country_is'  => [ 'type' => 'string' ],
			'risk_level_is'        => [ 'type' => 'string' ],
			'store_currency_is'    => [ 'type' => 'string' ],
			'customer_currency_is' => [ 'type' => 'string' ],
			'search'               => [
				'type'  => 'array',
				'items' => [ 'type' => 'string' ],
			],
			'orderby'              => [ 'type' => 'string' ],
			'order'                => [
				'type' => 'string',
				'enum' => [ 'asc', 'desc' ],
			],
			'deposit_id'           => [
				'type'        => 'string',
				'description' => 'Filter to transactions belonging to a single payout (deposit) ID. Accepted by both the list and the summary endpoints.',
			],
		];

		return [
			'label'               => __( 'List transactions', 'woocommerce-payments' ),
			'description'         => __( 'List WooPayments transactions with filters (date range, type, source device, channel, customer country, risk level, currency, search). Answers \'show me transactions where X\'.', 'woocommerce-payments' ),
			'category'            => AbilitiesRegistrar::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'default'              => (object) [],
				'properties'           => array_merge(
					self::get_pagination_input_properties( self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE ),
					$filter_properties
				),
				'additionalProperties' => false,
			],
			'output_schema'       => self::get_collection_output_schema(
				'transactions',
				[ 'type' => 'object' ]
			),
			'execute_callback'    => [ self::class, 'execute' ],
			'permission_callback' => [ AbilitiesRegistrar::class, 'current_user_can_manage_woocommerce' ],
			'meta'                => [
				'annotations'  => [
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				],
				'show_in_rest' => true,
				'mcp'          => [
					'public' => true,
				],
			],
		];
	}

	/**
	 * Execute the get-transactions ability.
	 *
	 * @see \WC_REST_Payments_Transactions_Controller::get_transactions()
	 *
	 * @param array<string, mixed> $input Pagination + filter parameters. See
	 *                                    `get_registration_args()` for shape.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		$input             = is_array( $input ) ? $input : [];
		$input['page']     = isset( $input['page'] ) ? (int) $input['page'] : 1;
		$input['per_page'] = isset( $input['per_page'] ) ? (int) $input['per_page'] : self::DEFAULT_PER_PAGE;

		$response = AbilitiesRegistrar::delegate_to_rest_controller(
			'GET',
			'/wc/v3/payments/transactions',
			$input
		);

		return self::wrap_paginated_response( $response, 'transactions', $input['page'], $input['per_page'] );
	}
}
