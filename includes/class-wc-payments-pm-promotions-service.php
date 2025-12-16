<?php
/**
 * Class WC_Payments_PM_Promotions_Service
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Constants\Track_Events;
use WCPay\Core\Server\Request;
use WCPay\Logger;

/**
 * Class handling WooPayments payment method promotions related business logic.
 */
class WC_Payments_PM_Promotions_Service {

	/**
	 * Transient key for caching promotions.
	 *
	 * @var string
	 */
	const PROMOTIONS_CACHE_KEY = 'wcpay_pm_promotions';

	/**
	 * Option key for promotion dismissals.
	 * Stores array of [id => timestamp].
	 *
	 * @var string
	 */
	const PROMOTION_DISMISSALS_OPTION = '_wcpay_pm_promotion_dismissals';

	/**
	 * The memoized raw promotions to avoid fetching multiple times during a request.
	 *
	 * @var array|null
	 */
	private $promotions_memo = null;

	/**
	 * The memoized visible promotions (filtered and normalized) for the current request.
	 * False means not yet computed, null means computed with no results, array means has results.
	 *
	 * @var array|null|false
	 */
	private $visible_promotions_memo = false;

	/**
	 * WC_Payment_Gateway_WCPay instance.
	 *
	 * @var WC_Payment_Gateway_WCPay|null
	 */
	private $gateway;

	/**
	 * WC_Payments_Account instance.
	 *
	 * @var WC_Payments_Account|null
	 */
	private $account;

	/**
	 * Class constructor.
	 *
	 * @param WC_Payment_Gateway_WCPay|null $gateway Optional gateway instance.
	 * @param WC_Payments_Account|null      $account Optional account instance.
	 */
	public function __construct( ?WC_Payment_Gateway_WCPay $gateway = null, ?WC_Payments_Account $account = null ) {
		$this->gateway = $gateway;
		$this->account = $account;
	}

	/**
	 * Initialise class hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		// Hooks can be added here if needed in the future.
	}

	/**
	 * Clear the promotions cache.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		delete_transient( self::PROMOTIONS_CACHE_KEY );
		$this->reset_memo();
	}

	/**
	 * Reset the memoized promotions.
	 *
	 * This is useful for testing purposes.
	 *
	 * @return void
	 */
	public function reset_memo(): void {
		$this->promotions_memo         = null;
		$this->visible_promotions_memo = false;
	}

	/**
	 * Get promotions that should be visible to the user.
	 *
	 * @return array|null The promotions or null if there is no eligible promotion.
	 */
	public function get_visible_promotions(): ?array {
		// Promotions are only visible to users who can manage WooCommerce (aka act on the promotions).
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return null;
		}

		// Return memoized result if available (false means not yet computed).
		if ( false !== $this->visible_promotions_memo ) {
			return $this->visible_promotions_memo;
		}

		$promotions = $this->get_promotions();

		// Validate each promotion's structure.
		$promotions = array_filter(
			$promotions,
			function ( $promotion ) {
				return $this->validate_promotion( $promotion );
			}
		);

		// Filter promotions by dismissal status, PM validity, enabled status, and promo_id uniqueness.
		$promotions = $this->filter_promotions( $promotions );

		// Normalize the promotions (apply fallbacks, derive fields).
		$promotions = $this->normalize_promotions( $promotions );

		// Return early if there are no promotions left.
		if ( empty( $promotions ) ) {
			$this->visible_promotions_memo = null;
			return null;
		}

		$this->visible_promotions_memo = array_values( $promotions );
		return $this->visible_promotions_memo;
	}

	/**
	 * Fetches and caches eligible promotions from the WooPayments API.
	 *
	 * @return array List of eligible promotions.
	 */
	private function get_promotions(): array {
		// Check memoized data first.
		if ( null !== $this->promotions_memo ) {
			return $this->promotions_memo;
		}

		// Try to use the cached data.
		$cache = get_transient( self::PROMOTIONS_CACHE_KEY );

		// If the cached data is not expired, and it's a WP_Error,
		// it means there was an API error previously, and we should not retry just yet.
		if ( is_wp_error( $cache ) ) {
			// Initialize the in-memory cache and return it.
			$this->promotions_memo = [];

			return $this->promotions_memo;
		}

		// Gather the store context data.
		$store_context = [
			// All the PM promotions dismissals.
			'dismissals' => $this->get_promotion_dismissals(),
			// Store locale, e.g. `en_US`.
			'locale'     => get_locale(),
		];

		// Fingerprint the store context through a hash of certain entries.
		$store_context_hash = $this->generate_context_hash( $store_context );

		// Use the transient cached data if it exists, it is not expired,
		// and the store context hasn't changed since we last requested from the WooPayments API (based on context hash).
		if ( false !== $cache
			&& ! empty( $cache['context_hash'] ) && is_string( $cache['context_hash'] )
			&& hash_equals( $store_context_hash, $cache['context_hash'] ) ) {

			// We have a store context hash, and it matches with the current context one.
			// We can use the cached data.
			$this->promotions_memo = $cache['promotions'] ?? [];

			return $this->promotions_memo;
		}

		// By this point, we have an expired transient or the store context has changed.
		// Query for promotions by calling the WooPayments API.
		$wcpay_request = Request\Get_PM_Promotions::create();
		$wcpay_request->set_store_context_params( $store_context );
		$response = $wcpay_request->handle_rest_request();

		// Return early if there is an error, waiting 6 hours before the next attempt.
		if ( is_wp_error( $response ) ) {
			// Store a trimmed down, lightweight error.
			/**
			 * Type hint for static analysis.
			 *
			 * @var WP_Error $response
			 */
			$error = new \WP_Error(
				$response->get_error_code(),
				$response->get_error_message(),
				wp_remote_retrieve_response_code( $response )
			);
			// Store the error in the transient so we know this is due to an API error.
			set_transient( self::PROMOTIONS_CACHE_KEY, $error, HOUR_IN_SECONDS * 6 );

			// Initialize the in-memory cache and return it.
			$this->promotions_memo = [];

			return $this->promotions_memo;
		}

		$cache_for = wp_remote_retrieve_header( $response, 'cache-for' );
		// Initialize the in-memory cache.
		$this->promotions_memo = [];

		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			// Decode the results, falling back to an empty array.
			$results = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

			$this->promotions_memo = $results;
		}

		// Skip transient cache if `cache-for` header equals zero.
		if ( '0' === $cache_for ) {
			// If we have a transient cache that is not expired, delete it so there are no leftovers.
			if ( false !== $cache ) {
				delete_transient( self::PROMOTIONS_CACHE_KEY );
			}

			return $this->promotions_memo;
		}

		// Store promotions in the transient cache (together with the context hash) for the given number of seconds
		// or 1 day in seconds. Also attach a timestamp to the transient data so we know when we last fetched.
		set_transient(
			self::PROMOTIONS_CACHE_KEY,
			[
				'promotions'   => $this->promotions_memo,
				'context_hash' => $store_context_hash,
				'timestamp'    => time(),
			],
			! empty( $cache_for ) ? (int) $cache_for : DAY_IN_SECONDS
		);

		return $this->promotions_memo;
	}

	/**
	 * Activate a promotion.
	 *
	 * This will:
	 * 1. Send a request to the server to apply the promotion discount.
	 * 2. Enable the payment method for checkout.
	 * Activating a promotion implies acceptance of terms and conditions for the promotion.
	 *
	 * @param string $id The promotion unique identifier (e.g., 'klarna-2026-promo__spotlight').
	 *
	 * @return bool True on success, false on failure.
	 */
	public function activate_promotion( string $id ): bool {
		// Find the promotion to get the payment method.
		$promotion = $this->find_promotion_by_id( $id );
		if ( null === $promotion ) {
			return false;
		}

		$payment_method_id = $promotion['payment_method'] ?? '';
		if ( empty( $payment_method_id ) ) {
			return false;
		}

		// Send request to server to apply the promotion discount.
		// The server should also handle capability requesting if it is not already requested.
		// This way we can keep things in sync and avoid applying discounts without having the capability requested.
		$wcpay_request = Request\Activate_PM_Promotion::create( $id );
		$wcpay_request->assign_hook( 'wcpay_activate_pm_promotion_request' );
		$response = $wcpay_request->handle_rest_request();
		if ( is_wp_error( $response ) ) {
			$error_message = sprintf(
				'Server activation request failed [%s]: %s',
				$response->get_error_code(),
				$response->get_error_message()
			);
			return $this->handle_promotion_activation_failure( $payment_method_id, $promotion, $error_message );
		}

		// Mark the promotion as dismissed so it won't be shown again.
		// Do it before the payment method gateway enabling in case that fails.
		$this->mark_promotion_dismissed( $id );

		// Enable the payment method for checkout.
		if ( ! $this->enable_payment_method_gateway( $payment_method_id, $promotion ) ) {
			return false;
		}

		// Clear the promotions cache to ensure fresh data on next fetch.
		$this->clear_cache();
		// Clear the account cache.
		if ( null !== $this->account ) {
			$this->account->clear_cache();
		}

		// Track successful activation.
		$this->tracks_event(
			Track_Events::PAYMENT_METHOD_PROMOTION_ACTIVATED,
			[
				'payment_method_id' => $payment_method_id,
				'promo_id'          => $promotion['promo_id'] ?? null,
				'promo_instance_id' => $id,
			]
		);

		return true;
	}

	/**
	 * Find a promotion by its ID.
	 *
	 * @param string $id The promotion ID (e.g., 'klarna-2026-promo__spotlight').
	 *
	 * @return array|null The promotion data or null if not found.
	 */
	private function find_promotion_by_id( string $id ): ?array {
		$promotions = $this->get_visible_promotions();

		if ( null === $promotions ) {
			return null;
		}

		foreach ( $promotions as $promotion ) {
			if ( isset( $promotion['id'] ) && $promotion['id'] === $id ) {
				return $promotion;
			}
		}

		return null;
	}

	/**
	 * Find a promotion for a payment method.
	 *
	 * We will return the first promotion found for the payment method.
	 *
	 * @param string $payment_method_id The payment method ID (e.g., 'klarna').
	 *
	 * @return array|null The promotion data or null if not found.
	 */
	private function find_promotion_by_payment_method( string $payment_method_id ): ?array {
		$promotions = $this->get_visible_promotions();

		if ( null === $promotions ) {
			return null;
		}

		foreach ( $promotions as $promotion ) {
			if ( isset( $promotion['payment_method'] ) && $promotion['payment_method'] === $payment_method_id ) {
				return $promotion;
			}
		}

		return null;
	}

	/**
	 * Enable a payment method gateway.
	 *
	 * @param string $payment_method_id The payment method ID (e.g., 'klarna').
	 * @param array  $promotion         The promotion data associated with the payment method.
	 *
	 * @return bool True on success, false on failure.
	 */
	private function enable_payment_method_gateway( string $payment_method_id, array $promotion ): bool {
		$gateway = WC_Payments::get_payment_gateway_by_id( $payment_method_id );
		if ( ! $gateway ) {
			$this->log_gateway_error( $payment_method_id, 'payment gateway instance not available' );
			return false;
		}

		// Attempt to enable the gateway with exception handling.
		try {
			$gateway->enable();
		} catch ( \Exception $e ) {
			$this->log_gateway_error( $payment_method_id, $e->getMessage() );
			return false;
		}

		// Verify the gateway was actually enabled.
		if ( 'yes' !== $gateway->get_option( 'enabled' ) ) {
			$this->log_gateway_error( $payment_method_id, 'gateway enable() did not persist enabled state' );
			return false;
		}

		$pm_to_capability_key_map = $gateway->get_payment_method_capability_key_map();
		$this->tracks_event(
			Track_Events::PAYMENT_METHOD_ENABLED,
			[
				'payment_method_id' => $payment_method_id,
				'capability_id'     => $pm_to_capability_key_map[ $payment_method_id ] ?? null,
				'promo_id'          => $promotion['promo_id'] ?? null,
			]
		);

		// Synchronize enabled payment method IDs across all gateways.
		$this->sync_enabled_payment_method_across_gateways( $payment_method_id );

		return true;
	}

	/**
	 * Log a gateway error.
	 *
	 * @param string $payment_method_id The payment method ID.
	 * @param string $error_message     The error message.
	 */
	private function log_gateway_error( string $payment_method_id, string $error_message ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->warning(
				sprintf(
					/* translators: 1: Payment method ID, 2: Error message */
					'Failed to enable payment method %1$s: %2$s',
					$payment_method_id,
					$error_message
				),
				[ 'source' => 'woopayments' ]
			);
		}
	}

	/**
	 * Synchronize enabled payment method ID across all gateways.
	 *
	 * @param string $payment_method_id The payment method ID to sync.
	 */
	private function sync_enabled_payment_method_across_gateways( string $payment_method_id ): void {
		$gateway_map = WC_Payments::get_payment_gateway_map();
		if ( empty( $gateway_map ) ) {
			return;
		}

		foreach ( $gateway_map as $payment_gateway ) {
			$enabled_pm_ids = $payment_gateway->get_upe_enabled_payment_method_ids();

			// Skip if already present or not a valid array.
			if ( ! is_array( $enabled_pm_ids ) || in_array( $payment_method_id, $enabled_pm_ids, true ) ) {
				continue;
			}

			$enabled_pm_ids[] = $payment_method_id;
			$result           = $payment_gateway->update_option( 'upe_enabled_payment_method_ids', $enabled_pm_ids );

			if ( false === $result && function_exists( 'wc_get_logger' ) ) {
				$logger = wc_get_logger();
				$logger->warning(
					sprintf(
						'Failed to sync payment method %s to gateway %s',
						$payment_method_id,
						get_class( $payment_gateway )
					),
					[ 'source' => 'woopayments' ]
				);
			}
		}
	}

	/**
	 * Activate any visible promotions for a payment method being enabled via settings.
	 *
	 * This method should be called BEFORE the payment method is enabled for checkout,
	 * as visible promotions are filtered out for already-enabled payment methods.
	 *
	 * Handles its own exception catching, logging, and tracking internally.
	 *
	 * @param string $payment_method_id The payment method ID (e.g., 'klarna').
	 * @param bool   $should_enable     Whether to enable the payment method for checkout.
	 *
	 * @return bool True if a promotion was activated, false otherwise.
	 */
	public function maybe_activate_promotion_for_payment_method( string $payment_method_id, bool $should_enable = false ): bool {
		$promotion = $this->find_promotion_by_payment_method( $payment_method_id );
		if ( null === $promotion ) {
			return false;
		}

		// Send request to server to apply the promotion discount.
		// The server should also handle capability requesting if it is not already requested.
		$wcpay_request = Request\Activate_PM_Promotion::create( $promotion['id'] );
		$wcpay_request->assign_hook( 'wcpay_activate_pm_promotion_request' );
		$response = $wcpay_request->handle_rest_request();
		if ( is_wp_error( $response ) ) {
			$error_message = sprintf(
				'Server activation request failed [%s]: %s',
				$response->get_error_code(),
				$response->get_error_message()
			);
			return $this->handle_promotion_activation_failure( $payment_method_id, $promotion, $error_message );
		}

		// Enable the payment method for checkout if requested.
		if ( $should_enable && ! $this->enable_payment_method_gateway( $payment_method_id, $promotion ) ) {
			return $this->handle_promotion_activation_failure( $payment_method_id, $promotion, 'Failed to enable payment method gateway' );
		}

		// Clear the promotions cache to ensure fresh data on next fetch.
		$this->clear_cache();
		// Clear the account cache.
		if ( null !== $this->account ) {
			$this->account->clear_cache();
		}

		// Track successful activation.
		$this->tracks_event(
			Track_Events::PAYMENT_METHOD_PROMOTION_ACTIVATED,
			[
				'payment_method_id' => $payment_method_id,
				'promo_id'          => $promotion['promo_id'] ?? null,
				// The `unique_promo_id` is excluded intentionally as it's not a reliable without a specific promo type.
			]
		);

		return true;
	}

	/**
	 * Handle promotion activation failure by logging and tracking.
	 *
	 * @param string $payment_method_id The payment method ID.
	 * @param array  $promotion         The promotion data.
	 * @param string $error_message     The error message.
	 *
	 * @return bool Always returns false.
	 */
	private function handle_promotion_activation_failure( string $payment_method_id, array $promotion, string $error_message ): bool {
		// Log the error.
		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			/* translators: 1: Payment method ID, 2: Error message */
			$logger->error( sprintf( 'Failed to activate promotion for payment method %1$s: %2$s', $payment_method_id, $error_message ), [ 'source' => 'woopayments' ] );
		}

		// Track the failure.
		$this->tracks_event(
			Track_Events::PAYMENT_METHOD_PROMOTION_ACTIVATION_FAILED,
			[
				'payment_method_id' => $payment_method_id,
				'promo_id'          => $promotion['promo_id'] ?? null,
			]
		);

		return false;
	}

	/**
	 * Dismiss a promotion.
	 *
	 * @param string $id The promotion unique identifier (e.g., 'klarna-2026-promo__spotlight').
	 *
	 * @return bool True if dismissed, false if already dismissed or error.
	 */
	public function dismiss_promotion( string $id ): bool {
		// Cannot dismiss a non-existing promotion.
		$promotion = $this->find_promotion_by_id( $id );
		if ( null === $promotion ) {
			return false;
		}

		if ( ! $this->mark_promotion_dismissed( $id ) ) {
			return false;
		}

		// Track dismissal event.
		$this->tracks_event(
			Track_Events::PAYMENT_METHOD_PROMOTION_DISMISSED,
			[
				'payment_method_id' => $promotion['payment_method'] ?? null,
				'promo_id'          => $promotion['promo_id'] ?? null,
				'promo_instance_id' => $id,
			]
		);

		// Reset memo to ensure fresh data on next access.
		// The context hash change will also invalidate the transient cache.
		$this->reset_memo();

		return true;
	}

	/**
	 * Mark a promotion as dismissed in local state.
	 *
	 * @param string $id The promotion unique identifier (e.g., 'klarna-2026-promo__spotlight').
	 *
	 * @return bool True if dismissed, false if already dismissed.
	 */
	private function mark_promotion_dismissed( string $id ): bool {
		// Don't dismiss if already dismissed.
		if ( $this->is_promotion_dismissed( $id ) ) {
			return false;
		}

		$dismissals        = $this->get_promotion_dismissals();
		$dismissals[ $id ] = time();

		return update_option( self::PROMOTION_DISMISSALS_OPTION, $dismissals, false );
	}

	/**
	 * Get all promotion dismissals.
	 *
	 * @return array Associative array of [id => timestamp].
	 */
	public function get_promotion_dismissals(): array {
		return get_option( self::PROMOTION_DISMISSALS_OPTION, [] );
	}

	/**
	 * Check if a promotion has been dismissed.
	 *
	 * Being dismissed means having an entry in the dismissals option with a timestamp into the past.
	 *
	 * @param string $id The promotion unique identifier.
	 *
	 * @return bool True if dismissed, false otherwise.
	 */
	public function is_promotion_dismissed( string $id ): bool {
		$dismissals = $this->get_promotion_dismissals();

		return isset( $dismissals[ $id ] ) && is_int( $dismissals[ $id ] ) && $dismissals[ $id ] > 0 && $dismissals[ $id ] <= time();
	}

	/**
	 * Check whether the promotion data is valid.
	 * Validates required fields based on promotion type.
	 *
	 * @param mixed $promotion_data The promotion data.
	 *
	 * @return bool Whether the promotion data is valid.
	 */
	private function validate_promotion( $promotion_data ): bool {
		if ( ! is_array( $promotion_data ) || empty( $promotion_data ) ) {
			return false;
		}

		// Required fields for all promotions.
		$required_fields = [ 'id', 'promo_id', 'payment_method', 'type', 'title', 'description', 'tc_url' ];

		foreach ( $required_fields as $field ) {
			if ( ! isset( $promotion_data[ $field ] ) || ! is_string( $promotion_data[ $field ] ) ) {
				return false;
			}
		}

		// Validate type is supported.
		$valid_types = [ 'spotlight', 'badge' ];
		if ( ! in_array( $promotion_data['type'], $valid_types, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Generate a hash from the store context data.
	 *
	 * @param array $context The store context data.
	 *
	 * @return string The context hash.
	 */
	private function generate_context_hash( array $context ): string {
		// Include only certain entries in the context hash.
		// We need only discrete, user-interaction dependent data.
		// Do not include information that changes automatically (e.g., time since activation, etc.).
		return md5(
			wp_json_encode(
				[
					'dismissals' => $context['dismissals'] ?? [],
					'locale'     => $context['locale'] ?? '',
				]
			)
		);
	}

	/**
	 * Get list of valid payment method IDs from the gateway.
	 *
	 * @return array List of valid payment method IDs.
	 */
	private function get_valid_payment_method_ids(): array {
		if ( null === $this->gateway ) {
			$this->gateway = WC_Payments::get_gateway();
		}

		if ( null === $this->gateway || ! is_callable( [ $this->gateway, 'get_upe_available_payment_methods' ] ) ) {
			return [];
		}

		$result = $this->gateway->get_upe_available_payment_methods();

		return is_array( $result ) ? $result : [];
	}

	/**
	 * Get list of enabled payment method IDs.
	 *
	 * @return array List of enabled payment method IDs.
	 */
	private function get_enabled_payment_method_ids(): array {
		if ( null === $this->gateway ) {
			$this->gateway = WC_Payments::get_gateway();
		}

		if ( null === $this->gateway || ! is_callable( [ $this->gateway, 'get_upe_enabled_payment_method_ids' ] ) ) {
			return [];
		}

		$result = $this->gateway->get_upe_enabled_payment_method_ids();

		return is_array( $result ) ? $result : [];
	}

	/**
	 * Get the account fees.
	 *
	 * @return array Account fees indexed by payment method ID.
	 */
	private function get_account_fees(): array {
		if ( null === $this->account ) {
			$this->account = WC_Payments::get_account_service();
		}

		if ( null === $this->account || ! is_callable( [ $this->account, 'get_fees' ] ) ) {
			return [];
		}

		$result = $this->account->get_fees();

		return is_array( $result ) ? $result : [];
	}

	/**
	 * Check if a payment method has an active discount.
	 *
	 * @param string     $payment_method_id The payment method ID.
	 * @param array|null $account_fees      Optional. Pre-fetched account fees. If null, will be fetched.
	 *
	 * @return bool True if the payment method has an active discount.
	 */
	private function payment_method_has_active_discount( string $payment_method_id, ?array $account_fees = null ): bool {
		if ( null === $account_fees ) {
			$account_fees = $this->get_account_fees();
		}

		if ( empty( $account_fees[ $payment_method_id ] ) ) {
			return false;
		}

		$pm_fees = $account_fees[ $payment_method_id ];

		// Verify discount is a non-empty array.
		if ( ! isset( $pm_fees['discount'] ) || ! is_array( $pm_fees['discount'] ) || empty( $pm_fees['discount'] ) ) {
			return false;
		}

		// Get first discount entry regardless of array key structure.
		$first_discount = reset( $pm_fees['discount'] );
		if ( is_array( $first_discount ) && array_key_exists( 'discount', $first_discount ) && ! empty( $first_discount['discount'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Filter promotions by dismissal status, payment method validity, enabled status, discount status,
	 * and promo_id uniqueness per payment method.
	 *
	 * @param array $promotions Array of promotions.
	 *
	 * @return array Filtered promotions.
	 */
	private function filter_promotions( array $promotions ): array {
		// Pre-fetch all data needed for filtering to avoid N+1 queries.
		$enabled_pms    = $this->get_enabled_payment_method_ids();
		$valid_pms      = $this->get_valid_payment_method_ids();
		$account_fees   = $this->get_account_fees();
		$seen_promo_ids = []; // Track first promo_id per PM.
		$filtered       = [];

		foreach ( $promotions as $promotion ) {
			$id       = $promotion['id'] ?? '';
			$pm_id    = $promotion['payment_method'] ?? '';
			$promo_id = $promotion['promo_id'] ?? '';

			// Filters ordered by performance cost (cheapest first, all use pre-fetched data).

			// 1. Skip promotions for already enabled payment methods.
			if ( in_array( $pm_id, $enabled_pms, true ) ) {
				continue;
			}

			// 2. Skip invalid payment methods.
			if ( ! in_array( $pm_id, $valid_pms, true ) ) {
				continue;
			}

			// 3. Skip dismissed promotions (WP cached option).
			if ( $this->is_promotion_dismissed( $id ) ) {
				continue;
			}

			// 4. Skip promotions for payment methods that already have an active discount.
			if ( $this->payment_method_has_active_discount( $pm_id, $account_fees ) ) {
				continue;
			}

			// 5. Track first promo_id per PM - keep all surfaces for that promo_id.
			// Must be last as it has side effects (tracks seen promo_ids).
			if ( ! isset( $seen_promo_ids[ $pm_id ] ) ) {
				$seen_promo_ids[ $pm_id ] = $promo_id;
			}

			// Skip if this is a different promo_id for an already-seen PM.
			if ( $seen_promo_ids[ $pm_id ] !== $promo_id ) {
				continue;
			}

			$filtered[] = $promotion;
		}

		return $filtered;
	}

	/**
	 * Normalize promotions by applying fallbacks and deriving fields.
	 *
	 * @param array $promotions Array of promotions.
	 *
	 * @return array Normalized promotions.
	 */
	private function normalize_promotions( array $promotions ): array {
		$normalized = [];

		foreach ( $promotions as $promotion ) {
			// These fields are validated as required before normalization.
			$pm_id  = $promotion['payment_method'];
			$tc_url = $promotion['tc_url'];

			// Add derived payment_method_title if not provided.
			if ( empty( $promotion['payment_method_title'] ) ) {
				$promotion['payment_method_title'] = $this->get_payment_method_title( $pm_id );
			}

			// Apply fallback for cta_label using the final payment_method_title.
			if ( empty( $promotion['cta_label'] ) ) {
				/* translators: %s is the payment method title, e.g., "Klarna" */
				$promotion['cta_label'] = sprintf( __( 'Enable %s', 'woocommerce-payments' ), $promotion['payment_method_title'] );
			}

			// Apply type-specific sanitization BEFORE tc_label fallback.
			// This ensures we check against the sanitized description (which might lose the link).
			$promotion = $this->sanitize_promotion( $promotion );

			// Apply fallback for tc_label only if tc_url is not already in the sanitized description.
			// If tc_url is in the description, leaving tc_label empty signals frontend to not add a link.
			if ( empty( $promotion['tc_label'] ) ) {
				if ( strpos( $promotion['description'], $tc_url ) === false ) {
					$promotion['tc_label'] = __( 'See terms', 'woocommerce-payments' );
				} else {
					// Explicitly set to empty string when skipping fallback.
					$promotion['tc_label'] = '';
				}
			}

			$normalized[] = $promotion;
		}

		return $normalized;
	}

	/**
	 * Sanitize a promotion's fields based on its type.
	 *
	 * @param array $promotion The promotion data.
	 *
	 * @return array Sanitized promotion.
	 */
	private function sanitize_promotion( array $promotion ): array {
		$type = $promotion['type'] ?? '';

		// Sanitize identifier fields strictly with sanitize_key.
		$key_fields = [ 'id', 'promo_id', 'payment_method', 'type' ];
		foreach ( $key_fields as $field ) {
			if ( isset( $promotion[ $field ] ) ) {
				$promotion[ $field ] = sanitize_key( $promotion[ $field ] );
			}
		}

		// Sanitize text fields (no HTML allowed).
		$text_fields = [ 'payment_method_title', 'title', 'cta_label', 'tc_label', 'badge_text' ];
		foreach ( $text_fields as $field ) {
			if ( isset( $promotion[ $field ] ) ) {
				$promotion[ $field ] = sanitize_text_field( $promotion[ $field ] );
			}
		}

		// Normalize badge_type: ensure it's a valid type, defaulting to 'success'.
		$valid_badge_types       = [ 'primary', 'success', 'light', 'warning', 'alert' ];
		$promotion['badge_type'] = isset( $promotion['badge_type'] ) && in_array( $promotion['badge_type'], $valid_badge_types, true )
			? $promotion['badge_type']
			: 'success';

		// Sanitize URL fields.
		if ( isset( $promotion['tc_url'] ) ) {
			$promotion['tc_url'] = esc_url_raw( $promotion['tc_url'] );
		}
		if ( isset( $promotion['image'] ) ) {
			$promotion['image'] = esc_url_raw( $promotion['image'] );
		}

		// Sanitize description based on type.
		if ( isset( $promotion['description'] ) ) {
			$promotion['description'] = $this->sanitize_description( $promotion['description'], $type );
		}

		// Sanitize footnote (same as spotlight description - allows light HTML).
		if ( isset( $promotion['footnote'] ) ) {
			$promotion['footnote'] = $this->sanitize_description( $promotion['footnote'], 'spotlight' );
		}

		return $promotion;
	}

	/**
	 * Sanitize description field based on promotion type.
	 *
	 * Spotlight type allows light HTML: paragraphs, bold, italic, links, breaks.
	 * Badge type only allows links.
	 *
	 * @param string $description The description to sanitize.
	 * @param string $type        The promotion type.
	 *
	 * @return string Sanitized description.
	 */
	private function sanitize_description( string $description, string $type ): string {
		if ( 'spotlight' === $type ) {
			// Allow light HTML for spotlight: paragraphs, bold, italic, links, breaks.
			$allowed_html = [
				'p'      => [],
				'strong' => [],
				'b'      => [],
				'em'     => [],
				'i'      => [],
				'a'      => [
					'href'   => [],
					'target' => [],
					'rel'    => [],
				],
				'br'     => [],
			];
			return wp_kses( $description, $allowed_html );
		}

		if ( 'badge' === $type ) {
			// Badge type: only allow links.
			$allowed_html = [
				'a' => [
					'href'   => [],
					'target' => [],
					'rel'    => [],
				],
			];
			return wp_kses( $description, $allowed_html );
		}

		// Default: strip all HTML.
		return sanitize_text_field( $description );
	}

	/**
	 * Get the human-readable title for a payment method.
	 *
	 * @param string $payment_method_id The payment method ID.
	 *
	 * @return string The payment method title or a fallback.
	 */
	private function get_payment_method_title( string $payment_method_id ): string {
		$payment_method = WC_Payments::get_payment_method_by_id( $payment_method_id );

		if ( false !== $payment_method && method_exists( $payment_method, 'get_title' ) ) {
			return $payment_method->get_title();
		}

		// Fallback to formatted ID (e.g., 'klarna' -> 'Klarna').
		return ucfirst( str_replace( '_', ' ', $payment_method_id ) );
	}

	/**
	 * Send a Tracks event.
	 *
	 * By default Woo adds `url`, `blog_lang`, `blog_id`, `store_id`, `products_count`, and `wc_version`
	 * properties to every event.
	 *
	 * @todo This is a duplicate of the one in the WC_Payments_Account, WC_REST_Payments_Settings_Controller, and WC_Payments_Onboarding_Service classes.
	 *
	 * @param string $name       The event name.
	 * @param array  $properties Optional. The event custom properties.
	 *
	 * @return void
	 */
	private function tracks_event( string $name, array $properties = [] ) {
		if ( ! function_exists( 'wc_admin_record_tracks_event' ) ) {
			return;
		}

		// Add default properties to every event.
		$account_service = WC_Payments::get_account_service();
		$tracking_info   = $account_service ? $account_service->get_tracking_info() : [];

		$properties = array_merge(
			$properties,
			[
				'is_test_mode'      => WC_Payments::mode()->is_test(),
				'jetpack_connected' => true, // Any PM promotions require a Jetpack connection.
				'wcpay_version'     => WCPAY_VERSION_NUMBER,
				'woo_country_code'  => WC()->countries->get_base_country(),
			],
			$tracking_info ?? []
		);

		wc_admin_record_tracks_event( $name, $properties );

		Logger::info( 'Tracks event: ' . $name . ' with data: ' . wp_json_encode( WC_Payments_Utils::redact_array( $properties, [ 'woo_country_code' ] ) ) );
	}
}
