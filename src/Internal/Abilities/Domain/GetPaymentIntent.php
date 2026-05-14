<?php
/**
 * Get Payment Intent ability definition.
 *
 * @package WooCommerce\Payments
 */

// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/get-payment-intent` ability.
 *
 * Look up a single payment intent by Stripe ID (pi_…). Answers 'what is
 * the state of intent pi_X?' during incident response. Delegates to
 * `/wc/v3/payments/payment_intents/{payment_intent_id}`. Returns
 * `WP_Error( 'wcpay_missing_payment_intent_id' )` when `payment_intent_id`
 * is missing, empty, or non-string.
 *
 * PII surface: the backing controller returns the full intent payload
 * including the nested charge (`billing_details` name/email/phone/address)
 * and any card metadata (`last4`, brand). Reviewed and accepted for
 * `manage_woocommerce`-gated MCP clients. If the cap gate ever moves
 * below admin, revisit this and add response filtering.
 *
 * @internal Only loaded when WooCommerce Core 10.9+ is active. The
 * `AbilitiesRegistrar` short-circuits before referencing this class on
 * earlier WC versions; PHP's lazy autoload means the unresolved
 * AbilityDefinition interface FQN never reaches the parser there.
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
	 * Body lifted verbatim from AbilitiesRegistrar::execute_get_payment_intent().
	 *
	 * @param mixed $input Ability input. Must include `payment_intent_id`.
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
