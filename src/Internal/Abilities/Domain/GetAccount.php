<?php
/**
 * Get Account ability definition.
 *
 * @package WooCommerce\Payments
 */

// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.

namespace WCPay\Internal\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WCPay\Internal\Abilities\AbilitiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `woocommerce-payments/get-account` ability.
 *
 * Zero-arg read that returns WooPayments account state. Delegates to
 * `WC_REST_Payments_Accounts_Controller::get_account_data()` to preserve
 * parity with the existing REST endpoint (adds `card_present_eligible`,
 * `test_mode`, `test_mode_onboarding` flags on top of the cached service
 * payload). Returns `WP_Error( 'wcpay_not_initialized' )` when WooPayments
 * has not finished initializing (delegate returns the unwrapped `false`
 * as `[]`).
 *
 * @internal Only loaded when WooCommerce Core 10.9+ is active. The
 * `AbilitiesRegistrar` short-circuits before referencing this class on
 * earlier WC versions; PHP's lazy autoload means the unresolved
 * AbilityDefinition interface FQN never reaches the parser there.
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
			// output_schema deliberately omitted — the payload shape comes
			// straight from the backing controller and we don't want to
			// couple this registrar to a specific structure here.
			'meta'                => [
				'annotations'  => [
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				],
				'show_in_rest' => true,
				// `mcp.public` is read by the wordpress/mcp-adapter package
				// (McpAbilityHelperTrait::is_public_mcp_ability() +
				// DefaultServerFactory::register_abilities()) to gate MCP
				// discoverability. The key is the adapter's contract, not a
				// WP Core convention.
				'mcp'          => [
					'public' => true,
				],
			],
		];
	}

	/**
	 * Execute the get-account ability.
	 *
	 * Delegates to `WC_REST_Payments_Accounts_Controller::get_account_data()`
	 * to preserve parity with the existing REST endpoint (controller adds
	 * `card_present_eligible`, `test_mode`, and `test_mode_onboarding` flags
	 * on top of the cached service payload).
	 *
	 * Reads account state from the local cache when available; issues a
	 * remote API request to the Transact platform if the cache is empty
	 * or stale (the get-or-fetch semantics of `WC_Payments_Account::get_cached_account_data()`).
	 *
	 * @param mixed $input Unused (zero-arg ability); accepted to match the
	 *                     Abilities API execute_callback signature.
	 * @return array|\WP_Error Account data array, or WP_Error when WooPayments
	 *                         has not finished initializing.
	 */
	public static function execute( $input = null ) {
		$result = AbilitiesRegistrar::delegate_to_rest_controller( 'GET', '/wc/v3/payments/accounts' );

		// The backing controller returns `false` (unwrapped to `[]` here) when
		// WooPayments is not initialized or not connected. A connected account
		// always returns a non-empty array, so an empty array is unambiguous.
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
