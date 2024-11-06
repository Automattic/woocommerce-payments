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

		if ( ! $this->get_cached_connect_incentive() ) {
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
		$incentive = $this->get_cached_connect_incentive();
		// Return early if there is no eligible incentive.
		if ( empty( $incentive['id'] ) ) {
			return $promo_notes;
		}

		/**
		 * Suppress psalm error because we already check that `id` exists in `is_valid_cached_incentive`.
		 *
		 * @psalm-suppress PossiblyNullArrayAccess */
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
		$incentive = $this->get_cached_connect_incentive();
		// Return early if there is no eligible incentive.
		if ( empty( $incentive['id'] ) ) {
			return $badge;
		}

		return $incentive['task_badge'] ?? $badge;
	}

	/**
	 * Filter the onboarding task additional data to add the WooPayments incentive data to it.
	 *
	 * @param ?array $additional_data The current task additional data.
	 *
	 * @return ?array The filtered task additional data.
	 */
	public function onboarding_task_additional_data( ?array $additional_data ): ?array {
		$incentive = $this->get_cached_connect_incentive();
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

		// Fingerprint the store context through a hash of certain entries.
		$store_context_hash = $this->generate_context_hash( $this->get_store_context() );

		// First, get the cache contents, if any.
		$incentive_data = $this->database_cache->get( Database_Cache::CONNECT_INCENTIVE_KEY, true );
		// Check if we need to force-refresh the cache contents.
		if ( empty( $incentive_data['context_hash'] ) || ! is_string( $incentive_data['context_hash'] )
			|| ! hash_equals( $store_context_hash, $incentive_data['context_hash'] ) ) {

			// No cache, the hash is missing, or it doesn't match. Force refresh.
			$incentive_data = $this->database_cache->get_or_add(
				Database_Cache::CONNECT_INCENTIVE_KEY,
				[ $this, 'fetch_connect_incentive_details' ],
				'is_array',
				true
			);
		}

		if ( ! $this->is_valid_cached_incentive( $incentive_data ) ) {
			return null;
		}

		return $incentive_data['incentive'];
	}

	/**
	 * Fetches eligible connect incentive details from the server.
	 *
	 * @return array|null Array of eligible incentive data or null.
	 */
	public function fetch_connect_incentive_details(): ?array {
		$store_context = $this->get_store_context();

		// Request incentive from WCPAY API.
		$url = add_query_arg(
			$store_context,
			'https://public-api.wordpress.com/wpcom/v2/wcpay/incentives'
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
					function ( array $incentive ) {
						return isset( $incentive['type'] ) && 'connect_page' === $incentive['type'];
					}
				)[0] ?? [];
			}
		}

		// Read TTL form the `cache-for` header, or default to 1 day.
		$cache_for = wp_remote_retrieve_header( $response, 'cache-for' );

		$incentive_data = [
			'incentive' => $incentive,
		];
		// Set the Time-To-Live for the incentive data.
		if ( '' !== $cache_for ) {
			$incentive_data['ttl'] = (int) $cache_for;
		} else {
			$incentive_data['ttl'] = DAY_IN_SECONDS;
		}

		// Attach the context hash to the incentive data.
		$incentive_data['context_hash'] = $this->generate_context_hash( $store_context );

		return $incentive_data;
	}

	/**
	 * Check whether the incentive data fetched from the cache are valid.
	 * Expects an array with an `incentive` entry that is an array with at least `id`, `description`, and `tc_url` keys.
	 *
	 * @param mixed $incentive_data The incentive data returned from the cache.
	 *
	 * @return bool Whether the incentive data is valid.
	 */
	public function is_valid_cached_incentive( $incentive_data ): bool {
		if ( ! is_array( $incentive_data )
			|| empty( $incentive_data['incentive'] )
			|| ! is_array( $incentive_data['incentive'] )
			|| ! isset( $incentive_data['incentive']['id'] )
			|| ! isset( $incentive_data['incentive']['description'] )
			|| ! isset( $incentive_data['incentive']['tc_url'] ) ) {

			return false;
		}

		return true;
	}

	/**
	 * Check if the WooPayments payment gateway is active and set up,
	 * or there are orders processed with it, at some moment.
	 *
	 * @return boolean
	 */
	private function has_wcpay(): bool {
		// We consider the store to have WooPayments if there is meaningful account data in the WooPayments account cache.
		// This implies that WooPayments is or was active at some point and that it was connected.
		if ( $this->has_wcpay_account_data() ) {
			return true;
		}

		// If there is at least one order processed with WooPayments, we consider the store to have WooPayments.
		if ( ! empty(
			wc_get_orders(
				[
					'payment_method' => 'woocommerce_payments',
					'return'         => 'ids',
					'limit'          => 1,
				]
			)
		) ) {
			return true;
		}

		return false;
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
	 * Get the store context to be used in determining eligibility.
	 *
	 * @return array The store context.
	 */
	private function get_store_context(): array {
		return [
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
			'has_wcpay'    => $this->has_wcpay(),
		];
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
