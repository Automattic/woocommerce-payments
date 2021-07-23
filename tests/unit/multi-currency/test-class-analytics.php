<?php
/**
 * Class WCPay_Multi_Currency_Compatibility_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\MultiCurrency\Analytics;
use WCPay\MultiCurrency\MultiCurrency;

/**
 * Analytics unit tests.
 */
class WCPay_Multi_Currency_Analytics_Tests extends WP_UnitTestCase {
	/**
	 * Analytics instance.
	 *
	 * @var Analytics
	 */
	private $analytics;

	/**
	 * Mock MultiCurrency
	 *
	 * @var MultiCurrency|MockObject
	 */
	private $mock_multi_currency;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->set_is_admin( true );
		$this->set_is_rest_request( true );
		add_filter(
			'woocommerce_is_rest_api_request',
			function() {
				return true;
			}
		);

		$this->mock_multi_currency = $this->createMock( MultiCurrency::class );
		$this->analytics           = new Analytics( $this->mock_multi_currency );
	}

	/**
	 * @group underTest
	 * @dataProvider woocommerce_filter_provider
	 */
	public function test_registers_woocommerce_filters_properly( $filter, $function_name ) {
		$priority = has_filter( $filter, [ $this->analytics, $function_name ] );

		$this->assertEquals(
			10,
			$priority,
			"Filter '$filter' was not registered with '$function_name' with the default priority."
		);
	}

	public function woocommerce_filter_provider() {
		return [
			[ 'admin_enqueue_scripts', 'enqueue_admin_scripts' ],
			[ 'woocommerce_analytics_clauses_select', 'filter_select_clauses' ],
			[ 'woocommerce_analytics_clauses_join', 'filter_join_clauses' ],
		];
	}

	/**
	 * @group underTest
	 * @dataProvider select_clause_provider
	 */
	public function test_filter_select_clauses( $context, $clauses, $expected ) {
		$this->assertEquals( $expected, $this->analytics->filter_select_clauses( $clauses, $context ) );
	}

	public function select_clause_provider() {
		global $wpdb;

		return [
			'generic select clause should be modified'   => [
				'unknown_stats_interval',
				[
					"DATE_FORMAT({$wpdb->prefix}wc_order_stats.date_created, '%Y-%m-%d') AS time_interval",
					"{$wpdb->prefix}wc_order_stats.order_id",
					", MAX({$wpdb->prefix}wc_order_stats.date_created) AS datetime_anchor",
					", SUM( CASE WHEN {$wpdb->prefix}wc_order_stats.parent_id = 0 THEN 1 ELSE 0 END ) as orders_count, COUNT( DISTINCT( {$wpdb->prefix}wc_order_stats.customer_id ) ) as total_customers, SUM({$wpdb->prefix}wc_order_stats.num_items_sold) as num_items_sold, COALESCE( coupons_count, 0 ) as coupons_count, SUM({$wpdb->prefix}wc_order_stats.net_total) AS net_revenue",
				],
				[
					"DATE_FORMAT({$wpdb->prefix}wc_order_stats.date_created, '%Y-%m-%d') AS time_interval",
					"{$wpdb->prefix}wc_order_stats.order_id",
					", MAX({$wpdb->prefix}wc_order_stats.date_created) AS datetime_anchor",
					", SUM( CASE WHEN {$wpdb->prefix}wc_order_stats.parent_id = 0 THEN 1 ELSE 0 END ) as orders_count, COUNT( DISTINCT( {$wpdb->prefix}wc_order_stats.customer_id ) ) as total_customers, SUM({$wpdb->prefix}wc_order_stats.num_items_sold) as num_items_sold, COALESCE( coupons_count, 0 ) as coupons_count, SUM(CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN {$wpdb->prefix}wc_order_stats.net_total * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE {$wpdb->prefix}wc_order_stats.net_total END) AS net_revenue",
					', wcpay_multicurrency_currency_postmeta.meta_value AS order_currency',
					', wcpay_multicurrency_default_currency_postmeta.meta_value AS order_default_currency',
					', wcpay_multicurrency_exchange_rate_postmeta.meta_value AS exchange_rate',
				],
			],
			'null context should not modify query'       => [
				null,
				[ "{$wpdb->prefix}wc_order_stats.net_total, {$wpdb->prefix}wc_order_stats.total_sales, discount_amount, product_net_revenue, product_gross_revenue" ],
				[ "{$wpdb->prefix}wc_order_stats.net_total, {$wpdb->prefix}wc_order_stats.total_sales, discount_amount, product_net_revenue, product_gross_revenue" ],
			],
			'products context should modify query'       => [
				'products_subquery',
				[
					", MAX({$wpdb->prefix}wc_order_stats.date_created) AS datetime_anchor",
					'product_net_revenue, product_gross_revenue',
				],
				[
					", MAX({$wpdb->prefix}wc_order_stats.date_created) AS datetime_anchor",
					'CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN product_net_revenue * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value) ELSE product_net_revenue END, CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN product_gross_revenue * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE product_gross_revenue END',
					', wcpay_multicurrency_currency_postmeta.meta_value AS order_currency',
					', wcpay_multicurrency_default_currency_postmeta.meta_value AS order_default_currency',
					', wcpay_multicurrency_exchange_rate_postmeta.meta_value AS exchange_rate',
				],
			],
			'order subquery context should modify query' => [
				'orders_subquery',
				[
					", {$wpdb->prefix}wc_order_stats.net_total, order_id,",
				],
				[
					", CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN {$wpdb->prefix}wc_order_stats.net_total * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE {$wpdb->prefix}wc_order_stats.net_total END AS net_total, order_id,",
					', wcpay_multicurrency_currency_postmeta.meta_value AS order_currency',
					', wcpay_multicurrency_default_currency_postmeta.meta_value AS order_default_currency',
					', wcpay_multicurrency_exchange_rate_postmeta.meta_value AS exchange_rate',
				],
			],
			'order stats context should modify query'    => [
				'orders_stats_inverval',
				[
					"{$wpdb->prefix}wc_order_stats.net_total, discount_amount, {$wpdb->prefix}wc_order_stats.shipping_total,",
				],
				[
					"CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN {$wpdb->prefix}wc_order_stats.net_total * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE {$wpdb->prefix}wc_order_stats.net_total END, CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN discount_amount * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE discount_amount END, CASE WHEN wcpay_multicurrency_default_currency_postmeta.meta_value IS NOT NULL THEN {$wpdb->prefix}wc_order_stats.shipping_total * (1 / wcpay_multicurrency_exchange_rate_postmeta.meta_value ) ELSE {$wpdb->prefix}wc_order_stats.shipping_total END,",
					', wcpay_multicurrency_currency_postmeta.meta_value AS order_currency',
					', wcpay_multicurrency_default_currency_postmeta.meta_value AS order_default_currency',
					', wcpay_multicurrency_exchange_rate_postmeta.meta_value AS exchange_rate',
				],
			],
		];
	}

	/**
	 * @group underTest
	 * @dataProvider join_clause_provider
	 */
	public function test_filter_join_clauses( $clauses, $expected ) {
		$this->assertEquals( $expected, $this->analytics->filter_join_clauses( $clauses ) );
	}

	public function join_clause_provider() {
		global $wpdb;

		return [
			'adds to empty clauses array'     => [
				[],
				[
					"LEFT JOIN {$wpdb->postmeta} wcpay_multicurrency_currency_postmeta ON {$wpdb->prefix}wc_order_stats.order_id = wcpay_multicurrency_currency_postmeta.post_id AND wcpay_multicurrency_currency_postmeta.meta_key = '_order_currency'",
					"LEFT JOIN {$wpdb->postmeta} wcpay_multicurrency_default_currency_postmeta ON {$wpdb->prefix}wc_order_stats.order_id = wcpay_multicurrency_default_currency_postmeta.post_id AND wcpay_multicurrency_default_currency_postmeta.meta_key = '_wcpay_multi_currency_order_default_currency'",
					"LEFT JOIN {$wpdb->postmeta} wcpay_multicurrency_exchange_rate_postmeta ON {$wpdb->prefix}wc_order_stats.order_id = wcpay_multicurrency_exchange_rate_postmeta.post_id AND wcpay_multicurrency_exchange_rate_postmeta.meta_key = '_wcpay_multi_currency_order_exchange_rate'",
				],
			],
			'adds to non-empty clauses array' => [
				[
					'JOIN my_custom_table ON field1 = field2',
				],
				[
					'JOIN my_custom_table ON field1 = field2',
					"LEFT JOIN {$wpdb->postmeta} wcpay_multicurrency_currency_postmeta ON {$wpdb->prefix}wc_order_stats.order_id = wcpay_multicurrency_currency_postmeta.post_id AND wcpay_multicurrency_currency_postmeta.meta_key = '_order_currency'",
					"LEFT JOIN {$wpdb->postmeta} wcpay_multicurrency_default_currency_postmeta ON {$wpdb->prefix}wc_order_stats.order_id = wcpay_multicurrency_default_currency_postmeta.post_id AND wcpay_multicurrency_default_currency_postmeta.meta_key = '_wcpay_multi_currency_order_default_currency'",
					"LEFT JOIN {$wpdb->postmeta} wcpay_multicurrency_exchange_rate_postmeta ON {$wpdb->prefix}wc_order_stats.order_id = wcpay_multicurrency_exchange_rate_postmeta.post_id AND wcpay_multicurrency_exchange_rate_postmeta.meta_key = '_wcpay_multi_currency_order_exchange_rate'",
				],
			],
		];
	}

	/**
	 * @param bool $is_admin
	 */
	private function set_is_admin( bool $is_admin ) {
		global $current_screen;

		if ( ! $is_admin ) {
			$current_screen = null;
			return;
		}

		$current_screen = $this->getMockBuilder( \stdClass::class )
			->setMethods( [ 'in_admin' ] )
			->getMock();

		$current_screen->method( 'in_admin' )->willReturn( $is_admin );
	}

	private function set_is_rest_request() {
		$_SERVER['REQUEST_URI'] = '/ajax';
	}
}
