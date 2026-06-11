<?php
/**
 * Get Account ability definition.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/get-account` ability.
 *
 * Zero-arg read that returns WooPayments account state. Returns
 * `WP_Error( 'wcpay_not_initialized' )` when WooPayments has not finished
 * initializing (delegate returns the unwrapped `false` as `[]`).
 *
 * @see \WC_REST_Payments_Accounts_Controller::get_account_data()
 */
class GetAccount implements AbilityDefinition {

	/**
	 * Return the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'woocommerce-payments/get-account';
	}

	/**
	 * Return registration args for this ability.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		return [
			'label'               => __( 'Get WooPayments account state', 'woocommerce-payments' ),
			'description'         => __( 'Return the merchant\'s WooPayments account state: onboarding status, country, store and customer currencies, KYC requirements, deadlines, and test/live mode flags.', 'woocommerce-payments' ),
			'category'            => AbilitiesRegistrar::CATEGORY_SLUG,
			'input_schema'        => [
				'type'                 => 'object',
				'default'              => (object) [],
				'properties'           => [],
				'additionalProperties' => false,
			],
			'execute_callback'    => [ self::class, 'execute' ],
			'permission_callback' => [ AbilitiesRegistrar::class, 'current_user_can_manage_woocommerce' ],
			// output_schema omitted: payload shape comes straight from the
			// backing controller.
			'meta'                => [
				'annotations'  => [
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				],
				'show_in_rest' => true,
				// `mcp.public` gates MCP discoverability via wordpress/mcp-adapter.
				'mcp'          => [
					'public' => true,
				],
			],
		];
	}

	/**
	 * Execute the get-account ability.
	 *
	 * @see \WC_REST_Payments_Accounts_Controller::get_account_data()
	 *
	 * @param mixed $input Unused (zero-arg ability).
	 * @return array|\WP_Error Account data, or `wcpay_not_initialized` WP_Error
	 *                        when the WooPayments account is not initialized.
	 */
	public static function execute( $input = null ) {
		$result = AbilitiesRegistrar::delegate_to_rest_controller( 'GET', '/wc/v3/payments/accounts' );

		// `false` from the controller (unwrapped to `[]` here) means WooPayments
		// is not initialized or not connected. A connected account always
		// returns a non-empty array.
		if ( is_array( $result ) && [] === $result ) {
			wc_get_logger()->error(
				'execute_get_account: WooPayments account not initialized — delegate returned an empty array.',
				[ 'source' => 'woopayments-abilities' ]
			);
			return new \WP_Error(
				'wcpay_not_initialized',
				__( 'WooPayments is not initialized.', 'woocommerce-payments' )
			);
		}

		return $result;
	}
}
