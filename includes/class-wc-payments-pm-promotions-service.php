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
	 * Stores array of [id => timestamp].
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
	 * WC_Payment_Gateway_WCPay instance.
	 *
	 * @var WC_Payment_Gateway_WCPay|null
	 */
	private $gateway;

	/**
	 * Class constructor.
	 *
	 * @param WC_Payment_Gateway_WCPay|null $gateway Optional gateway instance.
	 */
	public function __construct( $gateway = null ) {
		$this->gateway = $gateway;
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
		// Promotions are only visible to users who can manage WooCommerce (aka act on the promotions).
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return null;
		}

		$promotions = $this->get_promotions();

		// Validate each promotion's structure.
		$promotions = array_filter(
			$promotions,
			function ( $promotion ) {
				return $this->validate_promotion( $promotion );
			}
		);

		// Filter by PM validity, enabled status, and first promo_id per PM.
		$promotions = $this->filter_promotions( $promotions );

		// Normalize the promotions (apply fallbacks, derive fields).
		$promotions = $this->normalize_promotions( $promotions );

		// Return early if there are no promotions left.
		if ( empty( $promotions ) ) {
			return null;
		}

		return array_values( $promotions );
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
	 * Dismiss a promotion.
	 *
	 * @param string $id The promotion unique identifier (e.g., 'klarna-2026-promo__spotlight').
	 *
	 * @return array The dismissal response.
	 */
	public function dismiss_promotion( string $id ): array {
		// Extract promo_id from id for the API endpoint (e.g., 'klarna-2026-promo__spotlight' -> 'klarna-2026-promo').
		$promo_id = explode( '__', $id )[0];

		// Return mock success response (server-side dismissal tracking not implemented yet).
		$response = [
			'success'  => true,
			'id'       => $id,
			'promo_id' => $promo_id,
			'status'   => 'dismissed',
		];

		// Update local state. Cache invalidation happens automatically via context hash
		// when dismissals change - the next get_promotions() call will detect the hash
		// mismatch and refetch from the server.
		$this->reset_memo();
		$this->mark_promotion_dismissed( $id );

		return $response;
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
				'tc_label'       => 'Learn more',
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
	 * Mark a promotion as dismissed in local state.
	 *
	 * @param string $id The promotion unique identifier (e.g., 'klarna-2026-promo__spotlight').
	 *
	 * @return void
	 */
	private function mark_promotion_dismissed( string $id ): void {
		$dismissals        = self::get_promotion_dismissals();
		$dismissals[ $id ] = time();
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
	 * @return array Associative array of [id => timestamp].
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
	 * Check if a promotion has been dismissed.
	 *
	 * @param string $id The promotion unique identifier.
	 *
	 * @return bool True if dismissed, false otherwise.
	 */
	public static function is_promotion_dismissed( string $id ): bool {
		$dismissals = self::get_promotion_dismissals();
		return isset( $dismissals[ $id ] );
	}

	/**
	 * Get dismissal timestamp for a specific promotion.
	 *
	 * @param string $id The promotion unique identifier.
	 *
	 * @return int|null Dismissal timestamp, or null if not dismissed.
	 */
	public static function get_promotion_dismissal_time( string $id ): ?int {
		$dismissals = self::get_promotion_dismissals();
		return $dismissals[ $id ] ?? null;
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

		if ( null === $this->gateway ) {
			return [];
		}

		return $this->gateway->get_upe_available_payment_methods();
	}

	/**
	 * Check if a payment method ID is valid.
	 *
	 * @param string $payment_method_id The payment method ID to check.
	 *
	 * @return bool Whether the payment method ID is valid.
	 */
	private function is_valid_payment_method( string $payment_method_id ): bool {
		return in_array( $payment_method_id, $this->get_valid_payment_method_ids(), true );
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

		if ( null === $this->gateway ) {
			return [];
		}

		return $this->gateway->get_upe_enabled_payment_method_ids();
	}

	/**
	 * Filter promotions by payment method validity and enabled status.
	 * Also keeps only the first promo_id per payment method.
	 *
	 * @param array $promotions Array of promotions.
	 *
	 * @return array Filtered promotions.
	 */
	private function filter_promotions( array $promotions ): array {
		$enabled_pms    = $this->get_enabled_payment_method_ids();
		$seen_promo_ids = []; // Track first promo_id per PM.
		$filtered       = [];

		foreach ( $promotions as $promotion ) {
			$pm_id    = $promotion['payment_method'] ?? '';
			$promo_id = $promotion['promo_id'] ?? '';

			// Skip invalid payment methods.
			if ( ! $this->is_valid_payment_method( $pm_id ) ) {
				continue;
			}

			// Skip already enabled payment methods.
			if ( in_array( $pm_id, $enabled_pms, true ) ) {
				continue;
			}

			// Track first promo_id per PM - keep all surfaces for that promo_id.
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
			$pm_id    = $promotion['payment_method'] ?? '';
			$pm_title = $this->get_payment_method_title( $pm_id );

			// Add derived payment_method_title.
			$promotion['payment_method_title'] = $pm_title;

			// Apply fallback for cta_label.
			if ( empty( $promotion['cta_label'] ) ) {
				/* translators: %s is the payment method title, e.g., "Klarna" */
				$promotion['cta_label'] = sprintf( __( 'Enable %s', 'woocommerce-payments' ), $pm_title );
			}

			// Apply fallback for tc_label.
			if ( empty( $promotion['tc_label'] ) ) {
				$promotion['tc_label'] = __( 'See terms', 'woocommerce-payments' );
			}

			// Apply type-specific sanitization.
			$promotion = $this->sanitize_promotion( $promotion );

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
		$text_fields = [ 'payment_method_title', 'title', 'cta_label', 'tc_label' ];
		foreach ( $text_fields as $field ) {
			if ( isset( $promotion[ $field ] ) ) {
				$promotion[ $field ] = sanitize_text_field( $promotion[ $field ] );
			}
		}

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
}
