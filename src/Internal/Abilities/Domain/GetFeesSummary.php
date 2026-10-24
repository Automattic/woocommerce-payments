<?php
/**
 * Get Fees Summary ability definition.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/get-fees-summary` ability.
 *
 * Returns aggregate processing-fee totals over a date range (and optional
 * filters). Backed by the fees report summary endpoint.
 *
 * @internal Only loaded when WooCommerce 10.9+ is active.
 *
 * @see \WC_REST_Payments_Reports_Fees_Controller::get_fees_summary()
 */
class GetFeesSummary extends AbstractWCPayAbility implements AbilityDefinition {

	/**
	 * Return the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-payments/get-fees-summary';
	}

	/**
	 * Return registration args for this ability.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get processing-fee summary', 'woocommerce-payments' ),
			'description'         => __( 'Return aggregate processing-fee totals (count, fees, net) over a date range. Answers \'how much did I pay in fees this period?\'.', 'woocommerce-payments' ),
			'category'            => AbilitiesRegistrar::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'default'              => (object) [],
				'properties'           => [
					'date_before'         => [ 'type' => 'string' ],
					'date_after'          => [ 'type' => 'string' ],
					'date_between'        => [
						'type'  => 'array',
						'items' => [ 'type' => 'string' ],
					],
					'match'               => [ 'type' => 'string' ],
					'deposit_id'          => [ 'type' => 'string' ],
					'payment_method_type' => [ 'type' => 'string' ],
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
	 * Execute the get-fees-summary ability.
	 *
	 * @see \WC_REST_Payments_Reports_Fees_Controller::get_fees_summary()
	 *
	 * @param array<string, mixed> $input Optional date/filter parameters.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		$input = is_array( $input ) ? $input : [];

		return AbilitiesRegistrar::delegate_to_rest_controller(
			'GET',
			'/wc/v3/payments/reports/fees/summary',
			$input
		);
	}
}
