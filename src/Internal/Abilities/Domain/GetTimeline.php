<?php
/**
 * Get Timeline ability definition.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/get-timeline` ability.
 *
 * Return the chronological event timeline for a payment intent. Returns
 * `WP_Error( 'wcpay_missing_intention_id' )` when `intention_id` is missing,
 * empty, or non-string. The input field name mirrors the route parameter
 * `/payments/timeline/(?P<intention_id>\w+)` — the same identifier
 * `get-payment-intent` accepts as `payment_intent_id`.
 *
 * @see \WC_REST_Payments_Timeline_Controller::get_timeline()
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
	 * @see \WC_REST_Payments_Timeline_Controller::get_timeline()
	 *
	 * @param array{intention_id: string} $input Ability input.
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
