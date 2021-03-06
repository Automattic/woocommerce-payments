<?php
/**
 * Class WCPay_Multi_Currency_Utils_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WCPay\MultiCurrency\Utils unit tests.
 */
class WCPay_Multi_Currency_Utils_Tests extends WP_UnitTestCase {
	/**
	 * WCPay\MultiCurrency\Utils instance.
	 *
	 * @var WCPay\MultiCurrency\Utils
	 */
	private $utils;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->utils = new WCPay\MultiCurrency\Utils();
	}

	public function test_is_call_in_backtrace_return_false() {
		$this->assertFalse( $this->utils->is_call_in_backtrace( [ 'test' ] ) );
	}

	public function test_is_call_in_backtrace_return_true() {
		$this->assertTrue( $this->utils->is_call_in_backtrace( [ 'WCPay_Multi_Currency_Utils_Tests->test_is_call_in_backtrace_return_true' ] ) );
	}
}
