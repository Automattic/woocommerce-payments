<?php
/**
 * Class WCPay_Multi_Currency_Tracking_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\Currency;

/**
 * WCPay\MultiCurrency\Tracking unit tests.
 */
class WCPay_Multi_Currency_Tracking_Tests extends WP_UnitTestCase {
	/**
	 * WCPay\MultiCurrency\Tracking instance.
	 *
	 * @var WCPay\MultiCurrency\Tracking
	 */
	private $tracking;

	/**
	 * @var int
	 */
	private $timestamp_for_testing;

	/**
	 * Mock enabled currencies.
	 *
	 * @var Currency[]
	 */
	private $mock_enabled_currencies = [];

	/**
	 * Mock default currency.
	 *
	 * @var Currency
	 */
	private $mock_default_currency;

	/**
	 * Mock orders.
	 *
	 * @var Array of order ids.
	 */
	private $mock_orders = [];

	/**
	 * Mock WCPay\MultiCurrency\MultiCurrency.
	 *
	 * @var WCPay\MultiCurrency\MultiCurrency|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_multi_currency;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->set_up_mock_enabled_currencies();
		$this->add_mock_orders_with_meta();
		$this->mock_default_currency = new WCPay\MultiCurrency\Currency( get_woocommerce_currency(), 1 );

		$this->mock_multi_currency = $this->createMock( WCPay\MultiCurrency\MultiCurrency::class );
		$this->mock_multi_currency
			->method( 'get_enabled_currencies' )
			->willReturn( $this->mock_enabled_currencies );
		$this->mock_multi_currency
			->method( 'get_default_currency' )
			->willReturn( $this->mock_default_currency );

		$this->tracking = new WCPay\MultiCurrency\Tracking( $this->mock_multi_currency );
	}

	public function tearDown() {
		$this->delete_mock_orders();

		parent::tearDown();
	}

	/**
	 * @dataProvider woocommerce_filter_provider
	 */
	public function test_registers_woocommerce_filters_properly( $filter, $function_name ) {
		$priority = has_filter( $filter, [ $this->tracking, $function_name ] );
		$this->assertGreaterThan(
			10,
			$priority,
			"Filter '$filter' was not registered with '$function_name' with a priority higher than the default."
		);
	}

	public function woocommerce_filter_provider() {
		return [
			[ 'woocommerce_tracker_data', 'add_tracker_data' ],
		];
	}

	public function test_add_tracker_data_returns_correctly() {
		$expected = [
			'wcpay_multi_currency' => [
				'enabled_currencies' => [
					'BIF' => [
						'code' => 'BIF',
						'name' => 'Burundian franc',
					],
					'CAD' => [
						'code' => 'CAD',
						'name' => 'Canadian dollar',
					],
					'GBP' => [
						'code' => 'GBP',
						'name' => 'Pound sterling',
					],
				],
				'default_currency'   => [
					'code' => 'USD',
					'name' => 'United States (US) dollar',
				],
				'order_count'        => 5,
			],
		];

		$this->assertEquals( $expected, $this->tracking->add_tracker_data( [] ) );
	}

	private function set_up_mock_enabled_currencies() {
		$mock_currencies = [
			'USD' => 1,
			'BIF' => 1974,
			'CAD' => 1.206823,
			'GBP' => 0.708099,
		];

		foreach ( $mock_currencies as $code => $rate ) {
			$currency = new WCPay\MultiCurrency\Currency( $code, $rate );
			$this->mock_enabled_currencies[ $currency->get_code() ] = $currency;
		}
	}

	private function add_mock_orders_with_meta() {
		$post_data = [
			'post_type'   => 'shop_order',
			'post_status' => 'wc-processing',
		];

		for ( $i = 0; $i <= 4; $i++ ) {
			$order_id = wp_insert_post( $post_data );
			update_post_meta( $order_id, '_wcpay_multi_currency_order_exchange_rate', 2 );

			$this->mock_orders[] = $order_id;
		}
	}

	private function delete_mock_orders() {
		foreach ( $this->mock_orders as $order_id ) {
			wp_delete_post( $order_id, true );
		}
	}
}
