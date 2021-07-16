<?php
/**
 * Class Compatibility
 *
 * @package WooCommerce\Payments\Compatibility
 */

namespace WCPay\MultiCurrency;

use Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry;
use Automattic\WooCommerce\Blocks\Package;

defined( 'ABSPATH' ) || exit;

/**
 * Class that contains multi-currency related support for WooCommerce analytics.
 */
class Analytics {

	const SCRIPT_NAME = 'WCPAY_MULTI_CURRENCY_ANALYTICS';

	/**
	 * A list of all the pages in the WC Admin analytics section that
	 * we want to add multi-currency filters to.
	 *
	 * @var string[]
	 */
	const ANALYTICS_PAGES = [
		'orders',
		'revenue',
		'products',
		'categories',
		'coupons',
		'taxes',
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
		// If we aren't in the context of WC Admin, just return false.
		if ( ! is_admin() ) {
			return;
		}

		$this->multi_currency = $multi_currency;

		$this->init();
	}

	/**
	 * Initialise all actions, filters and hooks related to analytics support.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

		foreach ( self::ANALYTICS_PAGES as $analytics_page ) {
			add_filter( "woocommerce_analytics_{$analytics_page}_query_args", [ $this, 'filter_stats_by_currency' ] );
			add_filter( "woocommerce_analytics_{$analytics_page}_stats_query_args", [ $this, 'filter_stats_by_currency' ] );

			add_filter( "woocommerce_analytics_clauses_join_{$analytics_page}_subquery", [ $this, 'filter_join_clauses' ] );
			add_filter( "woocommerce_analytics_clauses_join_{$analytics_page}_stats_total", [ $this, 'filter_join_clauses' ] );
			add_filter( "woocommerce_analytics_clauses_join_{$analytics_page}_stats_interval", [ $this, 'filter_join_clauses' ] );

			add_filter( "woocommerce_analytics_clauses_where_{$analytics_page}_subquery", [ $this, 'filter_where_clauses' ] );
			add_filter( "woocommerce_analytics_clauses_where_{$analytics_page}_stats_total", [ $this, 'filter_where_clauses' ] );
			add_filter( "woocommerce_analytics_clauses_where_{$analytics_page}_stats_interval", [ $this, 'filter_where_clauses' ] );

			add_filter( "woocommerce_analytics_clauses_select_{$analytics_page}_subquery", [ $this, 'filter_select_clauses' ] );
			add_filter( "woocommerce_analytics_clauses_select_{$analytics_page}_stats_total", [ $this, 'filter_select_clauses' ] );
			add_filter( "woocommerce_analytics_clauses_select_{$analytics_page}_stats_interval", [ $this, 'filter_select_clauses' ] );
		}

		add_filter( 'woocommerce_analytics_clauses_join', [ $this, 'filter_join_clauses' ] );
		add_filter( 'woocommerce_analytics_clauses_where', [ $this, 'filter_where_clauses' ] );
		add_filter( 'woocommerce_analytics_clauses_select', [ $this, 'filter_select_clauses' ] );
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
	 * Return the currently selected currency from _GET, or the store default currency.
	 *
	 * @return string|null
	 */
	public function get_active_currency() {
		0 && wp_verify_nonce( '' );

		// Only return a currency if there is a GET param - if no currency is set, we want to fetch all orders.
		if ( ! empty( $_GET['currency'] ) ) {
			return strtoupper( sanitize_text_field( wp_unslash( $_GET['currency'] ) ) );
		}

		return null;
	}

	/**
	 * Add columns to get the order currency and converted amount (if required).
	 *
	 * @param string[] $clauses - An array containing the SELECT clauses to be applied.
	 *
	 * @return array
	 */
	public function filter_select_clauses( $clauses ): array {
		$clauses[] = ', currency_postmeta.meta_value AS order_currency';
		$clauses[] = ', default_currency_postmeta.meta_value AS order_default_currency';
		$clauses[] = ', exchange_rate_postmeta.meta_value AS exchange_rate';

		return $clauses;
	}

	/**
	 * Add a JOIN so that we can get the currency.
	 *
	 * @param string[] $clauses - An array containing the JOIN clauses to be applied.
	 *
	 * @return array
	 */
	public function filter_join_clauses( $clauses ): array {
		global $wpdb;

		$clauses[] = "JOIN {$wpdb->postmeta} currency_postmeta ON {$wpdb->prefix}wc_order_stats.order_id = currency_postmeta.post_id";
		$clauses[] = "JOIN {$wpdb->postmeta} default_currency_postmeta ON {$wpdb->prefix}wc_order_stats.order_id = default_currency_postmeta.post_id";
		$clauses[] = "JOIN {$wpdb->postmeta} exchange_rate_postmeta ON {$wpdb->prefix}wc_order_stats.order_id = exchange_rate_postmeta.post_id";

		return $clauses;
	}

	/**
	 * Filter by currency (if required).
	 *
	 * @param string[] $clauses - An array containing the WHERE clauses to be applied.
	 *
	 * @return array
	 */
	public function filter_where_clauses( $clauses ): array {
		$currency = $this->get_active_currency();

		$clauses[] = "AND currency_postmeta.meta_key = '_order_currency'";
		$clauses[] = "AND default_currency_postmeta.meta_key = '_wcpay_multi_currency_order_default_currency'";
		$clauses[] = "AND exchange_rate_postmeta.meta_key = '_wcpay_multi_currency_order_exchange_rate'";

		if ( ! is_null( $currency ) ) {
			$clauses[] = "AND currency_postmeta.meta_value = '{$currency}'";
		}

		return $clauses;
	}

	/**
	 * Applied when loading a stats page to filter stats by the selected currency.
	 *
	 * @param array $args Arguments passed in from the filter.
	 *
	 * @return array
	 */
	public function filter_stats_by_currency( $args ): array {
		$args['currency'] = $this->get_active_currency();
		return $args;
	}
}
