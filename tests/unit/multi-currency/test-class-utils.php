<?php
/**
 * Class WCPay_Multi_Currency_Utils_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WCPay\MultiCurrency\Utils unit tests.
 */
class WCPay_Multi_Currency_Utils_Tests extends WCPAY_UnitTestCase {
	/**
	 * WCPay\MultiCurrency\Utils instance.
	 *
	 * @var WCPay\MultiCurrency\Utils
	 */
	private $utils;

	/**
	 * $_SERVER values the is_admin_api_request() tests overwrite.
	 *
	 * @var array
	 */
	private $original_server;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->utils           = new WCPay\MultiCurrency\Utils();
		$this->original_server = $_SERVER;
	}

	/**
	 * Restores $_SERVER so a leftover wp-admin referer does not make later suites look like admin requests.
	 */
	public function tear_down() {
		$_SERVER = $this->original_server;

		parent::tear_down();
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

	public function test_is_admin_api_request_returns_false_with_store_api() {
		$_SERVER['HTTP_REFERER'] = 'http://example.org/wp-admin/';
		$_SERVER['REQUEST_URI']  = trailingslashit( rest_get_url_prefix() ) . 'wc/store/v1/checkout';

		$this->assertFalse( $this->utils->is_admin_api_request() );

		unset( $_REQUEST['rest_route'] );
	}

	public function test_matched_store_api_route_takes_precedence_over_the_url() {
		$_SERVER['HTTP_REFERER'] = 'http://example.org/wp-admin/';
		$_SERVER['REQUEST_URI']  = trailingslashit( rest_get_url_prefix() ) . 'batch/v1';

		$this->assertFalse( $this->utils->is_store_api_request() );
		$this->assertTrue( $this->utils->is_admin_api_request() );

		$this->utils->set_matched_store_api_route( true );

		try {
			$this->assertTrue( $this->utils->is_store_api_request() );
			$this->assertFalse( $this->utils->is_admin_api_request() );
		} finally {
			$this->utils->set_matched_store_api_route( false );
		}
	}
}
