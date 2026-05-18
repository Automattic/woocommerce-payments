<?php
/**
 * Get Charge ability definition.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/get-charge` ability.
 *
 * Look up a single charge by Stripe ID (ch_… or py_…). Answers 'what
 * happened with charge ch_X?'. Returns `WP_Error( 'wcpay_missing_charge_id' )`
 * when `charge_id` is missing, empty, or non-string.
 *
 * PII exposure: billing details (name/email/phone/address) and order
 * metadata (customer_email, customer_name, ip_address, fraud_meta_box_type)
 * on the Stripe charge. No additional exposure over the existing admin
 * REST surface — the ability requires `manage_woocommerce`.
 *
 * @see \WC_REST_Payments_Charges_Controller::get_charge()
 */
class GetCharge implements AbilityDefinition {

	/**
	 * Return the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-payments/get-charge';
	}

	/**
	 * Return registration args for this ability.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get charge by ID', 'woocommerce-payments' ),
			'description'         => __( 'Look up a single charge by Stripe ID (ch_… or py_…). Answers \'what happened with charge ch_X?\'.', 'woocommerce-payments' ),
			'category'            => AbilitiesRegistrar::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'default'              => (object) [],
				'properties'           => [
					'charge_id' => [
						'type'        => 'string',
						'description' => 'Stripe charge ID (typically `ch_…` or `py_…`). Stripe ID prefixes are not contractually stable, so this field is not pattern-validated.',
					],
				],
				'required'             => [ 'charge_id' ],
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
	 * Execute the get-charge ability.
	 *
	 * @see \WC_REST_Payments_Charges_Controller::get_charge()
	 *
	 * @param array{charge_id: string} $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		if ( ! is_array( $input ) || empty( $input['charge_id'] ) || ! is_string( $input['charge_id'] ) ) {
			return new \WP_Error(
				'wcpay_missing_charge_id',
				__( 'A non-empty `charge_id` is required.', 'woocommerce-payments' )
			);
		}
		$charge_id = $input['charge_id'];
		return AbilitiesRegistrar::delegate_to_rest_controller( 'GET', '/wc/v3/payments/charges/' . rawurlencode( $charge_id ) );
	}
}
