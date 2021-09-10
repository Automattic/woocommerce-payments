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
			'order_counts'       => $this->get_mc_order_count(),
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
		$data = [
			'code' => $currency->get_code(),
			'name' => html_entity_decode( $currency->get_name() ),
		];

		// Return early if it's the default currency.
		if ( $currency->get_code() === $this->multi_currency->get_default_currency()->get_code() ) {
			return $data;
		}

		// Is it a zero decimal currency?
		$is_zero_decimal = $currency->get_is_zero_decimal();

		// Is it using a custom or automatic rate?
		$rate_type = get_option( $this->multi_currency->id . '_exchange_rate_' . $currency->get_id(), 'automatic' );
		$rate_type = 'automatic' === $rate_type ? $rate_type . ' (default)' : $rate_type;

		// What is the price rounding setting?
		$price_rounding_default = $is_zero_decimal ? '100' : '1.00';
		$price_rounding         = $currency->get_rounding();
		$price_rounding         = $price_rounding_default === $price_rounding ? $price_rounding . ' (default)' : $price_rounding;

		// What is the price charm setting?
		$price_charm = $currency->get_charm();
		$price_charm = 0.00 === $price_charm ? '0.00 (default)' : $price_charm;

		$additional_data = [
			'is_zero_decimal' => $is_zero_decimal,
			'rate_type'       => $rate_type,
			'price_rounding'  => $price_rounding,
			'price_charm'     => $price_charm,
		];

		return array_merge( $data, $additional_data );
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
	 * @return array Result count.
	 */
	private function get_mc_order_count(): array {
		global $wpdb;

		$results = $wpdb->get_results( "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_wcpay_multi_currency_order_exchange_rate'" );
		$total   = is_array( $results ) ? count( $results ) : 0;

		$orders_by_currency = $wpdb->get_results(
			"
			SELECT
				gateway, currency, SUM(total) AS totals, COUNT(order_id) AS counts
			FROM (
				SELECT
					orders.id AS order_id,
					MAX(CASE WHEN meta_key = '_payment_method' THEN meta_value END) gateway,
					MAX(CASE WHEN meta_key = '_order_total' THEN meta_value END) total,
					MAX(CASE WHEN meta_key = '_order_currency' THEN meta_value END) currency
				FROM
					{$wpdb->prefix}posts orders
				LEFT JOIN
					{$wpdb->prefix}postmeta order_meta ON order_meta.post_id = orders.id
				WHERE orders.post_type = 'shop_order'
					AND orders.post_status in ( 'wc-completed', 'wc-processing', 'wc-refunded' )
					AND meta_key in ( '_payment_method', '_order_total', '_order_currency', '_wcpay_multi_currency_order_exchange_rate' )
				GROUP BY orders.id
			) order_gateways
			GROUP BY currency, gateway
			"
		);

		$currencies  = [];
		$added_total = 0;
		foreach ( $orders_by_currency as $group ) {
			$counts = $currencies[ $group->currency ]['counts'] ?? 0;
			$totals = $currencies[ $group->currency ]['totals'] ?? 0;

			$currencies[ $group->currency ] = [
				'counts'   => $counts + $group->counts,
				'totals'   => $totals + $group->totals,
				'gateways' => [
					$group->gateway => [
						'counts' => $group->counts,
						'totals' => $group->totals,
					],
				],
			];

			$added_total += $group->counts;
		}

		return [
			'total'       => $total,
			'added_total' => $added_total,
			'currencies'  => $currencies,
		];
	}
}
