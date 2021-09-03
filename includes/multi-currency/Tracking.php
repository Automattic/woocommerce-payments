<?php
/**
 * Class Tracking
 *
 * @package WooCommerce\Payments\MultiCurrency
 */

namespace WCPay\MultiCurrency;

use WC_Payments_Account;

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls Multi Currency Tracking functionality.
 */
class Tracking {
	/**
	 * Multi-Currency id.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Tracking option id.
	 *
	 * @var string
	 */
	private $option_id = 'wcpay_multi_currency_tracking_data';

	/**
	 * Tracking prefix.
	 *
	 * @var string
	 */
	private $prefix = 'wcadmin_wcpay_multi_currency_';

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
		$this->id             = $this->multi_currency->id;

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
		$data[ $this->id ] = [
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
			'code'            => $currency->get_code(),
			'rate'            => $currency->get_rate(),
			'name'            => html_entity_decode( $currency->get_name() ),
			'id'              => $currency->get_id(),
			'is_default'      => $currency->get_is_default(),
			'is_zero_decimal' => $currency->get_is_zero_decimal(),
			'last_updated'    => $currency->get_last_updated(),
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

		$results = $wpdb->get_results( "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_wcpay_multi_currency_owwrder_exchange_rate'" );
		if ( is_array( $results ) ) {
			return count( $results );
		}

		return 0;
	}

	/**
	 * Get the tracking data from the database.
	 *
	 * @param string $option_name The option being queried.
	 *
	 * @return mixed Returns the stored data, or false on empty.
	 */
	private function get_tracking_data( $option_name = null ) {
		$data = get_option( $this->option_id, [] );

		if ( isset( $data[ $option_name ] ) ) {
			return $data[ $option_name ];
		}

		return false;
	}

	/**
	 * Set the tracking data to the database.
	 *
	 * @param string $option_name The option being set.
	 * @param mixed  $value The data being set.
	 *
	 * @return void
	 */
	private function set_tracking_data( $option_name = null, $value = null ) {
		if ( null === $option_name || null === $value ) {
			return;
		}

		$data                 = get_option( $this->option_id, [] );
		$data[ $option_name ] = $value;
		update_option( $this->option_id, $data );
	}
}
