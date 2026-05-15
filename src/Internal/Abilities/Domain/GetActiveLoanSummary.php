<?php
/**
 * Get Active Loan Summary ability definition.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/get-active-loan-summary` ability.
 *
 * Zero-arg read that returns the merchant's active Stripe Capital loan
 * summary. Answers "how much do I still owe on my Capital loan and what
 * is the daily repayment rate?".
 *
 * @see \WC_REST_Payments_Capital_Controller::get_active_loan_summary()
 */
class GetActiveLoanSummary implements AbilityDefinition {

	/**
	 * Return the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-payments/get-active-loan-summary';
	}

	/**
	 * Return registration args for this ability.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get active Capital loan summary', 'woocommerce-payments' ),
			'description'         => __( 'Return the merchant\'s active Stripe Capital loan summary. Answers \'how much do I still owe on my Capital loan and what is the daily repayment rate?\'. Returns an empty shape if no loan is active.', 'woocommerce-payments' ),
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
	 * Execute the get-active-loan-summary ability.
	 *
	 * @see \WC_REST_Payments_Capital_Controller::get_active_loan_summary()
	 *
	 * @param mixed $input Unused (zero-arg ability).
	 * @return array|\WP_Error
	 */
	public static function execute( $input = null ) {
		return AbilitiesRegistrar::delegate_to_rest_controller( 'GET', '/wc/v3/payments/capital/active_loan_summary' );
	}
}
