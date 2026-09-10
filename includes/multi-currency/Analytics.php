<?php
/**
 * Class Compatibility
 *
 * @package WooCommerce\Payments\Compatibility
 */

namespace WCPay\MultiCurrency;

use Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore as OrderStatsDataStore;
use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry;
use Automattic\WooCommerce\Utilities\OrderUtil;
use WC_Order;
use WC_Order_Refund;
use WCPay\MultiCurrency\Interfaces\MultiCurrencySettingsInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Class that contains Multi-Currency related support for WooCommerce analytics.
 */
class Analytics {
	const PRIORITY_EARLY   = 1;
	const PRIORITY_DEFAULT = 10;
	const PRIORITY_LATE    = 20;
	const PRIORITY_LATEST  = 99999;
	const SCRIPT_NAME      = 'WCPAY_MULTI_CURRENCY_ANALYTICS';

	const SUPPORTED_CONTEXTS = [ 'orders', 'products', 'variations', 'categories', 'coupons', 'taxes' ];

	/**
	 * SQL string replacements made by the analytics Multi-Currency extension.
	 *
	 * @var array
	 */
	protected $sql_replacements = [];

	/**
	 * Instance of MultiCurrency.
	 *
	 * @var MultiCurrency $multi_currency
	 */
	private $multi_currency;

	/**
	 * Instance of MultiCurrencySettingsInterface.
	 *
	 * @var MultiCurrencySettingsInterface $settings_service
	 */
	private $settings_service;

	/**
	 * Constructor
	 *
	 * @param MultiCurrency                  $multi_currency   Instance of MultiCurrency.
	 * @param MultiCurrencySettingsInterface $settings_service Instance of MultiCurrencySettingsInterface.
	 * @param bool                           $initialize Whether to register hooks immediately.
	 */
	public function __construct( MultiCurrency $multi_currency, MultiCurrencySettingsInterface $settings_service, bool $initialize = true ) {
		$this->multi_currency   = $multi_currency;
		$this->settings_service = $settings_service;
		if ( $initialize ) {
			$this->init();
		}
	}

	/**
	 * Initialise all actions, filters and hooks related to analytics support.
	 *
	 * @return void
	 */
	public function init() {
		if ( is_admin() && current_user_can( 'manage_woocommerce' ) ) {
			add_filter( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
			$this->register_customer_currencies();
		}

		if ( $this->settings_service->is_dev_mode() ) {
			add_filter( 'woocommerce_analytics_report_should_use_cache', [ $this, 'disable_report_caching' ] );
		}

		// Add a filter when the order stats table is updated.
		add_filter( 'woocommerce_analytics_update_order_stats_data', [ $this, 'update_order_stats_data' ], self::PRIORITY_LATEST, 2 );

		// Add filters when the query args are updated.
		add_filter( 'woocommerce_analytics_orders_query_args', [ $this, 'apply_customer_currency_args' ] );
		add_filter( 'woocommerce_analytics_orders_stats_query_args', [ $this, 'apply_customer_currency_args' ] );

		// If we aren't making a REST request, or no multi currency orders exist in the merchant's store,
		// return before adding these filters.

		if ( ! WC()->is_rest_api_request() || ! $this->has_multi_currency_orders() ) {
			return;
		}

		$this->set_sql_replacements();

		// Add the filters that are applied in each analytics query.
		add_filter( 'woocommerce_analytics_products_segment_columns', [ $this, 'filter_product_segment_columns' ], self::PRIORITY_LATE, 2 );
		add_filter( 'woocommerce_analytics_taxes_segment_columns', [ $this, 'filter_tax_segment_columns' ], self::PRIORITY_LATE, 2 );
		add_filter( 'woocommerce_analytics_coupons_segment_columns', [ $this, 'filter_coupon_segment_columns' ], self::PRIORITY_LATE, 2 );
		add_filter( 'woocommerce_analytics_coupons_product_segment_columns', [ $this, 'filter_coupon_product_segment_columns' ], self::PRIORITY_LATE, 2 );
		add_filter( 'woocommerce_analytics_clauses_select', [ $this, 'filter_select_clauses' ], self::PRIORITY_LATE, 2 );
		add_filter( 'woocommerce_analytics_clauses_join', [ $this, 'filter_join_clauses' ], self::PRIORITY_LATE, 2 );

		// Add the WHERE clause filter which is applied only on Order related queries.
		add_filter( 'woocommerce_analytics_clauses_where_orders_subquery', [ $this, 'filter_where_clauses' ] );
		add_filter( 'woocommerce_analytics_clauses_where_orders_stats_total', [ $this, 'filter_where_clauses' ] );
		add_filter( 'woocommerce_analytics_clauses_where_orders_stats_interval', [ $this, 'filter_where_clauses' ] );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['currency'] ) && $_GET['currency'] !== $this->multi_currency->get_default_currency()->get_code() ) {
			add_filter( 'woocommerce_analytics_clauses_select_orders_subquery', [ $this, 'filter_select_orders_clauses' ] );
			add_filter( 'woocommerce_analytics_clauses_select_orders_stats_total', [ $this, 'filter_select_orders_clauses' ] );
		}
	}

	/**
	 * Register the CSS and JS scripts.
	 *
	 * @return void
	 */
	public function register_admin_scripts() {
		$this->multi_currency->register_script_with_dependencies( self::SCRIPT_NAME, 'dist/multi-currency-analytics' );
	}

	/**
	 * Add the list of currencies used on the store to the wcSettings to allow it to be accessed by the front-end JS script.
	 *
	 * @return void
	 */
	public function register_customer_currencies() {
		$data_registry = Package::container()->get( AssetDataRegistry::class );
		if ( $data_registry->exists( 'customerCurrencies' ) ) {
			return;
		}

		$currencies           = $this->multi_currency->get_all_customer_currencies();
		$available_currencies = $this->multi_currency->get_available_currencies();
		$currency_options     = [];

		$default_currency = $this->multi_currency->get_default_currency();

		// Add default currency to the list if it does not exist.
		if ( ! in_array( $default_currency->get_code(), $currencies, true ) ) {
			$currencies[] = $default_currency->get_code();
		}

		foreach ( $currencies as $currency ) {
			if ( ! isset( $available_currencies[ $currency ] ) ) {
				continue;
			}

			$currency_details   = $available_currencies[ $currency ];
			$currency_options[] = [
				'label' => html_entity_decode( $currency_details->get_name() ),
				'value' => $currency_details->get_code(),
			];
		}

		$data_registry->add( 'customerCurrencies', $currency_options );
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
	 * Disables report caching. Used for development of analytics related functionality.
	 * To disable report caching
	 *
	 * @param array $_unused_args Filter arguments.
	 *
	 * @return boolean
	 */
	public function disable_report_caching( $_unused_args ): bool {
		return false;
	}

	/**
	 * If the customer currency is set, add it as a query parameter to requests to the data store.
	 * This ensures that the cache is updated when this value is changed between requests.
	 *
	 * @param array $args The arguments passed in via the GET request.
	 *
	 * @return array
	 */
	public function apply_customer_currency_args( $args ): array {
		$currency_args = $this->get_customer_currency_args_from_request();
		return array_merge( $args, $currency_args );
	}

	/**
	 * When an order is updated in the stats table, perform a check to see if it is a Multi-Currency order
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

		$stripe_exchange_rate = $order->get_meta( '_wcpay_multi_currency_stripe_exchange_rate', true );
		$has_processor_rate   = '' !== $stripe_exchange_rate && null !== $stripe_exchange_rate;
		$saved_rate           = $has_processor_rate ? $stripe_exchange_rate : $order->get_meta( '_wcpay_multi_currency_order_exchange_rate', true );

		// An explicitly invalid processor rate must not silently fall back to a different basis.
		if ( ! is_numeric( $saved_rate ) || ! is_finite( (float) $saved_rate ) || (float) $saved_rate <= 0 ) {
			return $args;
		}
		$exchange_rate = $has_processor_rate ? (float) $saved_rate : 1 / (float) $saved_rate;
		if ( ! is_finite( $exchange_rate ) ) {
			return $args;
		}

		$dp        = wc_get_price_decimals();
		$converted = $args;
		foreach ( [ 'net_total', 'shipping_total', 'tax_total' ] as $key ) {
			$converted[ $key ] = round( $this->convert_amount( (float) $args[ $key ], $exchange_rate ), $dp );
			if ( ! is_finite( $converted[ $key ] ) ) {
				return $args;
			}
		}
		$converted['total_sales'] = $converted['net_total'] + $converted['shipping_total'] + $converted['tax_total'];
		if ( ! is_finite( $converted['total_sales'] ) ) {
			return $args;
		}
		if ( array_key_exists( 'source_net_total', $args ) ) {
			$converted['source_net_total'] = (float) $args['net_total'];
			$converted['source_currency']  = $order->get_currency();
		}
		if ( array_key_exists( 'reporting_exchange_rate', $args ) ) {
			$converted['reporting_exchange_rate'] = $exchange_rate;
		}
		$args = $converted;
		// Only newer core versions expose these columns in the import contract.
		if ( array_key_exists( 'reporting_currency', $args ) ) {
			$args['reporting_currency'] = strtoupper( $this->multi_currency->get_default_currency()->get_code() );
			$args['reporting_basis']    = $has_processor_rate ? 'historical_processor_rate' : 'historical_order_rate';
		}

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
		global $wpdb;
		// If we are unable to identify a context, just return the clauses as is.
		if ( is_null( $context ) ) {
			return $clauses;
		}

		/**
		 * Filters whether the Multi-Currency SELECT clauses modification should be disabled.
		 *
		 * @since 3.3.0
		 *
		 * @param bool $disable Whether to disable the SELECT clauses modification. Default false.
		 */
		if ( apply_filters( MultiCurrency::FILTER_PREFIX . 'disable_filter_select_clauses', false ) ) {
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
			// Core can only qualify native product amounts. This provider converts
			// product revenue at query time using the recorded reporting rate.
			if ( in_array( $context_page, [ 'products', 'variations', 'categories', 'taxes', 'coupons' ], true ) && false !== strpos( $clause, ' AS reporting_missing_orders' ) && method_exists( OrderStatsDataStore::class, 'has_reporting_currency_columns' ) && OrderStatsDataStore::has_reporting_currency_columns() ) {
				$stats  = $wpdb->prefix . 'wc_order_stats';
				$clause = str_replace(
					"$stats.reporting_exchange_rate = 1 AND $stats.reporting_basis = 'native'",
					"$stats.reporting_exchange_rate > 0 AND $stats.reporting_basis IN ('native', 'historical_order_rate', 'historical_processor_rate')",
					$clause
				);
			}
			if ( ! array_key_exists( $context_page, $sql_replacements ) ) {
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
			if ( $this->is_cot_enabled() ) {
				$new_clauses[] = ', wcpay_multicurrency_order_currency.currency AS order_currency';
			} else {
				$new_clauses[] = ', wcpay_multicurrency_currency_meta.meta_value AS order_currency';
			}
			$new_clauses[] = ', wcpay_multicurrency_default_currency_meta.meta_value AS order_default_currency';
			$new_clauses[] = ', wcpay_multicurrency_exchange_rate_meta.meta_value AS exchange_rate';
			$new_clauses[] = ', wcpay_multicurrency_stripe_exchange_rate_meta.meta_value AS stripe_exchange_rate';
			if ( 'stats' === $context_type && ! ( in_array( $context_page, [ 'products', 'taxes', 'coupons' ], true ) && false !== strpos( implode( ' ', $clauses ), ' AS reporting_missing_orders' ) ) && method_exists( OrderStatsDataStore::class, 'has_reporting_currency_columns' ) && OrderStatsDataStore::has_reporting_currency_columns() ) {
				$table         = $wpdb->prefix . 'wc_order_stats';
				$condition     = $this->get_reporting_condition_sql();
				$currency_args = $this->get_customer_currency_args_from_request();
				if ( 'orders' === $context_page && ! empty( $currency_args['currency'] ) && $currency_args['currency'] !== $this->multi_currency->get_default_currency()->get_code() ) {
					// Interpolate the table name rather than using %i: the identifier
					// placeholder needs WP 6.2, and $table is code-derived. Values stay prepared.
					$condition = $table . '.source_currency = ' . $wpdb->prepare( '%s', $currency_args['currency'] ) . " AND $table.source_net_total IS NOT NULL";
				}
				$indicator = "COUNT(DISTINCT CASE WHEN $condition THEN NULL ELSE CASE WHEN $table.parent_id > 0 THEN $table.parent_id ELSE $table.order_id END END) AS reporting_missing_orders";
				$replaced  = 0;
				if ( 'orders' === $context_page ) {
					$core_currency  = $table . '.reporting_currency = ' . $wpdb->prepare( '%s', get_woocommerce_currency() );
					$core_condition = "$core_currency AND $table.reporting_exchange_rate > 0 AND $table.reporting_basis IN ('native', 'historical_order_rate', 'historical_processor_rate')";
					$core_indicator = "COUNT(DISTINCT CASE WHEN $core_condition THEN NULL ELSE CASE WHEN $table.parent_id > 0 THEN $table.parent_id ELSE $table.order_id END END) AS reporting_missing_orders";
					foreach ( $new_clauses as &$new_clause ) {
						$new_clause = str_replace( $core_indicator, $indicator, $new_clause, $count );
						$replaced  += $count;
					}
					unset( $new_clause );
				}
				// Preserve an extension's qualification expression instead of creating an ambiguous duplicate alias.
				$has_indicator = preg_match( '/\bAS\s+`?reporting_missing_orders`?\b/i', implode( ' ', $new_clauses ) );
				if ( ! $replaced && ! $has_indicator ) {
					$new_clauses[] = ', ' . $indicator;
				}
			}
		}

		/**
		 * Filters the SELECT clauses after the Multi-Currency columns have been added.
		 *
		 * @since 3.3.0
		 *
		 * @param string[] $new_clauses The SELECT clauses to be applied.
		 */
		return apply_filters( MultiCurrency::FILTER_PREFIX . 'filter_select_clauses', $new_clauses );
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

		/**
		 * Filters whether the Multi-Currency JOIN clauses modification should be disabled.
		 *
		 * @since 3.3.0
		 *
		 * @param bool $disable Whether to disable the JOIN clauses modification. Default false.
		 */
		if ( apply_filters( MultiCurrency::FILTER_PREFIX . 'disable_filter_join_clauses', false ) ) {
			return $clauses;
		}

		$context_parts = explode( '_', $context, 2 );
		$context_page  = $context_parts[0] ?? 'generic';

		$prefix                   = 'wcpay_multicurrency_';
		$currency_tbl             = $prefix . 'currency_meta';
		$default_currency_tbl     = $prefix . 'default_currency_meta';
		$exchange_rate_tbl        = $prefix . 'exchange_rate_meta';
		$stripe_exchange_rate_tbl = $prefix . 'stripe_exchange_rate_meta';

		// Allow this to work with custom order tables as well.
		if ( $this->is_cot_enabled() ) {
			$meta_table = $wpdb->prefix . 'wc_orders_meta';
			$id_field   = 'order_id';

			$currency_tbl = $prefix . 'order_currency';
		} else {
			$meta_table = $wpdb->postmeta;
			$id_field   = 'post_id';
		}

		// If this is a supported context, add the joins. If this is an unsupported context, see if we can add the joins.
		if ( $this->is_supported_context( $context ) && ( in_array( $context_page, self::SUPPORTED_CONTEXTS, true ) || $this->is_order_stats_table_used_in_clauses( $clauses ) ) ) {
			if ( $this->is_cot_enabled() ) {
				$clauses[] = "LEFT JOIN {$wpdb->prefix}wc_orders {$currency_tbl} ON {$wpdb->prefix}wc_order_stats.order_id = {$currency_tbl}.id";
			} else {
				$clauses[] = "LEFT JOIN {$meta_table} {$currency_tbl} ON {$wpdb->prefix}wc_order_stats.order_id = {$currency_tbl}.{$id_field} AND {$currency_tbl}.meta_key = '_order_currency'";

			}
			$clauses[] = "LEFT JOIN {$meta_table} {$default_currency_tbl} ON {$wpdb->prefix}wc_order_stats.order_id = {$default_currency_tbl}.{$id_field} AND {$default_currency_tbl}.meta_key = '_wcpay_multi_currency_order_default_currency'";
			$clauses[] = "LEFT JOIN {$meta_table} {$exchange_rate_tbl} ON {$wpdb->prefix}wc_order_stats.order_id = {$exchange_rate_tbl}.{$id_field} AND {$exchange_rate_tbl}.meta_key = '_wcpay_multi_currency_order_exchange_rate'";
			$clauses[] = "LEFT JOIN {$meta_table} {$stripe_exchange_rate_tbl} ON {$wpdb->prefix}wc_order_stats.order_id = {$stripe_exchange_rate_tbl}.{$id_field} AND {$stripe_exchange_rate_tbl}.meta_key = '_wcpay_multi_currency_stripe_exchange_rate'";
		}

		/**
		 * Filters the JOIN clauses after the Multi-Currency currency JOINs have been added.
		 *
		 * @since 3.3.0
		 *
		 * @param string[] $clauses The JOIN clauses to be applied.
		 */
		return apply_filters( MultiCurrency::FILTER_PREFIX . 'filter_join_clauses', $clauses );
	}

	/**
	 * Add the WHERE clauses (if a customer currency has been selected).
	 *
	 * @param string[] $clauses - An array containing the JOIN clauses to be applied.
	 *
	 * @return array
	 */
	public function filter_where_clauses( array $clauses ): array {
		/**
		 * Filters whether the Multi-Currency WHERE clauses modification should be disabled.
		 *
		 * @since 4.5.1
		 *
		 * @param bool $disable Whether to disable the WHERE clauses modification. Default false.
		 */
		if ( apply_filters( MultiCurrency::FILTER_PREFIX . 'disable_filter_where_clauses', false ) ) {
			return $clauses;
		}

		$prefix = 'wcpay_multicurrency_';
		if ( $this->is_cot_enabled() ) {
			$currency_field = $prefix . 'order_currency.currency';
		} else {
			$currency_field = $prefix . 'currency_meta.meta_value';
		}

		$currency_args = $this->get_customer_currency_args_from_request();
		if ( ! empty( $currency_args['currency_is'] ) ) {
			$currency_is = sprintf( "'%s'", implode( "', '", array_map( 'esc_sql', $currency_args['currency_is'] ) ) );
			$clauses[]   = "AND {$currency_field} IN ({$currency_is})";
		}

		if ( ! empty( $currency_args['currency_is_not'] ) ) {
			$currency_is_not = sprintf( "'%s'", implode( "', '", array_map( 'esc_sql', $currency_args['currency_is_not'] ) ) );
			$clauses[]       = "AND {$currency_field} NOT IN ({$currency_is_not})";
		}

		if ( ! empty( $currency_args['currency'] ) ) {
			global $wpdb;
			$expression = "AND {$currency_field} = '%s'";
			$clauses[]  = $wpdb->prepare( $expression, $currency_args['currency'] ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		/**
		 * Filters the WHERE clauses after Multi-Currency customer currency filtering has been applied.
		 *
		 * @since 4.5.1
		 *
		 * @param string[] $clauses The WHERE clauses to be applied.
		 */
		return apply_filters( MultiCurrency::FILTER_PREFIX . 'filter_where_clauses', $clauses );
	}

	/**
	 * Convert amounts back to order currency (if a currency has been selected).
	 * Skipping it for default currency.
	 *
	 * @param string[] $clauses - An array containing the SELECT orders clauses to be applied.
	 * @return array
	 */
	public function filter_select_orders_clauses( array $clauses ): array {
		/**
		 * Filters whether the Multi-Currency SELECT orders clauses modification should be disabled.
		 *
		 * @since 4.9.0
		 *
		 * @param bool $disable Whether to disable the SELECT orders clauses modification. Default false.
		 */
		if ( apply_filters( MultiCurrency::FILTER_PREFIX . 'disable_filter_select_orders_clauses', false ) ) {
			return $clauses;
		}

		global $wpdb;
		$exchange_rate        = 'wcpay_multicurrency_exchange_rate_meta.meta_value';
		$stripe_exchange_rate = 'wcpay_multicurrency_stripe_exchange_rate_meta.meta_value';
		$net_total            = "{$wpdb->prefix}wc_order_stats.net_total";

		foreach ( $clauses as $k => $clause ) {
			if ( strpos( $clause, $net_total ) !== false ) {
				$is_orders_subquery = strpos( $clause, $net_total . ',' ) !== false;
				$variable           = $is_orders_subquery ? "$net_total," : $net_total;
				$alias              = $is_orders_subquery ? ' as net_total,' : '';
				$dp                 = wc_get_price_decimals();

				$converted = $this->generate_case_when(
					$stripe_exchange_rate,
					"ROUND($net_total / $stripe_exchange_rate, $dp)",
					"ROUND($net_total * $exchange_rate, $dp)"
				);
				// Use the native import input instead of reversing rounded totals with mutable metadata.
				if ( method_exists( OrderStatsDataStore::class, 'has_reporting_currency_columns' ) && OrderStatsDataStore::has_reporting_currency_columns() ) {
					$converted = "CASE WHEN {$wpdb->prefix}wc_order_stats.source_currency <> '' AND {$wpdb->prefix}wc_order_stats.source_net_total IS NOT NULL THEN {$wpdb->prefix}wc_order_stats.source_net_total ELSE $converted END";
				}
				$clauses[ $k ] = str_replace( $variable, $converted . $alias, $clause );
			}
		}

		/**
		 * Filters the SELECT orders clauses after Multi-Currency conversions have been applied.
		 *
		 * @since 4.9.0
		 *
		 * @param string[] $clauses The SELECT orders clauses to be applied.
		 */
		return apply_filters( MultiCurrency::FILTER_PREFIX . 'filter_select_orders_clauses', $clauses );
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
	 * @param float $amount The amount to convert into the store's default currency.
	 * @param float $exchange_rate The exchange rate to use for the conversion.
	 *
	 * @return float The converted amount.
	 */
	private function convert_amount( float $amount, float $exchange_rate ): float {
		return $amount * $exchange_rate;
	}

	/**
	 * Check whether the order stats table is referenced in the clauses, to work out whether
	 * to add the JOIN columns for Multi-Currency.
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
	 * Generate a case when statement using the provided variables.
	 *
	 * @param string $variable    The SQL variable we want to check for NULL.
	 * @param string $then        The THEN clause.
	 * @param string $else_clause The ELSE clause.
	 *
	 * @return string
	 */
	private function generate_case_when( string $variable, string $then, string $else_clause ): string {
		return "CASE WHEN {$variable} IS NOT NULL THEN {$then} ELSE {$else_clause} END";
	}

	/**
	 * Perform an SQL query to determine whether Multi Currency has ever been used on this store,
	 * by checking how many orders are in the database where an exchange currency rate has been stored.
	 *
	 * @return bool
	 */
	private function has_multi_currency_orders() {
		global $wpdb;

		// Using full SQL instead of variables to keep WPCS happy.
		if ( $this->is_cot_enabled() ) {
			$result = $wpdb->get_var(
				"SELECT EXISTS(
					SELECT 1
					FROM {$wpdb->prefix}wc_orders_meta
					WHERE meta_key = '_wcpay_multi_currency_order_exchange_rate'
					LIMIT 1)
				AS count;"
			);
		} else {
			$result = $wpdb->get_var(
				"SELECT EXISTS(
					SELECT 1
					FROM {$wpdb->postmeta}
					WHERE meta_key = '_wcpay_multi_currency_order_exchange_rate'
					LIMIT 1)
				AS count;"
			);
		}

		return intval( $result ) === 1;
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
	 * Check the passed in query params to see if currency has been passed in.
	 * Will return null if no currency variable was passed in, otherwise will
	 * return the currency.
	 *
	 * @return array
	 */
	private function get_customer_currency_args_from_request(): array {
		$args = [
			'currency_is'     => [],
			'currency_is_not' => [],
			'currency'        => null,
		];

		/* phpcs:disable WordPress.Security.NonceVerification */
		if ( isset( $_GET['currency_is'] ) && is_array( $_GET['currency_is'] ) ) {
			$args['currency_is'] = array_map( 'sanitize_text_field', wp_unslash( $_GET['currency_is'] ) );
		}

		if ( isset( $_GET['currency_is_not'] ) ) {
			$args['currency_is_not'] = array_map( 'sanitize_text_field', wp_unslash( $_GET['currency_is_not'] ) );
		}

		if ( isset( $_GET['currency'] ) ) {
			$args['currency'] = sanitize_text_field( wp_unslash( $_GET['currency'] ) );
		}
		/* phpcs:enable WordPress.Security.NonceVerification */

		return $args;
	}

	/**
	 * Set the SQL replacements variable.
	 *
	 * @return void
	 */
	private function set_sql_replacements() {
		$default_currency     = 'wcpay_multicurrency_default_currency_meta.meta_value';
		$exchange_rate        = 'wcpay_multicurrency_exchange_rate_meta.meta_value';
		$stripe_exchange_rate = 'wcpay_multicurrency_stripe_exchange_rate_meta.meta_value';

		$discount_amount       = $this->generate_case_when( $default_currency, $this->generate_case_when( $stripe_exchange_rate, "ROUND(discount_amount * {$stripe_exchange_rate}, 2)", "ROUND(discount_amount * (1 / {$exchange_rate} ), 2)" ), 'discount_amount' );
		$product_net_revenue   = $this->generate_case_when( $default_currency, $this->generate_case_when( $stripe_exchange_rate, "ROUND(product_net_revenue * {$stripe_exchange_rate}, 2)", "ROUND(product_net_revenue * (1 / {$exchange_rate} ), 2)" ), 'product_net_revenue' );
		$product_gross_revenue = $this->generate_case_when( $default_currency, $this->generate_case_when( $stripe_exchange_rate, "ROUND(product_gross_revenue * {$stripe_exchange_rate}, 2)", "ROUND(product_gross_revenue * (1 / {$exchange_rate} ), 2)" ), 'product_gross_revenue' );

		$discount_amount       = $this->get_historical_amount_sql( 'discount_amount', $discount_amount );
		$product_net_revenue   = $this->get_historical_amount_sql( 'product_net_revenue', $product_net_revenue );
		$product_gross_revenue = $this->get_historical_amount_sql( 'product_gross_revenue', $product_gross_revenue );

		$this->sql_replacements = [
			'generic'    => [
				'discount_amount'       => $discount_amount,
				'product_net_revenue'   => $product_net_revenue,
				'product_gross_revenue' => $product_gross_revenue,
			],
			'orders'     => [
				'discount_amount' => $discount_amount,
			],
			'products'   => [
				'product_net_revenue'   => $product_net_revenue,
				'product_gross_revenue' => $product_gross_revenue,
			],
			'variations' => [
				'product_net_revenue'   => $product_net_revenue,
				'product_gross_revenue' => $product_gross_revenue,
			],
			'categories' => [
				'product_net_revenue'   => $product_net_revenue,
				'product_gross_revenue' => $product_gross_revenue,
			],
			'taxes'      => [
				'SUM(total_tax)'    => 'SUM(' . $this->get_historical_amount_sql( 'total_tax', $this->generate_case_when( $default_currency, $this->generate_case_when( $stripe_exchange_rate, "ROUND(total_tax * {$stripe_exchange_rate}, 2)", "ROUND(total_tax * (1 / {$exchange_rate} ), 2)" ), 'total_tax' ) ) . ')',
				'SUM(order_tax)'    => 'SUM(' . $this->get_historical_amount_sql( 'order_tax', $this->generate_case_when( $default_currency, $this->generate_case_when( $stripe_exchange_rate, "ROUND(order_tax * {$stripe_exchange_rate}, 2)", "ROUND(order_tax * (1 / {$exchange_rate} ), 2)" ), 'order_tax' ) ) . ')',
				'SUM(shipping_tax)' => 'SUM(' . $this->get_historical_amount_sql( 'shipping_tax', $this->generate_case_when( $default_currency, $this->generate_case_when( $stripe_exchange_rate, "ROUND(shipping_tax * {$stripe_exchange_rate}, 2)", "ROUND(shipping_tax * (1 / {$exchange_rate} ), 2)" ), 'shipping_tax' ) ) . ')',
			],
			'coupons'    => [
				'discount_amount' => $discount_amount,
			],
		];
	}

	/**
	 * Convert coupon-code segments using the order import's historical rate.
	 *
	 * @param array  $columns Segment SELECT expressions.
	 * @param string $table Coupon lookup table.
	 * @return array
	 */
	public function filter_coupon_segment_columns( array $columns, string $table ): array {
		// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Filter documented in filter_select_clauses().
		if ( ! isset( $columns['reporting_missing_orders'], $columns['amount'] ) || apply_filters( MultiCurrency::FILTER_PREFIX . 'disable_filter_select_clauses', false ) ) {
			return $columns;
		}
		global $wpdb;
		$stats                               = $wpdb->prefix . 'wc_order_stats';
		$condition                           = $this->get_reporting_condition_sql();
		$amount                              = $this->get_historical_amount_sql( "$table.discount_amount", "$table.discount_amount" );
		$columns['amount']                   = "SUM($amount) AS amount";
		$columns['reporting_missing_orders'] = "COUNT(DISTINCT CASE WHEN $condition THEN NULL ELSE CASE WHEN $stats.parent_id > 0 THEN $stats.parent_id ELSE $table.order_id END END) AS reporting_missing_orders";
		return $columns;
	}

	/**
	 * Convert product-level coupon segments using the order import's historical rate.
	 *
	 * @param array  $columns Segment SELECT expressions.
	 * @param string $table Product lookup table.
	 * @return array
	 */
	public function filter_coupon_product_segment_columns( array $columns, string $table ): array {
		// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Filter documented in filter_select_clauses().
		if ( ! isset( $columns['reporting_missing_orders'], $columns['amount'] ) || apply_filters( MultiCurrency::FILTER_PREFIX . 'disable_filter_select_clauses', false ) ) {
			return $columns;
		}
		global $wpdb;
		$stats                               = $wpdb->prefix . 'wc_order_stats';
		$condition                           = $this->get_reporting_condition_sql();
		$amount                              = $this->get_historical_amount_sql( "$table.coupon_amount", "$table.coupon_amount" );
		$columns['amount']                   = "SUM($amount) AS amount";
		$columns['reporting_missing_orders'] = "COUNT(DISTINCT CASE WHEN $condition THEN NULL ELSE CASE WHEN $stats.parent_id > 0 THEN $stats.parent_id ELSE $table.order_id END END) AS reporting_missing_orders";
		return $columns;
	}

	/**
	 * Convert product segments using the order import's historical rate.
	 *
	 * @param array  $columns Segment SELECT expressions.
	 * @param string $table Product lookup table.
	 * @return array
	 */
	public function filter_product_segment_columns( array $columns, string $table ): array {
		// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Filter documented in filter_select_clauses().
		if ( ! isset( $columns['reporting_missing_orders'], $columns['net_revenue'] ) || apply_filters( MultiCurrency::FILTER_PREFIX . 'disable_filter_select_clauses', false ) ) {
			return $columns;
		}
		global $wpdb;
		$stats                               = $wpdb->prefix . 'wc_order_stats';
		$condition                           = $this->get_reporting_condition_sql();
		$amount                              = $this->get_historical_amount_sql( "$table.product_net_revenue", "$table.product_net_revenue" );
		$columns['net_revenue']              = "SUM($amount) AS net_revenue";
		$columns['reporting_missing_orders'] = "COUNT(DISTINCT CASE WHEN $condition THEN NULL ELSE CASE WHEN $stats.parent_id > 0 THEN $stats.parent_id ELSE $table.order_id END END) AS reporting_missing_orders";
		return $columns;
	}

	/**
	 * Convert tax segments using the order import's historical rate.
	 *
	 * @param array  $columns Segment SELECT expressions.
	 * @param string $table Tax lookup table.
	 * @return array
	 */
	public function filter_tax_segment_columns( array $columns, string $table ): array {
		// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Filter documented in filter_select_clauses().
		if ( ! isset( $columns['reporting_missing_orders'] ) || apply_filters( MultiCurrency::FILTER_PREFIX . 'disable_filter_select_clauses', false ) ) {
			return $columns;
		}
		global $wpdb;
		$stats     = $wpdb->prefix . 'wc_order_stats';
		$condition = $this->get_reporting_condition_sql();
		foreach ( [ 'total_tax', 'order_tax', 'shipping_tax' ] as $metric ) {
			if ( isset( $columns[ $metric ] ) ) {
				$amount             = $this->get_historical_amount_sql( "$table.$metric", "$table.$metric" );
				$columns[ $metric ] = "SUM($amount) AS $metric";
			}
		}
		$columns['reporting_missing_orders'] = "COUNT(DISTINCT CASE WHEN $condition THEN NULL ELSE CASE WHEN $stats.parent_id > 0 THEN $stats.parent_id ELSE $table.order_id END END) AS reporting_missing_orders";
		return $columns;
	}

	/**
	 * Use the rate recorded by the same import as the order reporting totals.
	 *
	 * @param string $amount Native amount SQL expression.
	 * @param string $legacy Expression for core versions without the contract.
	 * @return string
	 */
	private function get_historical_amount_sql( string $amount, string $legacy ): string {
		if ( ! method_exists( OrderStatsDataStore::class, 'has_reporting_currency_columns' ) || ! OrderStatsDataStore::has_reporting_currency_columns() ) {
			return $legacy;
		}
		global $wpdb;
		$table     = $wpdb->prefix . 'wc_order_stats';
		$condition = $this->get_reporting_condition_sql();
		$dp        = wc_get_price_decimals();
		return "CASE WHEN $condition THEN CASE WHEN $table.reporting_basis = 'native' THEN $amount ELSE ROUND($amount * $table.reporting_exchange_rate, $dp) END ELSE NULL END";
	}

	/**
	 * Qualification shared by converted amounts and their missing-order count.
	 *
	 * @return string
	 */
	private function get_reporting_condition_sql(): string {
		global $wpdb;
		$table    = $wpdb->prefix . 'wc_order_stats';
		$currency = $this->multi_currency->get_default_currency()->get_code();
		return $table . '.reporting_currency = ' . $wpdb->prepare( '%s', $currency ) . " AND $table.reporting_exchange_rate > 0 AND $table.reporting_basis IN ('native', 'historical_order_rate', 'historical_processor_rate')";
	}

	/**
	 * Checks whether Custom Order Tables are enabled.
	 *
	 * @return bool
	 */
	private function is_cot_enabled(): bool {
		return class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled();
	}
}
