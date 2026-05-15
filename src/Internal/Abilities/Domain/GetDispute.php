<?php
/**
 * Get Dispute ability definition.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/get-dispute` ability.
 *
 * Look up a single dispute by ID. Answers 'what evidence is needed for
 * dispute du_X and by when?'. Returns `WP_Error( 'wcpay_missing_dispute_id' )`
 * when `dispute_id` is missing, empty, or non-string.
 *
 * @see \WC_REST_Payments_Disputes_Controller::get_dispute()
 */
class GetDispute implements AbilityDefinition {

	/**
	 * Return the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-payments/get-dispute';
	}

	/**
	 * Return registration args for this ability.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get dispute by ID', 'woocommerce-payments' ),
			'description'         => __( 'Look up a single dispute by ID. Answers \'what evidence is needed for dispute du_X and by when?\'.', 'woocommerce-payments' ),
			'category'            => AbilitiesRegistrar::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'default'              => (object) [],
				'properties'           => [
					'dispute_id' => [
						'type'        => 'string',
						'description' => 'Stripe dispute ID (typically `du_…` or legacy `dp_…`). Stripe ID prefixes are not contractually stable, so this field is not pattern-validated.',
					],
				],
				'required'             => [ 'dispute_id' ],
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
	 * Execute the get-dispute ability.
	 *
	 * @see \WC_REST_Payments_Disputes_Controller::get_dispute()
	 *
	 * @param array{dispute_id: string} $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		if ( ! is_array( $input ) || empty( $input['dispute_id'] ) || ! is_string( $input['dispute_id'] ) ) {
			return new \WP_Error(
				'wcpay_missing_dispute_id',
				__( 'A non-empty `dispute_id` is required.', 'woocommerce-payments' )
			);
		}
		$dispute_id = $input['dispute_id'];
		return AbilitiesRegistrar::delegate_to_rest_controller( 'GET', '/wc/v3/payments/disputes/' . rawurlencode( $dispute_id ) );
	}
}
