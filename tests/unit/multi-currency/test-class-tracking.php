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
	 * Mock currencies.
	 *
	 * @var array
	 */
	private $mock_currencies = [
		// Default currency.
		'USD' => [
			'rate' => 1,
		],
		// Zero decimal currency with non-default settings.
		'BIF' => [
			'rate'           => 1985.96,
			'rate_type'      => 'manual',
			'price_rounding' => '50',
			'price_charm'    => '-1',
		],
		// Currency with non-default settings.
		'CAD' => [
			'rate'           => 1.27,
			'rate_type'      => 'manual',
			'price_rounding' => '0.50',
			'price_charm'    => '-0.01',
		],
		// Zero decimal currency with default settings.
		'CLP' => [
			'rate'           => 786.60,
			'rate_type'      => 'automatic',
			'price_rounding' => '100',
			'price_charm'    => '0.00',
		],
		// Currency with default settings.
		'EUR' => [
			'rate'           => 0.84,
			'rate_type'      => 'automatic',
			'price_rounding' => '1.00',
			'price_charm'    => '0.00',
		],
		// Zero decimal currency with no settings. Mimics currency added, but not modified.
		'JPY' => [
			'rate' => 109.86,
		],
		// Currency with no settings. Mimics currency added, but not modified.
		'GBP' => [
			'rate' => 0.72,
		],
	];

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
						'code'            => 'BIF',
						'name'            => 'Burundian franc',
						'is_zero_decimal' => true,
						'rate_type'       => 'manual',
						'price_rounding'  => '50',
						'price_charm'     => '-1',
					],
					'CAD' => [
						'code'            => 'CAD',
						'name'            => 'Canadian dollar',
						'is_zero_decimal' => false,
						'rate_type'       => 'manual',
						'price_rounding'  => '0.50',
						'price_charm'     => '-0.01',
					],
					'CLP' => [
						'code'            => 'CLP',
						'name'            => 'Chilean peso',
						'is_zero_decimal' => true,
						'rate_type'       => 'automatic (default)',
						'price_rounding'  => '100 (default)',
						'price_charm'     => '0.00 (default)',
					],
					'EUR' => [
						'code'            => 'EUR',
						'name'            => 'Euro',
						'is_zero_decimal' => false,
						'rate_type'       => 'automatic (default)',
						'price_rounding'  => '1.00 (default)',
						'price_charm'     => '0.00 (default)',
					],
					'JPY' => [
						'code'            => 'JPY',
						'name'            => 'Japanese yen',
						'is_zero_decimal' => true,
						'rate_type'       => 'automatic (default)',
						'price_rounding'  => '100 (default)',
						'price_charm'     => '0.00 (default)',
					],
					'GBP' => [
						'code'            => 'GBP',
						'name'            => 'Pound sterling',
						'is_zero_decimal' => false,
						'rate_type'       => 'automatic (default)',
						'price_rounding'  => '1.00 (default)',
						'price_charm'     => '0.00 (default)',
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
		foreach ( $this->mock_currencies as $code => $settings ) {
			$currency = new WCPay\MultiCurrency\Currency( $code, $settings['rate'] );

			if ( isset( $settings['rate_type'] ) ) {
				update_option( 'wcpay_multi_currency_exchange_rate_' . $currency->get_id(), $settings['rate_type'] );
			}

			// Set the rounding as MultiCurrency->get_enabled_currencies() does.
			$default_rounding = $currency->get_is_zero_decimal() ? '100' : '1.00';
			$price_rounding   = isset( $settings['price_rounding'] ) ? $settings['price_rounding'] : $default_rounding;
			$currency->set_rounding( $price_rounding );

			// Set the charm as MultiCurrency->get_enabled_currencies() does.
			$price_charm = isset( $settings['price_charm'] ) ? $settings['price_charm'] : 0.00;
			$currency->set_charm( $price_charm );

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
