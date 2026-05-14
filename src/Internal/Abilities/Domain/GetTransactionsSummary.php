<?php
/**
 * Get Transactions Summary ability definition.
 *
 * @package WooCommerce\Payments
 */

// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/get-transactions-summary` ability.
 *
 * Filters-only read that returns aggregate counts and totals for a
 * transactions filter. Delegates to the
 * `/wc/v3/payments/transactions/summary` endpoint. Answers "how many
 * transactions / how much volume in this window?" without paging the list.
 *
 * The input_schema is the transactions list schema with pagination/sort
 * properties (`page`, `per_page`, `orderby`, `order`) removed — summary
 * endpoints don't paginate and keeping those keys would be misleading.
 *
 * @internal Only loaded when WooCommerce Core 10.9+ is active. The
 * `AbilitiesRegistrar` short-circuits before referencing this class on
 * earlier WC versions; PHP's lazy autoload means the unresolved
 * AbilityDefinition interface FQN never reaches the parser there.
 */
class GetTransactionsSummary implements AbilityDefinition {

	/**
	 * Return the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-payments/get-transactions-summary';
	}

	/**
	 * Return registration args for this ability.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get transactions summary', 'woocommerce-payments' ),
			'description'         => __( 'Return aggregate counts and totals for a transactions filter. Answers \'how many transactions / how much volume in this window?\' without paging the list.', 'woocommerce-payments' ),
			'category'            => AbilitiesRegistrar::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'default'              => (object) [],
				'properties'           => [
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
					'deposit_id'           => [
						'type'        => 'string',
						'description' => 'Filter to transactions belonging to a single payout (deposit) ID. Accepted by both the list and the summary endpoints.',
					],
				],
				'additionalProperties' => true,
			],
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
	 * Execute the get-transactions-summary ability.
	 *
	 * Delegates to the `/wc/v3/payments/transactions/summary` REST endpoint
	 * to return aggregate counts and totals for the given filter set.
	 *
	 * @param mixed $input Ability input (filter parameters).
	 * @return array|\WP_Error Transactions summary array, or WP_Error on failure.
	 */
	public static function execute( $input = null ) {
		return AbilitiesRegistrar::delegate_to_rest_controller( 'GET', '/wc/v3/payments/transactions/summary', is_array( $input ) ? $input : [] );
	}
}
