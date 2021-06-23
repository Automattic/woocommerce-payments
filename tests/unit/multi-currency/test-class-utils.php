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
	 * Store options to be restored between tests.
	 *
	 * @var array
	 */
	private $wc_options;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->utils = new WCPay\Multi_Currency\Utils();

		$this->wc_options['woocommerce_default_customer_address'] = get_option( 'woocommerce_default_customer_address' );
		$this->wc_options['woocommerce_default_country']          = get_option( 'woocommerce_default_country' );
	}

	public function tearDown() {
		update_option( 'woocommerce_default_customer_address', $this->wc_options['woocommerce_default_customer_address'] );
		update_option( 'woocommerce_default_country', $this->wc_options['woocommerce_default_country'] );
	}

	public function test_is_call_in_backtrace_return_false() {
		$this->assertFalse( $this->utils->is_call_in_backtrace( [ 'test' ] ) );
	}

	public function test_is_call_in_backtrace_return_true() {
		$this->assertTrue( $this->utils->is_call_in_backtrace( [ 'WCPay_Multi_Currency_Utils_Tests->test_is_call_in_backtrace_return_true' ] ) );
	}

	public function test_get_customer_country_returns_wc_default_location() {
		update_option( 'woocommerce_default_customer_address', 'base' );
		update_option( 'woocommerce_default_country', 'US:CA' );

		$this->assertSame( 'US', $this->utils->get_customer_country() );
	}

	public function test_get_customer_country_returns_wc_customer_country() {
		update_option( 'woocommerce_default_customer_address', 'base' );
		update_option( 'woocommerce_default_country', 'US:CA' );

		WC()->customer->set_billing_country( 'BR' );

		$this->assertSame( 'BR', $this->utils->get_customer_country() );
	}
}
