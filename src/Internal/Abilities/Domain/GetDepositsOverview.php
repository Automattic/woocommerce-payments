<?php
/**
 * Get Deposits Overview ability definition.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/get-deposits-overview` ability.
 *
 * Zero-arg read that returns a per-currency overview of upcoming and
 * recent payouts (Stripe deposits). Answers "when is my next payout and
 * how much?".
 *
 * @see \WC_REST_Payments_Deposits_Controller::get_all_deposits_overviews()
 */
class GetDepositsOverview implements AbilityDefinition {

	/**
	 * Return the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-payments/get-deposits-overview';
	}

	/**
	 * Return registration args for this ability.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get payouts overview', 'woocommerce-payments' ),
			'description'         => __( 'Return a per-currency overview of upcoming and recent payouts (Stripe deposits). Answers \'when is my next payout and how much?\'.', 'woocommerce-payments' ),
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
	 * Execute the get-deposits-overview ability.
	 *
	 * @see \WC_REST_Payments_Deposits_Controller::get_all_deposits_overviews()
	 *
	 * @param mixed $input Unused (zero-arg ability).
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		return AbilitiesRegistrar::delegate_to_rest_controller( 'GET', '/wc/v3/payments/deposits/overview-all' );
	}
}
