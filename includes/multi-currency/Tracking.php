<?php
/**
 * Class Tracking
 *
 * @package WooCommerce\Payments\MultiCurrency
 */

namespace WCPay\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls Multi-Currency Tracking functionality.
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
	 * Queries the database to see how many orders have been made using Multi-Currency.
	 *
	 * @return array Result count.
	 */
	private function get_mc_order_count(): array {
		global $wpdb;
		$query_on_orders = "
			SELECT
				gateway, currency, SUM(total) AS totals, COUNT(order_id) AS counts
			FROM (
				SELECT
					orders.id AS order_id, orders.payment_method as gateway, orders.total_amount as total, orders.currency as currency
				FROM
					{$wpdb->prefix}wc_orders orders
				LEFT JOIN
					{$wpdb->prefix}wc_orders_meta order_meta ON order_meta.order_id = orders.id
				INNER JOIN
					{$wpdb->prefix}wc_orders_meta mc_meta ON mc_meta.order_id = orders.id
					AND mc_meta.meta_key = '_wcpay_multi_currency_order_exchange_rate'
				WHERE orders.type = 'shop_order'
					AND orders.status in ( 'wc-completed', 'wc-processing', 'wc-refunded' )
				GROUP BY orders.id
			) order_gateways
			GROUP BY currency, gateway
		";
		$query_on_posts  = "
			SELECT
				gateway, currency, SUM(total) AS totals, COUNT(order_id) AS counts
			FROM (
				SELECT
					orders.id AS order_id,
					MAX(CASE WHEN order_meta.meta_key = '_payment_method' THEN order_meta.meta_value END) gateway,
					MAX(CASE WHEN order_meta.meta_key = '_order_total' THEN order_meta.meta_value END) total,
					MAX(CASE WHEN order_meta.meta_key = '_order_currency' THEN order_meta.meta_value END) currency
				FROM
					{$wpdb->prefix}posts orders
				LEFT JOIN
					{$wpdb->prefix}postmeta order_meta ON order_meta.post_id = orders.id
				INNER JOIN
					{$wpdb->prefix}postmeta mc_meta ON mc_meta.post_id = orders.id
					AND mc_meta.meta_key = '_wcpay_multi_currency_order_exchange_rate'
				WHERE orders.post_type = 'shop_order'
					AND orders.post_status in ( 'wc-completed', 'wc-processing', 'wc-refunded' )
					AND order_meta.meta_key in ( '_payment_method', '_order_total', '_order_currency' )
				GROUP BY orders.id
			) order_gateways
			GROUP BY currency, gateway
		";

		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) &&
				\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$orders_by_currency = $wpdb->get_results( $query_on_orders ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$orders_by_currency = $wpdb->get_results( $query_on_posts ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$currencies  = [];
		$total_count = 0;
		foreach ( $orders_by_currency as $group ) {
			// Get current counts and totals.
			$counts = $currencies[ $group->currency ]['counts'] ?? 0;
			$totals = $currencies[ $group->currency ]['totals'] ?? 0;

			// Update the counts and totals for the currency.
			$currencies[ $group->currency ]['counts'] = $counts + $group->counts;
			$currencies[ $group->currency ]['totals'] = $totals + $group->totals;

			// If something provides a 100% discount, the payment method is null. This could be coupons, gift cards, etc.
			$gateway = $group->gateway ?? 'unknown';

			// Update the counts and totals per gateway for the currency.
			$currencies[ $group->currency ]['gateways'][ $gateway ] = [
				'counts' => $group->counts,
				'totals' => $group->totals,
			];

			// Update the total count.
			$total_count += $group->counts;
		}

		return [
			'counts'     => $total_count,
			'currencies' => $currencies,
		];
	}
}
