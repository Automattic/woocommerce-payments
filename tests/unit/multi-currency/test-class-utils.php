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
}
