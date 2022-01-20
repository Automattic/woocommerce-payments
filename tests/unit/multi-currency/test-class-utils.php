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

	public function test_is_page_with_vars_return_false() {
		$this->assertFalse( $this->utils->is_page_with_vars( [ 'test' ], [ 'test' ] ) );
	}

	public function test_is_page_with_vars_return_true() {
		global $wp;
		$wp->query_vars = [
			'pagename'       => 'checkout',
			'order-received' => '42',
		];
		$this->assertTrue( $this->utils->is_page_with_vars( [ 'checkout' ], [ 'order-received' ] ) );
	}

	public function test_is_admin_api_request_returns_false() {
		$_SERVER['HTTP_REFERER'] = 'http://example.org/';
		$_SERVER['REQUEST_URI']  = trailingslashit( rest_get_url_prefix() );
		$this->assertFalse( $this->utils->is_admin_api_request() );
	}

	public function test_is_admin_api_request_returns_true() {
		$_SERVER['HTTP_REFERER'] = 'http://example.org/wp-admin/';
		$_SERVER['REQUEST_URI']  = trailingslashit( rest_get_url_prefix() );
		$this->assertTrue( $this->utils->is_admin_api_request() );
	}
}
