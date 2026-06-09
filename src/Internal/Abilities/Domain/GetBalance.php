<?php
/**
 * Get Balance ability definition.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/get-balance` ability.
 *
 * Returns the balance summary (available, pending, and on-hold movements) for
 * a currency over a date range. Backed by the balance report endpoint.
 *
 * @internal Only loaded when WooCommerce 10.9+ is active.
 *
 * @see \WC_REST_Payments_Reports_Balance_Controller::get_balance_summary()
 */
class GetBalance extends AbstractWCPayAbility implements AbilityDefinition {

	private const REQUIRED_FIELDS = [ 'date_start', 'date_end', 'currency' ];

	/**
	 * Return the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-payments/get-balance';
	}

	/**
	 * Return registration args for this ability.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get balance summary', 'woocommerce-payments' ),
			'description'         => __( 'Return the balance summary (charges, refunds, disputes, fees, payouts, ending balance) for a currency over a date range.', 'woocommerce-payments' ),
			'category'            => AbilitiesRegistrar::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'required'             => [ 'date_start', 'date_end', 'currency' ],
				'properties'           => [
					'date_start' => [
						'type'        => 'string',
						'description' => __( 'Start of the period (ISO-8601 date-time).', 'woocommerce-payments' ),
					],
					'date_end'   => [
						'type'        => 'string',
						'description' => __( 'End of the period (ISO-8601 date-time).', 'woocommerce-payments' ),
					],
					'currency'   => [
						'type'        => 'string',
						'description' => __( 'Three-letter ISO-4217 currency code.', 'woocommerce-payments' ),
					],
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
	 * Execute the get-balance ability.
	 *
	 * @see \WC_REST_Payments_Reports_Balance_Controller::get_balance_summary()
	 *
	 * @param array<string, mixed> $input Required: date_start, date_end, currency.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		$input = is_array( $input ) ? $input : [];

		foreach ( self::REQUIRED_FIELDS as $field ) {
			if ( ! isset( $input[ $field ] ) || ! is_string( $input[ $field ] ) || '' === $input[ $field ] ) {
				return new \WP_Error(
					'wcpay_missing_' . $field,
					sprintf(
						/* translators: %s: required field name. */
						__( 'A %s is required to read the balance summary.', 'woocommerce-payments' ),
						$field
					)
				);
			}
		}

		return AbilitiesRegistrar::delegate_to_rest_controller(
			'GET',
			'/wc/v3/payments/reports/balance',
			[
				'date_start' => $input['date_start'],
				'date_end'   => $input['date_end'],
				'currency'   => $input['currency'],
			]
		);
	}
}
