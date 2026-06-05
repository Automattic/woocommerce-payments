<?php
/**
 * Get Authorizations Summary ability definition.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/get-authorizations-summary` ability.
 *
 * Zero-arg read that returns aggregate counts and total authorized amount
 * for pending authorizations. Answers "how much money is held in uncaptured
 * authorizations?".
 *
 * @see \WC_REST_Payments_Authorizations_Controller::get_authorizations_summary()
 */
class GetAuthorizationsSummary implements AbilityDefinition {

	/**
	 * Return the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-payments/get-authorizations-summary';
	}

	/**
	 * Return registration args for this ability.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get authorizations summary', 'woocommerce-payments' ),
			'description'         => __( 'Return aggregate counts and total authorized amount for pending authorizations. Answers \'how much money is held in uncaptured authorizations?\'.', 'woocommerce-payments' ),
			'category'            => AbilitiesRegistrar::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'default'              => (object) [],
				'properties'           => [],
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
	 * Execute the get-authorizations-summary ability.
	 *
	 * @see \WC_REST_Payments_Authorizations_Controller::get_authorizations_summary()
	 *
	 * @param mixed $input Unused (zero-arg ability).
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		return AbilitiesRegistrar::delegate_to_rest_controller( 'GET', '/wc/v3/payments/authorizations/summary' );
	}
}
