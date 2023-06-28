<?php
/**
 * Class WC_Payments_Incentives_Service
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Database_Cache;

/**
 * Class handling onboarding related business logic.
 */
class WC_Payments_Incentives_Service {

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

		add_action( 'admin_menu', [ $this, 'add_payments_menu_badge' ] );
		add_filter( 'woocommerce_admin_allowed_promo_notes', [ $this, 'allowed_promo_notes' ] );
	}

	/**
	 * Add badge to payments menu if there is an eligible incentive.
	 *
	 * @return void
	 */
	public function add_payments_menu_badge(): void {
		global $menu;

		if ( ! $this->get_cached_connect_incentive() ) {
			return;
		}

		foreach ( $menu as $index => $menu_item ) {
			if ( 'wc-admin&path=/payments/connect' === $menu_item[2] ) {
				$menu[ $index ][0] .= WC_Payments_Admin::MENU_NOTIFICATION_BADGE; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
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
		if ( $this->get_cached_connect_incentive() ) {
			/**
			 * Suppress psalm error because we already check that `id` exists in `is_valid_cached_incentive`.
			 *
			 * @psalm-suppress PossiblyNullArrayAccess */
			$promo_notes[] = $this->get_cached_connect_incentive()['id'];
		}

		return $promo_notes;
	}

	/**
	 * Gets and caches eligible connect incentive from the server.
	 *
	 * @param bool $force_refresh Forces data to be fetched from the server, rather than using the cache.
	 *
	 * @return array|null List of incentives or null.
	 */
	public function get_cached_connect_incentive( bool $force_refresh = false ): ?array {
		// Return early if there is an account connected.
		if ( WC_Payments::get_account_service()->is_stripe_connected() ) {
			return null;
		}

		// Return early if the country is not supported.
		if ( ! array_key_exists( WC()->countries->get_base_country(), WC_Payments_Utils::supported_countries() ) ) {
			return null;
		}

		$incentive = $this->database_cache->get_or_add(
			Database_Cache::CONNECT_INCENTIVE_KEY,
			[ $this, 'fetch_connect_incentive' ],
			'is_array',
			$force_refresh,
		);

		if ( ! $this->is_valid_cached_incentive( $incentive ) ) {
			return null;
		}

		return $incentive;
	}

	/**
	 * Fetches eligible connect incentive from the server.
	 *
	 * @return array|null Array of eligible incentive data or null.
	 */
	public function fetch_connect_incentive(): ?array {
		// Request incentive from WCPAY API.
		$url = add_query_arg(
			[
				// Store ISO-2 country code, e.g. `US`.
				'country'      => WC()->countries->get_base_country(),
				// Store locale, e.g. `en_US`.
				'locale'       => get_locale(),
				// WooCommerce active for duration in seconds.
				'active_for'   => time() - get_option( 'woocommerce_admin_install_timestamp', time() ),
				// Whether the store has paid orders in the last 90 days.
				'has_orders'   => ! empty(
					wc_get_orders(
						[
							'status'       => [ 'wc-completed', 'wc-processing' ],
							'date_created' => '>=' . strtotime( '-90 days' ),
							'return'       => 'ids',
							'limit'        => 1,
						]
					)
				),
				// Whether the store has at least one payment gateway enabled.
				'has_payments' => ! empty( WC()->payment_gateways()->get_available_payment_gateways() ),
			],
			'https://public-api.wordpress.com/wpcom/v2/wcpay/incentives',
		);

		$response = wp_remote_get(
			$url,
			[
				'user-agent' => 'WCPay/' . WCPAY_VERSION_NUMBER . '; ' . get_bloginfo( 'url' ),
			]
		);

		// Return early if there is an error.
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$incentive = [];

		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			// Decode the results, falling back to an empty array.
			$results = json_decode( wp_remote_retrieve_body( $response ), true );

			// Find a `connect_page` incentive.
			if ( ! empty( $results ) ) {
				$incentive = array_filter(
					$results,
					function( array $incentive ) {
						return isset( $incentive['type'] ) && 'connect_page' === $incentive['type'];
					}
				)[0] ?? [];
			}
		}

		// Read TTL form the `cache-for` header, or default to 1 day.
		$cache_for = wp_remote_retrieve_header( $response, 'cache-for' );

		if ( '' !== $cache_for ) {
			$incentive['ttl'] = (int) $cache_for;
		} else {
			$incentive['ttl'] = DAY_IN_SECONDS;
		}

		return $incentive;
	}

	/**
	 * Check whether the incentive fetched from the cache is valid.
	 * Expects an array with at least `id`, `description`, and `tc_url` keys.
	 *
	 * @param mixed $incentive The incentive returned from the cache.
	 *
	 * @return bool Whether the incentive is valid.
	 */
	public function is_valid_cached_incentive( $incentive ): bool {
		if ( ! is_array( $incentive ) || empty( $incentive ) || ! isset( $incentive['id'] ) || ! isset( $incentive['description'] ) || ! isset( $incentive['tc_url'] ) ) {
			return false;
		}

		return true;
	}

}
