<?php
/**
 * Class WC_Payments_Incentives_Service
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Automattic\WooCommerce\Internal\Admin\Suggestions\Incentives\Incentive;
use Automattic\WooCommerce\Internal\Admin\Suggestions\PaymentsExtensionSuggestionIncentives;

/**
 * Class handling WooPayments incentives related business logic.
 */
class WC_Payments_Incentives_Service {
	const PREFIX = 'woocommerce_admin_pes_incentive_';

	/**
	 * The option name used to store whether the store had WooPayments in use.
	 *
	 * We use the same option key as the WC core suggestion incentives.
	 *
	 * @see \Automattic\WooCommerce\Internal\Admin\Suggestions\Incentives\WooPayments
	 */
	const STORE_HAD_WOOPAYMENTS_OPTION_NAME = self::PREFIX . 'woopayments_store_had_woopayments';

	/**
	 * The option name used to store the logic version that determined the store had WooPayments value.
	 *
	 * We use the same option key as the WC core suggestion incentives.
	 *
	 * @see \Automattic\WooCommerce\Internal\Admin\Suggestions\Incentives\WooPayments
	 */
	const STORE_HAD_WOOPAYMENTS_VERSION_OPTION_NAME = self::STORE_HAD_WOOPAYMENTS_OPTION_NAME . '_version';

	/**
	 * The WooCommerce core incentives provider ID for WooPayments.
	 *
	 * @see \Automattic\WooCommerce\Internal\Admin\Suggestions\PaymentsExtensionSuggestions::WOOPAYMENTS
	 */
	private const CORE_SUGGESTION_ID = 'woopayments';

	/**
	 * Initialise class hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'admin_menu', [ $this, 'add_payments_menu_badge' ] );
		add_filter( 'woocommerce_admin_allowed_promo_notes', [ $this, 'allowed_promo_notes' ] );
		add_filter( 'woocommerce_admin_woopayments_onboarding_task_badge', [ $this, 'onboarding_task_badge' ] );
		add_filter( 'woocommerce_admin_woopayments_onboarding_task_additional_data', [ $this, 'onboarding_task_additional_data' ], 20 );
	}

	/**
	 * Add badge to payments menu if there is an eligible incentive.
	 *
	 * @return void
	 */
	public function add_payments_menu_badge(): void {
		global $menu;

		// Return early if there is no eligible incentive.
		if ( ! $this->get_connect_incentive() ) {
			return;
		}

		$badge = WC_Payments_Admin::MENU_NOTIFICATION_BADGE;
		foreach ( $menu as $index => $menu_item ) {
			if ( false === strpos( $menu_item[0], $badge ) && ( 'wc-admin&path=/payments/connect' === $menu_item[2] ) ) {
				$menu[ $index ][0] .= $badge; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

				// One menu item with a badge is more than enough.
				break;
			}
		}
	}

	/**
	 * Adds allowed promo notes from eligible incentive.
	 *
	 * @param array $promo_notes Current allowed promo notes.
	 * @return array Updated allowed promo notes.
	 */
	public function allowed_promo_notes( $promo_notes = [] ): array {
		$incentive = $this->get_connect_incentive();
		// Return early if there is no eligible incentive.
		if ( empty( $incentive['id'] ) ) {
			return $promo_notes;
		}

		$promo_notes[] = $incentive['id'];

		return $promo_notes;
	}

	/**
	 * Adds the WooPayments incentive badge to the onboarding task.
	 *
	 * @param string $badge Current badge.
	 *
	 * @return string
	 */
	public function onboarding_task_badge( string $badge ): string {
		$incentive = $this->get_connect_incentive();
		// Return early if there is no eligible incentive or there is no task badge for it.
		if ( empty( $incentive['task_badge'] ) ) {
			return $badge;
		}

		return $incentive['task_badge'];
	}

	/**
	 * Filter the onboarding task additional data to add the WooPayments incentive data to it.
	 *
	 * @param ?array $additional_data The current task additional data.
	 *
	 * @return ?array The filtered task additional data.
	 */
	public function onboarding_task_additional_data( ?array $additional_data ): ?array {
		$incentive = $this->get_connect_incentive();
		// Return early if there is no eligible incentive.
		if ( empty( $incentive['id'] ) ) {
			return $additional_data;
		}

		if ( empty( $additional_data ) ) {
			$additional_data = [];
		}
		$additional_data['wooPaymentsIncentiveId'] = $incentive['id'];

		return $additional_data;
	}

	/**
	 * Clear the incentives cache.
	 */
	public function clear_cache() {
		$provider = $this->get_core_incentives_provider();
		if ( null !== $provider && method_exists( $provider, 'clear_cache' ) ) {
			$provider->clear_cache();
		}
	}

	/**
	 * Reset the memoized incentives.
	 *
	 * This is useful for testing purposes.
	 */
	public function reset_memo() {
		$provider = $this->get_core_incentives_provider();
		if ( null !== $provider && method_exists( $provider, 'reset_memo' ) ) {
			$provider->reset_memo();
		}
	}

	/**
	 * Get an eligible 'connect_page' type incentive, if available.
	 *
	 * @return array|null The incentive details or null if there is no eligible incentive.
	 */
	public function get_connect_incentive(): ?array {
		// Return early if there is an account connected.
		if ( WC_Payments::get_account_service()->is_stripe_connected() ) {
			return null;
		}

		// The country for which to get the incentive.
		$country_code = WC()->countries->get_base_country();

		// Return early if the country is not supported.
		if ( ! array_key_exists( $country_code, WC_Payments_Utils::supported_countries() ) ) {
			return null;
		}

		// Get the eligible 'connect_page' incentives that have what the Connect page needs to display them.
		$incentives = array_filter(
			$this->get_incentives( $country_code ),
			function ( $incentive ) {
				return $this->validate_incentive( $incentive );
			}
		);

		// Return early if there are no incentives left.
		if ( empty( $incentives ) ) {
			return null;
		}

		// Finally, return the first incentive found, if there are more than one.
		return reset( $incentives );
	}

	/**
	 * Gets the eligible 'connect_page' incentives from WooCommerce core.
	 *
	 * WooCommerce core owns fetching, caching and determining eligibility for WooPayments incentives.
	 *
	 * @param string $country_code The business location country code to get incentives for.
	 *
	 * @return array List of eligible incentives.
	 */
	private function get_incentives( string $country_code ): array {
		$provider = $this->get_core_incentives_provider();
		if ( null === $provider ) {
			return [];
		}

		try {
			return $provider->get_all( $country_code, 'connect_page' );
		} catch ( \Throwable $e ) {
			return [];
		}
	}

	/**
	 * Gets the WooCommerce core incentives provider for WooPayments.
	 *
	 * The provider is internal to WooCommerce core and only exists since WooCommerce 9.5,
	 * so stores without it get no incentives rather than an error.
	 *
	 * @return Incentive|null The provider, or null if it is not available.
	 */
	protected function get_core_incentives_provider(): ?Incentive {
		if ( ! function_exists( 'wc_get_container' ) || ! class_exists( PaymentsExtensionSuggestionIncentives::class ) ) {
			return null;
		}

		try {
			$provider = wc_get_container()->get( PaymentsExtensionSuggestionIncentives::class )->get_incentive_instance( self::CORE_SUGGESTION_ID );
		} catch ( \Throwable $e ) {
			return null;
		}

		return $provider instanceof Incentive ? $provider : null;
	}

	/**
	 * Check whether the incentive data is valid.
	 * Expects an array with at least `id`, `description`, and `tc_url` keys.
	 *
	 * @param mixed $incentive_data The incentive data.
	 *
	 * @return bool Whether the incentive data is valid.
	 */
	private function validate_incentive( $incentive_data ): bool {
		if ( ! is_array( $incentive_data )
			|| empty( $incentive_data )
			|| ! isset( $incentive_data['id'] )
			|| ! isset( $incentive_data['description'] )
			|| ! isset( $incentive_data['tc_url'] ) ) {

			return false;
		}

		return true;
	}
}
