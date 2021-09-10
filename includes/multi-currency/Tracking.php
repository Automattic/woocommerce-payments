<?php
/**
 * Class Tracking
 *
 * @package WooCommerce\Payments\MultiCurrency
 */

namespace WCPay\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls Multi Currency Tracking functionality.
 */
class Tracking {
	/**
	 * MultiCurrency class.
	 *
	 * @var MultiCurrency
	 */
	private $multi_currency;

	/**
	 * Class constructor.
	 *
	 * @param MultiCurrency $multi_currency MultiCurrency class.
	 */
	public function __construct( MultiCurrency $multi_currency ) {
		$this->multi_currency = $multi_currency;

		add_filter( 'woocommerce_tracker_data', [ $this, 'add_tracker_data' ], 50 );
	}

	/**
	 * Add our data to the tracking data from WC core.
	 *
	 * @param array $data The array of data WC core has already built.
	 *
	 * @return array Our modified data.
	 */
	public function add_tracker_data( array $data ): array {
		$data[ $this->multi_currency->id ] = [
			'enabled_currencies' => $this->get_enabled_currencies(),
			'default_currency'   => $this->get_currency_data_array( $this->multi_currency->get_default_currency() ),
			'order_count'        => $this->get_mc_order_count(),
		];
		return $data;
	}

	/**
	 * Returns an assoc array of the data we want from a Currency object.
	 *
	 * @param Currency $currency The Currency object we want data from.
	 *
	 * @return array Assoc array with the Currency data.
	 */
	private function get_currency_data_array( Currency $currency ): array {
		return [
			'code' => $currency->get_code(),
			'name' => html_entity_decode( $currency->get_name() ),
		];
	}

	/**
	 * Gets the enabled currencies as an associative array. Excludes the store/default currency.
	 *
	 * @return array Array of currencies, or empty array if none found.
	 */
	private function get_enabled_currencies(): array {
		$enabled_currencies = $this->multi_currency->get_enabled_currencies();
		$default_currency   = $this->multi_currency->get_default_currency();
		unset( $enabled_currencies[ $default_currency->get_code() ] );
		$enabled_array = [];

		foreach ( $enabled_currencies as $currency ) {
			$enabled_array[ $currency->get_code() ] = $this->get_currency_data_array( $currency );
		}

		return $enabled_array;
	}

	/**
	 * Queries the postmeta table to see how many orders have been made using Multi-Currency.
	 *
	 * @return int Result count.
	 */
	private function get_mc_order_count(): int {
		global $wpdb;

		$results = $wpdb->get_results( "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_wcpay_multi_currency_order_exchange_rate'" );
		if ( is_array( $results ) ) {
			return count( $results );
		}

		return 0;
	}
}
