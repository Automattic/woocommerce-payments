<?php
/**
 * Get Deposits Summary ability definition.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/get-deposits-summary` ability.
 *
 * Filters-only read that returns aggregate counts and totals of payouts by
 * status and currency. Answers "how many payouts have I received this
 * month?".
 *
 * @see \WC_REST_Payments_Deposits_Controller::get_deposits_summary()
 */
class GetDepositsSummary implements AbilityDefinition {

	/**
	 * Return the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-payments/get-deposits-summary';
	}

	/**
	 * Return registration args for this ability.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get payouts summary', 'woocommerce-payments' ),
			'description'         => __( 'Return aggregate counts and totals of payouts by status and currency. Answers \'how many payouts have I received this month?\'.', 'woocommerce-payments' ),
			'category'            => AbilitiesRegistrar::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'default'              => (object) [],
				'properties'           => [
					'match'             => [ 'type' => 'string' ],
					'store_currency_is' => [ 'type' => 'string' ],
					'date_before'       => [ 'type' => 'string' ],
					'date_after'        => [ 'type' => 'string' ],
					'date_between'      => [
						'type'  => 'array',
						'items' => [ 'type' => 'string' ],
					],
					'status_is'         => [
						'type'        => 'string',
						'description' => 'Payout status (paid|pending|in_transit|canceled|failed).',
					],
					'status_is_not'     => [ 'type' => 'string' ],
				],
				'additionalProperties' => false,
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
	 * Execute the get-deposits-summary ability.
	 *
	 * @see \WC_REST_Payments_Deposits_Controller::get_deposits_summary()
	 *
	 * @param array<string, mixed> $input Filter parameters. See
	 *                                    `get_registration_args()` for shape.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		return AbilitiesRegistrar::delegate_to_rest_controller( 'GET', '/wc/v3/payments/deposits/summary', is_array( $input ) ? $input : [] );
	}
}
