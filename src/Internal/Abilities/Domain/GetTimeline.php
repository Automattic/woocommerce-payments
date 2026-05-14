<?php
/**
 * Get Timeline ability definition.
 *
 * @package WooCommerce\Payments
 */

// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/get-timeline` ability.
 *
 * Return the chronological event timeline for a payment intent (created →
 * succeeded → refunded → disputed). Helps reconstruct what happened to one
 * transaction. Delegates to `/wc/v3/payments/timeline/{intention_id}`.
 * Returns `WP_Error( 'wcpay_missing_intention_id' )` when `intention_id`
 * is missing, empty, or non-string.
 *
 * The input field is named `intention_id` (rather than `payment_intent_id`
 * as used by `get-payment-intent`) to match the URL parameter on the
 * backing route `/payments/timeline/(?P<intention_id>\w+)`. Both names
 * refer to the same Stripe payment-intent identifier.
 *
 * @internal Only loaded when WooCommerce Core 10.9+ is active. The
 * `AbilitiesRegistrar` short-circuits before referencing this class on
 * earlier WC versions; PHP's lazy autoload means the unresolved
 * AbilityDefinition interface FQN never reaches the parser there.
 */
class GetTimeline implements AbilityDefinition {

	/**
	 * Return the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-payments/get-timeline';
	}

	/**
	 * Return registration args for this ability.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get timeline for payment intent', 'woocommerce-payments' ),
			'description'         => __( 'Return the chronological event timeline for a payment intent (created → succeeded → refunded → disputed). Helps reconstruct what happened to one transaction. Takes `intention_id` (the same Stripe `pi_…` identifier accepted by `get-payment-intent` as `payment_intent_id` — both names exist because they mirror the underlying REST route parameters).', 'woocommerce-payments' ),
			'category'            => AbilitiesRegistrar::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'default'              => (object) [],
				'properties'           => [
					'intention_id' => [
						'type'        => 'string',
						'description' => 'Stripe payment intent ID (typically `pi_…`). Same identifier accepted by `get-payment-intent` under the field name `payment_intent_id`. Stripe ID prefixes are not contractually stable, so this field is not pattern-validated.',
					],
				],
				'required'             => [ 'intention_id' ],
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
	 * Execute the get-timeline ability.
	 *
	 * Body lifted verbatim from AbilitiesRegistrar::execute_get_timeline().
	 *
	 * @param mixed $input Ability input. Must include `intention_id`.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		if ( ! is_array( $input ) || empty( $input['intention_id'] ) || ! is_string( $input['intention_id'] ) ) {
			return new \WP_Error(
				'wcpay_missing_intention_id',
				__( 'A non-empty `intention_id` is required.', 'woocommerce-payments' )
			);
		}
		$intent_id = $input['intention_id'];
		return AbilitiesRegistrar::delegate_to_rest_controller( 'GET', '/wc/v3/payments/timeline/' . rawurlencode( $intent_id ) );
	}
}
