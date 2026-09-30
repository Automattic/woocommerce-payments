<?php
/**
 * Get Payment Intent ability definition.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/get-payment-intent` ability.
 *
 * Look up a single payment intent by Stripe ID (pi_…). Answers 'what is
 * the state of intent pi_X?' during incident response. Returns
 * `WP_Error( 'wcpay_missing_payment_intent_id' )` when `payment_intent_id`
 * is missing, empty, or non-string.
 *
 * PII exposure: billing details on the nested charge (name/email/phone/
 * address) and payment-method metadata (last4, brand). No additional
 * exposure over the existing admin REST surface — the ability requires
 * `manage_woocommerce`.
 *
 * @see \WC_REST_Payments_Payment_Intents_Controller::get_payment_intent()
 */
class GetPaymentIntent implements AbilityDefinition {

	/**
	 * Return the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-payments/get-payment-intent';
	}

	/**
	 * Return registration args for this ability.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get payment intent by ID', 'woocommerce-payments' ),
			'description'         => __( 'Look up a single payment intent by Stripe ID (pi_…). Answers \'what is the state of intent pi_X?\' during incident response.', 'woocommerce-payments' ),
			'category'            => AbilitiesRegistrar::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'default'              => (object) [],
				'properties'           => [
					'payment_intent_id' => [
						'type'        => 'string',
						'description' => 'Stripe payment intent ID (typically `pi_…`). Stripe ID prefixes are not contractually stable, so this field is not pattern-validated.',
					],
				],
				'required'             => [ 'payment_intent_id' ],
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
	 * Execute the get-payment-intent ability.
	 *
	 * @see \WC_REST_Payments_Payment_Intents_Controller::get_payment_intent()
	 *
	 * @param array{payment_intent_id: string} $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		if ( ! is_array( $input ) || empty( $input['payment_intent_id'] ) || ! is_string( $input['payment_intent_id'] ) ) {
			return new \WP_Error(
				'wcpay_missing_payment_intent_id',
				__( 'A non-empty `payment_intent_id` is required.', 'woocommerce-payments' )
			);
		}
		$intent_id = $input['payment_intent_id'];
		return AbilitiesRegistrar::delegate_to_rest_controller( 'GET', '/wc/v3/payments/payment_intents/' . rawurlencode( $intent_id ) );
	}
}
