<?php
/**
 * Class WCPay_Multi_Currency_Frontend_Prices_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WCPay\Multi_Currency\Frontend_Prices unit tests.
 */
class WCPay_Multi_Currency_Frontend_Prices_Tests extends WP_UnitTestCase {
	/**
	 * Mock WCPay\Multi_Currency\Multi_Currency.
	 *
	 * @var WCPay\Multi_Currency\Multi_Currency|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_multi_currency;

	/**
	 * WCPay\Multi_Currency\Frontend_Prices instance.
	 *
	 * @var WCPay\Multi_Currency\Frontend_Prices
	 */
	private $frontend_prices;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_multi_currency = $this->createMock( WCPay\Multi_Currency\Multi_Currency::class );
		$this->mock_multi_currency
			->method( 'get_price' )
			->willReturnCallback(
				function ( $price ) {
					return (float) $price * 2.5;
				}
			);

		$this->frontend_prices = new WCPay\Multi_Currency\Frontend_Prices( $this->mock_multi_currency );
	}

	public function test_get_currency_code() {
		$current_currency = new WCPay\Multi_Currency\Currency( 'USD' );
		$this->mock_multi_currency->method( 'get_selected_currency' )->willReturn( $current_currency );

		$this->assertSame( 'USD', $this->frontend_prices->get_current_currency_code() );
	}

	public function test_get_product_price_returns_empty_price() {
		$this->assertSame( '', $this->frontend_prices->get_product_price( '' ) );
	}

	public function test_get_product_price_converts_prices() {
		$this->assertSame( 25.0, $this->frontend_prices->get_product_price( 10.0 ) );
	}

	public function test_get_variation_price_range_converts_non_empty_prices() {
		$base_variation_prices = [
			'price'      => [
				1 => '10.0',
				2 => '12.0',
			],
			'sale_price' => [
				1 => '6.0',
				2 => '8.0',
			],
		];

		$this->assertSame(
			[
				'price'      => [
					1 => 25.0,
					2 => 30.0,
				],
				'sale_price' => [
					1 => 15.0,
					2 => 20.0,
				],
			],
			$this->frontend_prices->get_variation_price_range( $base_variation_prices )
		);
	}

	public function test_get_variation_price_range_skips_empty_prices() {
		$base_variation_prices = [
			'sale_price' => [
				1 => '',
				2 => '',
			],
		];

		$this->assertSame(
			[
				'sale_price' => [
					1 => '',
					2 => '',
				],
			],
			$this->frontend_prices->get_variation_price_range( $base_variation_prices )
		);
	}

	public function test_exchange_rate_is_added_to_prices_hash() {
		$this->assertSame(
			[ 'existing_item', 2.5 ],
			$this->frontend_prices->add_exchange_rate_to_variation_prices_hash( [ 'existing_item' ] )
		);
	}

	public function test_get_shipping_rates_prices_converts_rates() {
		$shipping_zone_1       = new WC_Shipping_Zone();
		$shipping_zone_2       = new WC_Shipping_Zone();
		$shipping_zone_1->cost = '10.0';
		$shipping_zone_2->cost = '0.0';

		$base_shipping_rates = [
			'shipping_rate_1' => $shipping_zone_1,
			'shipping_rate_2' => $shipping_zone_2,
		];

		$shipping_rates = $this->frontend_prices->get_shipping_rates_prices( $base_shipping_rates );

		$this->assertSame( 25.0, $shipping_rates['shipping_rate_1']->cost );
		$this->assertSame( 0.0, $shipping_rates['shipping_rate_2']->cost );
	}

	public function test_get_coupon_amount_returns_empty_amount() {
		$this->assertSame( '', $this->frontend_prices->get_coupon_amount( '', null ) );
	}

	public function test_get_coupon_amount_returns_percent_coupon_amount() {
		$percent_coupon = new WC_Coupon();
		$percent_coupon->set_discount_type( 'percent' );

		$this->assertSame( '10', $this->frontend_prices->get_coupon_amount( '10', $percent_coupon ) );
	}

	public function test_get_coupon_amount_converts_fixed_cart_amount() {
		$fixed_cart_coupon = new WC_Coupon();
		$fixed_cart_coupon->set_discount_type( 'fixed_cart' );

		$this->assertSame( 25.0, $this->frontend_prices->get_coupon_amount( '10', $fixed_cart_coupon ) );
	}

	public function test_get_coupon_mix_max_amount_returns_empty_amount() {
		$this->assertSame( '', $this->frontend_prices->get_coupon_min_max_amount( '' ) );
	}

	public function test_get_coupon_mix_max_amount_converts_amount() {
		$this->assertSame( 12.5, $this->frontend_prices->get_coupon_min_max_amount( '5.0' ) );
	}
}
