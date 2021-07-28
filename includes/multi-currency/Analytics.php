<?php
/**
 * Class Compatibility
 *
 * @package WooCommerce\Payments\Compatibility
 */

namespace WCPay\MultiCurrency;

use WC_Order;
use WC_Order_Refund;

defined( 'ABSPATH' ) || exit;

/**
 * Class that contains multi-currency related support for WooCommerce analytics.
 */
class Analytics {
	const PRIORITY_EARLY   = 1;
	const PRIORITY_DEFAULT = 10;
	const PRIORITY_LATE    = 20;
	const PRIORITY_LATEST  = 99999;
	const SCRIPT_NAME      = 'WCPAY_MULTI_CURRENCY_ANALYTICS';

	const SUPPORTED_CONTEXTS = [ 'orders', 'products', 'variations', 'categories', 'coupons', 'taxes' ];

	/**
	 * Instance of MultiCurrency.
	 *
	 * @var MultiCurrency $multi_currency
	 */
	private $multi_currency;

	/**
	 * Constructor
	 *
	 * @param MultiCurrency $multi_currency Instance of MultiCurrency.
	 */
	public function __construct( MultiCurrency $multi_currency ) {
		$this->multi_currency = $multi_currency;
		$this->init();
	}

	/**
	 * Initialise all actions, filters and hooks related to analytics support.
	 *
	 * @return void
	 */
	public function init() {
		if ( is_admin() ) {
			add_filter( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		}

		add_filter( 'woocommerce_analytics_update_order_stats_data', [ $this, 'update_order_stats_data' ], self::PRIORITY_LATEST, 2 );

		// If we aren't making a REST request, return before adding these filters.
		if ( ! WC()->is_rest_api_request() ) {
			return;

		}

		$this->set_sql_replacements();

		add_filter( 'woocommerce_analytics_clauses_select', [ $this, 'filter_select_clauses' ], self::PRIORITY_LATE, 2 );
		add_filter( 'woocommerce_analytics_clauses_join', [ $this, 'filter_join_clauses' ], self::PRIORITY_LATE, 2 );
	}

	/**
	 * Register the CSS and JS scripts.
	 *
	 * @return void
	 */
	public function register_admin_scripts() {
		$script_src_url    = plugins_url( 'dist/multi-currency-analytics.js', WCPAY_PLUGIN_FILE );
		$script_asset_path = WCPAY_ABSPATH . 'dist/multi-currency-analytics.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require_once $script_asset_path : [ 'dependencies' => [] ];

		wp_register_script(
			self::SCRIPT_NAME,
			$script_src_url,
			$script_asset['dependencies'],
			\WC_Payments::get_file_version( 'dist/multi-currency-analytics.js' ),
			true
		);
	}

	/**
	 * Enqueue scripts used on the analytics WP Admin pages.
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts() {
		$this->register_admin_scripts();

		wp_enqueue_script( self::SCRIPT_NAME );
	}

	/**
	 * When an order is updated in the stats table, perform a check to see if it is a multi currency order
	 * and convert the information into the store's default currency if it is.
	 *
	 * @param array    $args  - An array of the arguments to be inserted into the order stats table.
	 * @param WC_Order $order - The order itself.
	 *
	 * @return array
	 */
	public function update_order_stats_data( array $args, $order ): array {
		if ( ! $this->should_convert_order_stats( $order ) ) {
			return $args;
		}

		$order_exchange_rate = floatval( $order->get_meta( '_wcpay_multi_currency_order_exchange_rate', true ) );

		$args['net_total']      = $this->convert_amount( floatval( $args['net_total'] ), $order_exchange_rate, wc_get_price_decimals() );
		$args['shipping_total'] = $this->convert_amount( floatval( $args['shipping_total'] ), $order_exchange_rate, wc_get_price_decimals() );
		$args['tax_total']      = $this->convert_amount( floatval( $args['tax_total'] ), $order_exchange_rate, wc_get_price_decimals() );
		$args['total_sales']    = $args['net_total'] + $args['shipping_total'] + $args['tax_total'];

		return $args;
	}

	/**
	 * Add columns to get the currency and converted amount (if required).
	 *
	 * @param string[] $clauses - An array containing the SELECT clauses to be applied.
	 * @param string   $context - The context in which this SELECT clause is being called.
	 *
	 * @return array
	 */
	public function filter_select_clauses( array $clauses, $context ): array {
		// If we are unable to identify a context, just return the clauses as is.
		if ( is_null( $context ) ) {
			return $clauses;
		}

		$context_parts = explode( '_', $context );
		$context_page  = $context_parts[0] ?? 'generic';
		$context_type  = $context_parts[1] ?? null;

		// If we can't identify the type of context we are running in (stats or subquery), then return the clauses as is.
		if ( ! in_array( $context_type, [ 'stats', 'subquery' ], true ) ) {
			return $clauses;
		}

		$new_clauses      = [];
		$sql_replacements = $this->get_sql_replacements();

		foreach ( $clauses as $clause ) {
			if ( ! in_array( $context_page, array_keys( $sql_replacements ), true ) ) {
				$replacements_array = $sql_replacements['generic'] ?? [];
			} else {
				$replacements_array = $sql_replacements[ $context_page ] ?? [];
			}

			foreach ( $replacements_array as $find => $replace ) {
				if ( strpos( $clause, $find ) !== false ) {
					$clause = str_replace(
						$find,
						$replace,
						$clause
					);
				}
			}

			$new_clauses[] = $clause;
		}

		if ( $this->is_supported_context( $context ) && ( in_array( $context_page, self::SUPPORTED_CONTEXTS, true ) || $this->is_order_stats_table_used_in_clauses( $clauses ) ) ) {
			$new_clauses[] = ', wcpay_multicurrency_currency_postmeta.meta_value AS order_currency';
			$new_clauses[] = ', wcpay_multicurrency_default_currency_postmeta.meta_value AS order_default_currency';
			$new_clauses[] = ', wcpay_multicurrency_exchange_rate_postmeta.meta_value AS exchange_rate';
		}

		return $new_clauses;
	}

	/**
	 * Add a JOIN so that we can get the currency information.
	 *
	 * @param string[] $clauses - An array containing the JOIN clauses to be applied.
	 * @param string   $context - The context in which this SELECT clause is being called.
	 *
	 * @return array
	 */
	public function filter_join_clauses( array $clauses, $context ): array {
		global $wpdb;

		$context_parts = explode( '_', $context );
		$context_page  = $context_parts[0] ?? 'generic';

		$prefix               = 'wcpay_multicurrency_';
		$currency_tbl         = $prefix . 'currency_postmeta';
		$default_currency_tbl = $prefix . 'default_currency_postmeta';
		$exchange_rate_tbl    = $prefix . 'exchange_rate_postmeta';

		// If this is a suppotted context, add the joins. If this is an unsupported context, see if we can add the joins.
		if ( $this->is_supported_context( $context ) && ( in_array( $context_page, self::SUPPORTED_CONTEXTS, true ) || $this->is_order_stats_table_used_in_clauses( $clauses ) ) ) {
			$clauses[] = "LEFT JOIN {$wpdb->postmeta} {$currency_tbl} ON {$wpdb->prefix}wc_order_stats.order_id = {$currency_tbl}.post_id AND {$currency_tbl}.meta_key = '_order_currency'";
			$clauses[] = "LEFT JOIN {$wpdb->postmeta} {$default_currency_tbl} ON {$wpdb->prefix}wc_order_stats.order_id = {$default_currency_tbl}.post_id AND ${default_currency_tbl}.meta_key = '_wcpay_multi_currency_order_default_currency'";
			$clauses[] = "LEFT JOIN {$wpdb->postmeta} {$exchange_rate_tbl} ON {$wpdb->prefix}wc_order_stats.order_id = {$exchange_rate_tbl}.post_id AND ${exchange_rate_tbl}.meta_key = '_wcpay_multi_currency_order_exchange_rate'";
		}

		return $clauses;
	}

	/**
	 * Get the SQL replacements variable.
	 *
	 * @return array
	 */
	private function get_sql_replacements(): array {
		return $this->sql_replacements;
	}

	/**
	 * Check to see whether we should convert an order to store in the order stats table.
	 *
	 * @param WC_Order|WC_Order_Refund $order The order.
	 *
	 * @return boolean
	 */
	private function should_convert_order_stats( $order ): bool {
		$default_currency = $this->multi_currency->get_default_currency();

		// If this order was in the default currency, or the meta information isn't set on the order, return false.
		if ( ! $order ||
			$order->get_currency() === $default_currency->get_code() ||
			! $order->get_meta( '_wcpay_multi_currency_order_exchange_rate', true ) ||
			$order->get_meta( '_wcpay_multi_currency_order_default_currency', true ) !== $default_currency->get_code()
		) {
			return false;
		}

		return true;
	}

	/**
	 * Convert an amount to the store's default currency in order to store in the stats table.
	 *
	 * @param float   $amount The amount to convert into the store's default currency.
	 * @param float   $exchange_rate The exchange rate to use for the conversion.
	 * @param integer $dp How many decimal places we should use to store the converted amount.
	 *
	 * @return float The converted amount.
	 */
	private function convert_amount( float $amount, float $exchange_rate, int $dp = 2 ): float {
		return number_format( $amount * ( 1 / $exchange_rate ), $dp, '.', '' );
	}

	/**
	 * Check whether the order stats table is referenced in the clauses, to work out whether
	 * to add the JOIN columns for multi currency.
	 *
	 * @param array $clauses The array containing the clauses used.
	 *
	 * @return boolean Whether the order stats table is referenced.
	 */
	private function is_order_stats_table_used_in_clauses( array $clauses ): bool {
		global $wpdb;

		foreach ( $clauses as $clause ) {
			if ( strpos( $clause, "{$wpdb->prefix}wc_order_stats" ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * There are some queries which are made in the analytics which are actually sub-queries
	 * which are used to join on an individual item/coupon/tax code. In these cases, rather than
	 * the context being the expected format e.g. product_stats_total, it will simply be 'product'.
	 * In these cases, we don't want to add the join columns or select them.
	 *
	 * @param string $context The context the query was made in.
	 *
	 * @return boolean
	 */
	private function is_supported_context( string $context ): bool {
		$unsupported_contexts = [ 'products', 'coupons', 'taxes', 'variations', 'categories' ];

		if ( in_array( $context, $unsupported_contexts, true ) ) {
			return false;
		}

		return true;
	}
	/**
	 * Set the SQL replacements variable.
	 *
	 * @return void
	 */
	private function set_sql_replacements() {
		$this->sql_replacements = [
			'generic'    => [
				'discount_amount'       => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN ROUND(discount_amount * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ), 2) ELSE discount_amount END',
				'product_net_revenue'   => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN ROUND(product_net_revenue * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value), 2) ELSE product_net_revenue END',
				'product_gross_revenue' => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN ROUND(product_gross_revenue * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ), 2) ELSE product_gross_revenue END',
			],
			'orders'     => [
				'discount_amount' => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN ROUND(discount_amount * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ), 2) ELSE discount_amount END',
			],
			'products'   => [
				'product_net_revenue'   => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN ROUND(product_net_revenue * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value), 2) ELSE product_net_revenue END',
				'product_gross_revenue' => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN ROUND(product_gross_revenue * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ), 2) ELSE product_gross_revenue END',
			],
			'variations' => [
				'product_net_revenue'   => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN ROUND(product_net_revenue * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value), 2) ELSE product_net_revenue END',
				'product_gross_revenue' => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN ROUND(product_gross_revenue * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ), 2) ELSE product_gross_revenue END',
			],
			'categories' => [
				'product_net_revenue'   => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN ROUND(product_net_revenue * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value), 2) ELSE product_net_revenue END',
				'product_gross_revenue' => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN ROUND(product_gross_revenue * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ), 2) ELSE product_gross_revenue END',
			],
			'taxes'      => [
				'SUM(total_tax)'    => 'SUM(CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN ROUND(total_tax * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ), 2) ELSE total_tax END)',
				'SUM(order_tax)'    => 'SUM(CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN ROUND(order_tax * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ), 2) ELSE order_tax END)',
				'SUM(shipping_tax)' => 'SUM(CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN ROUND(shipping_tax * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ), 2) ELSE shipping_tax END)',
			],
			'coupons'    => [
				'discount_amount' => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN ROUND(discount_amount * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ), 2) ELSE discount_amount END',
			],
		];
	}
}
