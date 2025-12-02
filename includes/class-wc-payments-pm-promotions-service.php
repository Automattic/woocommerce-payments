<?php
/**
 * Class WC_Payments_PM_Promotions_Service
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Core\Server\Request;

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
	 * Stores array of [promo_id => [variation_id => timestamp]].
	 *
	 * @var string
	 */
	const PROMOTION_DISMISSALS_OPTION = '_wcpay_pm_promotion_dismissals';

	/**
	 * Option key for activated promotions.
	 *
	 * @var string
	 */
	const ACTIVATED_PROMOTIONS_OPTION = '_wcpay_activated_pm_promotions';

	/**
	 * The memoized promotions to avoid fetching multiple times during a request.
	 *
	 * @var array|null
	 */
	private $promotions_memo = null;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		// No dependencies needed for now.
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
		$this->promotions_memo = null;
	}

	/**
	 * Get promotions that should be visible to the user.
	 *
	 * @return array|null The promotions or null if there is no eligible promotion.
	 */
	public function get_visible_promotions(): ?array {
		// Get all the valid promotions.
		$promotions = array_filter(
			$this->get_promotions(),
			function ( $promotion ) {
				return $this->validate_promotion( $promotion );
			}
		);

		// Go through each promotion and filter out invalid variations.
		foreach ( $promotions as $key => $promotion ) {
			$valid_variations                 = array_filter(
				$promotion['variations'],
				function ( $variation ) {
					return $this->validate_promotion_variation( $variation );
				}
			);
			$promotions[ $key ]['variations'] = $valid_variations;
		}
		// Validate promotions again after filtering variations.
		$promotions = array_filter(
			$promotions,
			function ( $promotion ) {
				return $this->validate_promotion( $promotion );
			}
		);

		// Return early if there are no promotions left.
		if ( empty( $promotions ) ) {
			return null;
		}

		return $promotions;
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
			'dismissals' => self::get_promotion_dismissals(),
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
		// TODO: Replace with actual API call when server endpoints are available.
		// $wcpay_request = Request\Get_PM_Promotions::create();
		// $wcpay_request->set_store_context_params( $store_context );
		// $response = $wcpay_request->handle_rest_request();
		// Return mock data for testing.
		$response = $this->get_mock_promotions_data();

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
	 * @param string $identifier   The promotion identifier.
	 * @param bool   $accept_terms Whether the user accepted the terms.
	 *
	 * @return array The activation response.
	 */
	public function activate_promotion( string $identifier, bool $accept_terms = true ): array {
		// TODO: Replace with actual API call when server endpoints are available.
		// $wcpay_request = Request\Activate_Promotion::create( $identifier );.
		// $wcpay_request->set_accept_terms( $accept_terms );.
		// $wcpay_request->assign_hook( 'wcpay_activate_promotion_request' );.
		// $response = $wcpay_request->handle_rest_request();.

		// Return mock success response.
		$response = [
			'success'    => true,
			'identifier' => $identifier,
			'status'     => 'active',
		];

		// Clear cache and update local state.
		$this->clear_cache();
		$this->mark_promotion_activated( $identifier );

		return $response;
	}

	/**
	 * Dismiss a promotion variation.
	 *
	 * @param string $identifier   The promotion identifier.
	 * @param string $variation_id The variation identifier.
	 *
	 * @return array The dismissal response.
	 */
	public function dismiss_promotion( string $identifier, string $variation_id ): array {
		// TODO: Replace with actual API call when server endpoints are available.
		// $wcpay_request = Request\Dismiss_Promotion::create( $identifier, $variation_id );.
		// $wcpay_request->assign_hook( 'wcpay_dismiss_promotion_request' );.
		// $response = $wcpay_request->handle_rest_request();.

		// Return mock success response.
		$response = [
			'success'      => true,
			'identifier'   => $identifier,
			'variation_id' => $variation_id,
			'status'       => 'dismissed',
		];

		// Clear cache and update local state.
		$this->clear_cache();
		$this->mark_variation_dismissed( $identifier, $variation_id );

		return $response;
	}

	/**
	 * Filter variations based on config and dismissal history.
	 *
	 * @param array $promotions Array of promotions with variations.
	 *
	 * @return array Filtered promotions array.
	 */
	private function filter_variations_by_dismissals( array $promotions ): array {
		foreach ( $promotions as &$promotion ) {
			if ( empty( $promotion['variations'] ) ) {
				continue;
			}

			$promo_id             = $promotion['promo_id'];
			$variation_dismissals = self::get_promotion_variation_dismissals( $promo_id );

			// Group variations by type to apply type-specific config.
			$variations_by_type = [];
			foreach ( $promotion['variations'] as $variation ) {
				$type = $variation['type'] ?? 'default';
				if ( ! isset( $variations_by_type[ $type ] ) ) {
					$variations_by_type[ $type ] = [];
				}
				$variations_by_type[ $type ][] = $variation;
			}

			$filtered_variations = [];

			foreach ( $variations_by_type as $type => $type_variations ) {
				// Get config for this variation type.
				// Defaults: 1 dismissal allowed, no delay (must configure to show multiple variations).
				$type_config    = $promotion['config'][ $type ] ?? [];
				$max_dismissals = $type_config['max_dismissals'] ?? 1;
				$reshow_delay   = $type_config['reshow_delay_days'] ?? 0;
				$delay_seconds  = $reshow_delay * DAY_IN_SECONDS;

				// Count dismissals for variations of this type.
				$type_dismissals       = 0;
				$most_recent_dismissal = 0;
				foreach ( $type_variations as $variation ) {
					$dismissed_at = $variation_dismissals[ $variation['id'] ] ?? null;
					if ( null !== $dismissed_at ) {
						++$type_dismissals;
						if ( $dismissed_at > $most_recent_dismissal ) {
							$most_recent_dismissal = $dismissed_at;
						}
					}
				}

				// Check if max dismissals reached for this type.
				if ( $type_dismissals >= $max_dismissals ) {
					continue;
				}

				// Check if still in delay period.
				if ( $most_recent_dismissal > 0 && $delay_seconds > 0 ) {
					$time_since_dismissal = time() - $most_recent_dismissal;
					if ( $time_since_dismissal < $delay_seconds ) {
						continue;
					}
				}

				// Find first non-dismissed variation of this type.
				foreach ( $type_variations as $variation ) {
					$dismissed_at = $variation_dismissals[ $variation['id'] ] ?? null;
					if ( null === $dismissed_at ) {
						$filtered_variations[] = $variation;
						break;
					}
				}
			}

			$promotion['variations'] = $filtered_variations;
		}
		unset( $promotion );

		return $promotions;
	}

	/**
	 * Get mock promotions data for testing.
	 * TODO: Remove this method when server endpoints are available.
	 *
	 * @return array Mock promotions data (array of promotions).
	 */
	private function get_mock_promotions_data(): array {
		// Mock available promotions - flat structure, no nested variations.
		$promotions = [
			[
				'id'             => 'klarna-2026-promo__spotlight',
				'promo_id'       => 'klarna-2026-promo',
				'payment_method' => 'klarna',
				'type'           => 'spotlight',
				'title'          => 'Zero Processing Fees for 90 Days',
				'description'    => 'Save on every Klarna transaction with 0% processing fees for 90 days from activation.',
				'cta_label'      => 'Enable Klarna',
				'tc_url'         => 'https://woocommerce.com/terms',
				'tc_label'       => 'See terms',
				'footnote'       => '*Offer valid for new activations only.',
				'image'          => '',
			],
			[
				'id'             => 'klarna-2026-promo__badge',
				'promo_id'       => 'klarna-2026-promo',
				'payment_method' => 'klarna',
				'type'           => 'badge',
				'title'          => 'Zero fees for 90 days',
				'description'    => 'Enable Klarna and pay no processing fees.',
				'tc_url'         => 'https://woocommerce.com/terms',
			],
			[
				'id'             => 'affirm-2026-promo__spotlight',
				'promo_id'       => 'affirm-2026-promo',
				'payment_method' => 'affirm',
				'type'           => 'spotlight',
				'title'          => '2% Cashback on Affirm Transactions',
				'description'    => 'Earn cashback on all Affirm payments for 60 days.',
				'tc_url'         => 'https://woocommerce.com/terms',
			],
		];

		return [
			'response' => [ 'code' => 200 ],
			'body'     => wp_json_encode( $promotions ),
		];
	}

	/**
	 * Mark a promotion variation as dismissed in local state.
	 *
	 * @param string $promo_id     The promotion identifier.
	 * @param string $variation_id The variation identifier.
	 *
	 * @return void
	 */
	private function mark_variation_dismissed( string $promo_id, string $variation_id ): void {
		$dismissals = self::get_promotion_dismissals();

		if ( ! isset( $dismissals[ $promo_id ] ) ) {
			$dismissals[ $promo_id ] = [];
		}

		$dismissals[ $promo_id ][ $variation_id ] = time();
		update_option( self::PROMOTION_DISMISSALS_OPTION, $dismissals, false );
	}

	/**
	 * Mark a promotion as activated in local state.
	 *
	 * @param string $identifier The promotion identifier.
	 *
	 * @return void
	 */
	private function mark_promotion_activated( string $identifier ): void {
		$activated                = self::get_activated_promotions();
		$activated[ $identifier ] = time();
		update_option( self::ACTIVATED_PROMOTIONS_OPTION, $activated, false );
	}

	/**
	 * Get all promotion dismissals.
	 *
	 * @return array Associative array of [promo_id => [variation_id => timestamp]].
	 */
	public static function get_promotion_dismissals(): array {
		return get_option( self::PROMOTION_DISMISSALS_OPTION, [] );
	}

	/**
	 * Get list of activated promotions with timestamps.
	 *
	 * @return array Associative array of promotion identifiers to activation timestamps.
	 */
	public static function get_activated_promotions(): array {
		return get_option( self::ACTIVATED_PROMOTIONS_OPTION, [] );
	}

	/**
	 * Get dismissal timestamp for a specific variation.
	 *
	 * @param string $promo_id     The promotion identifier.
	 * @param string $variation_id The variation identifier.
	 *
	 * @return int|null Dismissal timestamp, or null if not dismissed.
	 */
	public static function get_variation_dismissal_time( string $promo_id, string $variation_id ): ?int {
		$dismissals = self::get_promotion_dismissals();
		return $dismissals[ $promo_id ][ $variation_id ] ?? null;
	}

	/**
	 * Get all dismissal timestamps for a promotion.
	 *
	 * @param string $promo_id The promotion identifier.
	 *
	 * @return array Array of [variation_id => timestamp].
	 */
	public static function get_promotion_variation_dismissals( string $promo_id ): array {
		$dismissals = self::get_promotion_dismissals();
		return $dismissals[ $promo_id ] ?? [];
	}

	/**
	 * Check if a promotion has been activated.
	 *
	 * @param string $identifier The promotion identifier.
	 *
	 * @return bool True if activated, false otherwise.
	 */
	public static function is_promotion_activated( string $identifier ): bool {
		$activated = self::get_activated_promotions();
		return isset( $activated[ $identifier ] );
	}

	/**
	 * Get the activation timestamp for a promotion.
	 *
	 * @param string $identifier The promotion identifier.
	 *
	 * @return int|null The activation timestamp, or null if not activated.
	 */
	public static function get_promotion_activation_time( string $identifier ): ?int {
		$activated = self::get_activated_promotions();
		return $activated[ $identifier ] ?? null;
	}

	/**
	 * Check whether the promotion data is valid.
	 * Expects an array with at least `promo_id` and `variations`.
	 * Variations must be a non-empty array.
	 *
	 * @param mixed $promotion_data The promotion data.
	 *
	 * @return bool Whether the promotion data is valid.
	 */
	private function validate_promotion( $promotion_data ): bool {
		if ( ! is_array( $promotion_data )
			|| empty( $promotion_data )
			|| ! isset( $promotion_data['promo_id'] )
			|| empty( $promotion_data['variations'] )
			|| ! is_array( $promotion_data['variations'] ) ) {

			return false;
		}

		return true;
	}

	/**
	 * Check whether the promotion variation data is valid.
	 * Expects an array with at least `id`, `type`, `description`, and `tc_url`.
	 *
	 * @param mixed $variation_data The promotion variation data.
	 *
	 * @return bool Whether the promotion variation data is valid.
	 */
	private function validate_promotion_variation( $variation_data ): bool {
		if ( ! is_array( $variation_data )
			|| empty( $variation_data )
			|| ! isset( $variation_data['id'] )
			|| ! isset( $variation_data['type'] )
			|| ! isset( $variation_data['description'] )
			|| ! isset( $variation_data['tc_url'] ) ) {

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
}
