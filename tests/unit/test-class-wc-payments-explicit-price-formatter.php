<?php
/**
 * Class WC_Payments_Explicit_Price_Formatter_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Explicit_Price_Formatter unit tests.
 */
class WC_Payments_Explicit_Price_Formatter_Test extends WP_UnitTestCase {
	public function test_get_explicit_price_with_order_currency() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_currency' )->willReturn( 'BRL' );

		$this->assertSame( 'R$ 5,90 BRL', WC_Payments_Explicit_Price_Formatter::get_explicit_price( 'R$ 5,90', $order ) );
	}

	public function test_get_explicit_price_with_store_currency() {
		$this->assertSame( '$10.30 USD', WC_Payments_Explicit_Price_Formatter::get_explicit_price( '$10.30' ) );
	}

	public function test_get_explicit_price_skips_already_explicit_prices() {
		$this->assertSame( '$10.30 USD', WC_Payments_Explicit_Price_Formatter::get_explicit_price( '$10.30 USD' ) );
	}
}
