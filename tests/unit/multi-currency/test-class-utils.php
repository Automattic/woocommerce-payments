<?php
/**
 * Class WCPay_Multi_Currency_Utils_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WCPay\Multi_Currency\Utils unit tests.
 */
class WCPay_Multi_Currency_Utils_Tests extends WP_UnitTestCase {
	/**
	 * WCPay\Multi_Currency\Utils instance.
	 *
	 * @var WCPay\Multi_Currency\Utils
	 */
	private $utils;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->utils = new WCPay\Multi_Currency\Utils();
	}

	public function test_is_call_in_backtrace_return_false() {
		$this->assertFalse( $this->utils->is_call_in_backtrace( [ 'test' ] ) );
	}

	public function test_is_call_in_backtrace_return_true() {
		$this->assertTrue( $this->utils->is_call_in_backtrace( [ 'WCPay_Multi_Currency_Utils_Tests->test_is_call_in_backtrace_return_true' ] ) );
	}

	public function test_get_customer_country_returns_wc_default_location() {
		update_option( 'woocommerce_default_customer_address', 'base' );
		update_option( 'woocommerce_default_country', 'GB' );

		$this->assertSame( 'GB', $this->utils->get_customer_country() );
	}

	public function test_get_customer_country_returns_wc_customer_country() {
		update_option( 'woocommerce_default_customer_address', 'base' );
		update_option( 'woocommerce_default_country', 'GB' );

		WC()->customer->set_billing_country( 'BR' );

		$this->assertSame( 'BR', $this->utils->get_customer_country() );
	}
}
