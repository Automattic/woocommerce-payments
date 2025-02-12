<?php
/**
 * Class WC_Payments_Incentives_Service
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Automattic\WooCommerce\Admin\WCAdminHelper;
use WCPay\Database_Cache;

/**
 * Class handling WooPayments incentives related business logic.
 */
class WC_Payments_Incentives_Service {
	const PREFIX = 'woocommerce_admin_pes_incentive_';

	/**
	 * The transient name for incentives cache.
	 *
	 * @var string
	 */
	private $cache_transient_name;

	/**
	 * The transient name used to store the value for if store has orders.
	 *
	 * @var string
	 */
	private $store_has_orders_transient_name;

	/**
	 * The option name used to store the value for if store had WooPayments in use.
	 *
	 * @var string
	 */
	private $store_had_woopayments_option_name;

	/**
	 * The memoized incentives to avoid fetching multiple times during a request.
	 *
	 * @var array|null
	 */
	private $incentives_memo = null;

	/**
	 * Cache util for managing onboarding data.
	 *
	 * @var Database_Cache
	 */
	private $database_cache;

	/**
	 * Class constructor
	 *
	 * @param Database_Cache $database_cache      Database cache util.
	 */
	public function __construct( Database_Cache $database_cache ) {
		$this->database_cache = $database_cache;

		// We use the same transient keys as the WC core suggestion incentives.
		// This way we can reuse the same cache for the incentives across the store admin.
		// @see \Automattic\WooCommerce\Internal\Admin\Suggestions\Incentives\WooPayments.
		$this->cache_transient_name              = self::PREFIX . 'woopayments_cache';
		$this->store_has_orders_transient_name   = self::PREFIX . 'woopayments_store_has_orders';
		$this->store_had_woopayments_option_name = self::PREFIX . 'woopayments_store_had_woopayments';
	}

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
		delete_transient( $this->cache_transient_name );
		$this->reset_memo();
	}

	/**
	 * Reset the memoized incentives.
	 *
	 * This is useful for testing purposes.
	 */
	public function reset_memo() {
		$this->incentives_memo = null;
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

		// Get all the valid incentives.
		$incentives = array_filter(
			$this->get_incentives( $country_code ),
			function ( $incentive ) {
				return $this->validate_incentive( $incentive );
			}
		);

		// Filter by the 'connect_page' incentive type.
		$incentives = array_filter(
			$incentives,
			function ( $incentive ) {
				return 'connect_page' === $incentive['type'];
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
	 * Fetches and caches eligible incentives from the WooPayments API.
	 *
	 * @param string $country_code The business location country code to get incentives for.
	 *
	 * @return array List of eligible incentives.
	 */
	private function get_incentives( string $country_code ): array {
		if ( isset( $this->incentives_memo ) ) {
			return $this->incentives_memo;
		}

		// Get the cached data.
		$cache = get_transient( $this->cache_transient_name );

		// If the cached data is not expired, and it's a WP_Error,
		// it means there was an API error previously, and we should not retry just yet.
		if ( is_wp_error( $cache ) ) {
			// Initialize the in-memory cache and return it.
			$this->incentives_memo = [];

			return $this->incentives_memo;
		}

		// Gather the store context data.
		/**
		 * The WC stub uses 'number' as the return type.
		 *
		 * @psalm-suppress UndefinedDocblockClass
		 */
		$store_context = [
			'country'      => $country_code,
			// Store locale, e.g. `en_US`.
			'locale'       => get_locale(),
			// WooCommerce store active for duration in seconds.
			'active_for'   => WCAdminHelper::get_wcadmin_active_for_in_seconds(),
			'has_orders'   => $this->has_orders(),
			// Whether the store has at least one payment gateway enabled.
			'has_payments' => ! empty( WC()->payment_gateways()->get_available_payment_gateways() ),
			'has_wcpay'    => $this->has_wcpay(),
		];

		// Fingerprint the store context through a hash of certain entries.
		$store_context_hash = $this->generate_context_hash( $store_context );

		// Use the transient cached incentive if it exists, it is not expired,
		// and the store context hasn't changed since we last requested from the WooPayments API (based on context hash).
		if ( false !== $cache
			&& ! empty( $cache['context_hash'] ) && is_string( $cache['context_hash'] )
			&& hash_equals( $store_context_hash, $cache['context_hash'] ) ) {

			// We have a store context hash, and it matches with the current context one.
			// We can use the cached incentive data.
			// Store the incentives in the in-memory cache and return them.
			$this->incentives_memo = $cache['incentives'] ?? [];

			return $this->incentives_memo;
		}

		// By this point, we have an expired transient or the store context has changed.
		// Query for incentives by calling the WooPayments API.
		$url = add_query_arg(
			$store_context,
			'https://public-api.wordpress.com/wpcom/v2/wcpay/incentives',
		);

		$response = wp_remote_get(
			$url,
			[
				'user-agent' => 'WCPay/' . WCPAY_VERSION_NUMBER . '; ' . get_bloginfo( 'url' ),
			]
		);

		// Return early if there is an error, waiting 6 hours before the next attempt.
		if ( is_wp_error( $response ) ) {
			// Store a trimmed down, lightweight error.
			$error = new \WP_Error(
				$response->get_error_code(),
				$response->get_error_message(),
				wp_remote_retrieve_response_code( $response )
			);
			// Store the error in the transient so we know this is due to an API error.
			set_transient( $this->cache_transient_name, $error, HOUR_IN_SECONDS * 6 );
			// Initialize the in-memory cache and return it.
			$this->incentives_memo = [];

			return $this->incentives_memo;
		}

		$cache_for = wp_remote_retrieve_header( $response, 'cache-for' );
		// Initialize the in-memory cache.
		$this->incentives_memo = [];

		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			// Decode the results, falling back to an empty array.
			$results = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

			// Store incentives in the in-memory cache.
			$this->incentives_memo = $results;
		}

		// Skip transient cache if `cache-for` header equals zero.
		if ( '0' === $cache_for ) {
			// If we have a transient cache that is not expired, delete it so there are no leftovers.
			if ( false !== $cache ) {
				delete_transient( $this->cache_transient_name );
			}

			return $this->incentives_memo;
		}

		// Store incentive in transient cache (together with the context hash) for the given number of seconds
		// or 1 day in seconds. Also attach a timestamp to the transient data so we know when we last fetched.
		set_transient(
			$this->cache_transient_name,
			[
				'incentives'   => $this->incentives_memo,
				'context_hash' => $store_context_hash,
				'timestamp'    => time(),
			],
			! empty( $cache_for ) ? (int) $cache_for : DAY_IN_SECONDS
		);

		return $this->incentives_memo;
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

	/**
	 * Check if WooPayments payment gateway was active and set up at some point,
	 * or there are orders processed with it, at some moment.
	 *
	 * @return boolean Whether the store has WooPayments.
	 */
	private function has_wcpay(): bool {
		// First, get the stored value, if it exists.
		// This way we avoid costly DB queries and API calls.
		// Basically, we only want to know if WooPayments was in use in the past.
		// Since the past can't be changed, neither can this value.
		$had_wcpay = get_option( $this->store_had_woopayments_option_name );
		if ( false !== $had_wcpay ) {
			return filter_var( $had_wcpay, FILTER_VALIDATE_BOOLEAN );
		}

		// We need to determine the value.
		// Start with the assumption that the store didn't have WooPayments in use.
		$had_wcpay = false;

		// We consider the store to have WooPayments if there is meaningful account data in the WooPayments account cache.
		// This implies that WooPayments was active at some point and that it was connected.
		if ( $this->has_wcpay_account_data() ) {
			$had_wcpay = true;
		}

		// If there is at least one order processed with WooPayments, we consider the store to have WooPayments.
		if ( false === $had_wcpay && ! empty(
			wc_get_orders(
				[
					'payment_method' => 'woocommerce_payments',
					'return'         => 'ids',
					'limit'          => 1,
					'orderby'        => 'none',
				]
			)
		) ) {
			$had_wcpay = true;
		}

		// Store the value for future use.
		update_option( $this->store_had_woopayments_option_name, $had_wcpay ? 'yes' : 'no' );

		return $had_wcpay;
	}

	/**
	 * Check if there is meaningful data in the WooPayments account cache.
	 *
	 * @return boolean
	 */
	private function has_wcpay_account_data(): bool {
		$account_data = $this->database_cache->get( Database_Cache::ACCOUNT_KEY );
		if ( ! empty( $account_data['account_id'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if the store has any paid orders.
	 *
	 * Currently, we look at the past 90 days and only consider orders
	 * with status `wc-completed`, `wc-processing`, or `wc-refunded`.
	 *
	 * @return boolean Whether the store has any paid orders.
	 */
	private function has_orders(): bool {
		// First, get the stored value, if it exists.
		// This way we avoid costly DB queries and API calls.
		$has_orders = get_transient( $this->store_has_orders_transient_name );
		if ( false !== $has_orders ) {
			return filter_var( $has_orders, FILTER_VALIDATE_BOOLEAN );
		}

		// We need to determine the value.
		// Start with the assumption that the store doesn't have orders in the timeframe we look at.
		$has_orders = false;
		// By default, we will check for new orders every 6 hours.
		$expiration = 6 * HOUR_IN_SECONDS;

		// Get the latest completed, processing, or refunded order.
		$latest_order = wc_get_orders(
			[
				'status'  => [ 'wc-completed', 'wc-processing', 'wc-refunded' ],
				'limit'   => 1,
				'orderby' => 'date',
				'order'   => 'DESC',
			]
		);
		if ( ! empty( $latest_order ) ) {
			$latest_order = reset( $latest_order );
			// If the latest order is within the timeframe we look at, we consider the store to have orders.
			// Otherwise, it clearly doesn't have orders.
			if ( $latest_order instanceof WC_Abstract_Order
				&& strtotime( (string) $latest_order->get_date_created() ) >= strtotime( '-90 days' ) ) {

				$has_orders = true;

				// For ultimate efficiency, we will check again after 90 days from the latest order
				// because in all that time we will consider the store to have orders regardless of new orders.
				$expiration = strtotime( (string) $latest_order->get_date_created() ) + 90 * DAY_IN_SECONDS - time();
			}
		}

		// Store the value for future use.
		set_transient( $this->store_has_orders_transient_name, $has_orders ? 'yes' : 'no', $expiration );

		return $has_orders;
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
		// Entries like `active_for` have no place in the hash generation since they change automatically.
		return md5(
			wp_json_encode(
				[
					'country'      => $context['country'] ?? '',
					'locale'       => $context['locale'] ?? '',
					'has_orders'   => $context['has_orders'] ?? false,
					'has_payments' => $context['has_payments'] ?? false,
					'has_wcpay'    => $context['has_wcpay'] ?? false,
				]
			)
		);
	}
}
