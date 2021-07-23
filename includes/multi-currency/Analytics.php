<?php
/**
 * Class Compatibility
 *
 * @package WooCommerce\Payments\Compatibility
 */

namespace WCPay\MultiCurrency;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Class that contains multi-currency related support for WooCommerce analytics.
 */
class Analytics {

	const PRIORITY_EARLY   = 1;
	const PRIORITY_DEFAULT = 10;
	const PRIORITY_LATE    = 20;
	const SCRIPT_NAME      = 'WCPAY_MULTI_CURRENCY_ANALYTICS';

	const SQL_REPLACEMENTS = [
		'generic'    => [
			'wp_wc_order_stats.net_total'      => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN wp_wc_order_stats.net_total * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE wp_wc_order_stats.net_total END',
			'wp_wc_order_stats.total_sales'    => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN wp_wc_order_stats.total_sales * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE wp_wc_order_stats.total_sales END',
			'wp_wc_order_stats.tax_total'      => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN wp_wc_order_stats.tax_total * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE wp_wc_order_stats.tax_total END',
			'wp_wc_order_stats.shipping_total' => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN wp_wc_order_stats.shipping_total * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE wp_wc_order_stats.shipping_total END',
			'discount_amount'                  => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN discount_amount * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE discount_amount END',
			'product_net_revenue'              => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN product_net_revenue * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value) ELSE product_net_revenue END',
			'product_gross_revenue'            => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN product_gross_revenue * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE product_gross_revenue END',
		],
		'orders'     => [
			'subquery' => [
				'wp_wc_order_stats.net_total' => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN wp_wc_order_stats.net_total * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE wp_wc_order_stats.net_total END AS net_total',
			],
			'stats'    => [
				'wp_wc_order_stats.net_total'      => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN wp_wc_order_stats.net_total * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE wp_wc_order_stats.net_total END',
				'wp_wc_order_stats.total_sales'    => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN wp_wc_order_stats.total_sales * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE wp_wc_order_stats.total_sales END',
				'wp_wc_order_stats.tax_total'      => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN wp_wc_order_stats.tax_total * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE wp_wc_order_stats.tax_total END',
				'wp_wc_order_stats.shipping_total' => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN wp_wc_order_stats.shipping_total * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE wp_wc_order_stats.shipping_total END',
				'discount_amount'                  => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN discount_amount * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE discount_amount END',
			],
		],
		'products'   => [
			'product_net_revenue'   => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN product_net_revenue * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value) ELSE product_net_revenue END',
			'product_gross_revenue' => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN product_gross_revenue * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE product_gross_revenue END',
		],
		'variations' => [
			'product_net_revenue'   => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN product_net_revenue * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value) ELSE product_net_revenue END',
			'product_gross_revenue' => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN product_gross_revenue * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE product_gross_revenue END',
		],
		'categories' => [
			'product_net_revenue'   => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN product_net_revenue * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value) ELSE product_net_revenue END',
			'product_gross_revenue' => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN product_gross_revenue * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE product_gross_revenue END',
		],
		'taxes'      => [
			'SUM(total_tax)'    => 'SUM(CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN total_tax * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE total_tax END)',
			'SUM(order_tax)'    => 'SUM(CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN order_tax * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE order_tax END)',
			'SUM(shipping_tax)' => 'SUM(CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN shipping_tax * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE shipping_tax END)',
		],
		'coupons'    => [
			'discount_amount' => 'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN discount_amount * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE discount_amount END',
		],
	];

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

		// TODO: Remove this or make it only apply in dev mode.
		add_filter( 'woocommerce_analytics_report_should_use_cache', [ $this, 'disable_report_caching' ] );

		// If we aren't making a REST request, return before adding these filters.
		if ( ! WC()->is_rest_api_request() ) {
			return;

		}

		add_filter( 'woocommerce_analytics_clauses_select', [ $this, 'filter_select_clauses' ], self::PRIORITY_DEFAULT, 2 );
		add_filter( 'woocommerce_analytics_clauses_join', [ $this, 'filter_join_clauses' ] );
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

		wp_register_style(
			self::SCRIPT_NAME,
			plugins_url( 'dist/multi-currency-analytics.css', WCPAY_PLUGIN_FILE ),
			[ 'wc-components' ],
			\WC_Payments::get_file_version( 'dist/multi-currency-analytics.css' )
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
		wp_enqueue_style( self::SCRIPT_NAME );
	}

	/**
	 * Disables report caching. Used for development testing.
	 *
	 * @param array $args Filter arguments.
	 *
	 * @return boolean
	 */
	public function disable_report_caching( $args ): bool {
		return false;
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

		$new_clauses = [];

		foreach ( $clauses as $clause ) {
			if ( ! in_array( $context_page, array_keys( self::SQL_REPLACEMENTS ), true ) ) {
				$replacements_array = self::SQL_REPLACEMENTS['generic'] ?? [];
			} elseif ( 'orders' === $context_page ) {
				$replacements_array = self::SQL_REPLACEMENTS[ $context_page ][ $context_type ] ?? [];
			} else {
				$replacements_array = self::SQL_REPLACEMENTS[ $context_page ] ?? [];
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

		$new_clauses[] = ', wcpay_multicurrency_currency_postmeta.meta_value AS order_currency';
		$new_clauses[] = ', wcpay_multicurrency_default_currency_postmeta.meta_value AS order_default_currency';
		$new_clauses[] = ', wcpay_multicurrency_exchange_rate_postmeta.meta_value AS exchange_rate';

		return $new_clauses;
	}

	/**
	 * Add a JOIN so that we can get the currency information.
	 *
	 * @param string[] $clauses - An array containing the JOIN clauses to be applied.
	 *
	 * @return array
	 */
	public function filter_join_clauses( array $clauses ): array {
		global $wpdb;

		$prefix               = 'wcpay_multicurrency_';
		$currency_tbl         = $prefix . 'currency_postmeta';
		$default_currency_tbl = $prefix . 'default_currency_postmeta';
		$exchange_rate_tbl    = $prefix . 'exchange_rate_postmeta';

		$clauses[] = "LEFT JOIN {$wpdb->postmeta} {$currency_tbl} ON {$wpdb->prefix}wc_order_stats.order_id = {$currency_tbl}.post_id AND {$currency_tbl}.meta_key = '_order_currency'";
		$clauses[] = "LEFT JOIN {$wpdb->postmeta} {$default_currency_tbl} ON {$wpdb->prefix}wc_order_stats.order_id = {$default_currency_tbl}.post_id AND ${default_currency_tbl}.meta_key = '_wcpay_multi_currency_order_default_currency'";
		$clauses[] = "LEFT JOIN {$wpdb->postmeta} {$exchange_rate_tbl} ON {$wpdb->prefix}wc_order_stats.order_id = {$exchange_rate_tbl}.post_id AND ${exchange_rate_tbl}.meta_key = '_wcpay_multi_currency_order_exchange_rate'";

		return $clauses;
	}
}
