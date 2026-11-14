<?php
/**
 * Class WCPay_Multi_Currency_Utils_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use Automattic\WooCommerce\Currency\ActiveCurrency;

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
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->utils = new WCPay\MultiCurrency\Utils();
	}

	/**
	 * Restores the request context so later suites do not inherit it.
	 */
	public function tear_down() {
		WCPay_Test_Request_Context::reset();

		parent::tear_down();
	}

	public function test_is_call_in_backtrace_return_false() {
		$this->assertFalse( $this->utils->is_call_in_backtrace( [ 'test' ] ) );
	}

	public function test_is_call_in_backtrace_return_true() {
		$this->assertTrue( $this->utils->is_call_in_backtrace( [ 'WCPay_Multi_Currency_Utils_Tests->test_is_call_in_backtrace_return_true' ] ) );
	}

	public function test_is_store_api_request_follows_the_request_context() {
		WCPay_Test_Request_Context::set( 'store_api' );
		$this->assertTrue( WCPay\MultiCurrency\Utils::is_store_api_request() );

		WCPay_Test_Request_Context::set( 'rest' );
		$this->assertFalse( WCPay\MultiCurrency\Utils::is_store_api_request() );

		WCPay_Test_Request_Context::set( null );
		$this->assertFalse( WCPay\MultiCurrency\Utils::is_store_api_request() );
	}

	public function test_is_store_api_request_ignores_the_rest_route_while_the_context_is_unknown() {
		$_REQUEST['rest_route'] = '/wc/store/v1/cart';
		WCPay_Test_Request_Context::set( null );

		$this->assertFalse( WCPay\MultiCurrency\Utils::is_store_api_request() );

		unset( $_REQUEST['rest_route'] );
	}

	public function test_is_admin_rest_request_is_known_only_inside_the_route() {
		$request = new WP_REST_Request( 'GET', '/wc/v3/orders' );
		$this->assertFalse( WCPay\MultiCurrency\Utils::is_admin_rest_request() );

		WCPay\MultiCurrency\Utils::enter_rest_route( $request, false, true );
		$this->assertTrue( WCPay\MultiCurrency\Utils::is_admin_rest_request() );

		WCPay\MultiCurrency\Utils::leave_rest_route( $request );
		$this->assertFalse( WCPay\MultiCurrency\Utils::is_admin_rest_request() );
	}

	public function test_is_admin_rest_request_is_false_for_shoppers_and_the_store_api() {
		$request = new WP_REST_Request( 'GET', '/wc/v3/orders' );

		WCPay\MultiCurrency\Utils::enter_rest_route( $request, false, false );
		$this->assertFalse( WCPay\MultiCurrency\Utils::is_admin_rest_request() );
		WCPay\MultiCurrency\Utils::leave_rest_route( $request );

		WCPay\MultiCurrency\Utils::enter_rest_route( $request, true, true );
		$this->assertFalse( WCPay\MultiCurrency\Utils::is_admin_rest_request() );
		WCPay\MultiCurrency\Utils::leave_rest_route( $request );
	}

	public function test_nested_rest_routes_restore_the_outer_one() {
		$outer = new WP_REST_Request( 'GET', '/wc/v3/orders' );
		$inner = new WP_REST_Request( 'GET', '/wc/store/v1/cart' );

		WCPay\MultiCurrency\Utils::enter_rest_route( $outer, false, true );
		WCPay\MultiCurrency\Utils::enter_rest_route( $inner, true, true );
		$this->assertFalse( WCPay\MultiCurrency\Utils::is_admin_rest_request() );
		$this->assertTrue( WCPay\MultiCurrency\Utils::get_rest_route()['shopper'] );

		WCPay\MultiCurrency\Utils::leave_rest_route( $inner );
		$this->assertTrue( WCPay\MultiCurrency\Utils::is_admin_rest_request() );

		WCPay\MultiCurrency\Utils::leave_rest_route( $outer );
		$this->assertNull( WCPay\MultiCurrency\Utils::get_rest_route() );
	}

	public function test_is_frontend_request_for_rest_follows_the_route_once_known() {
		$request = new WP_REST_Request( 'GET', '/wc/v3/orders' );
		WCPay_Test_Request_Context::set( 'rest' );
		$this->assertTrue( WCPay\MultiCurrency\Utils::is_frontend_request() );

		WCPay\MultiCurrency\Utils::enter_rest_route( $request, false, false );
		$this->assertFalse( WCPay\MultiCurrency\Utils::is_frontend_request() );
		WCPay\MultiCurrency\Utils::leave_rest_route( $request );

		WCPay\MultiCurrency\Utils::enter_rest_route( $request, true, false );
		$this->assertTrue( WCPay\MultiCurrency\Utils::is_frontend_request() );
		WCPay\MultiCurrency\Utils::leave_rest_route( $request );
	}

	public function test_is_non_admin_request_follows_the_request_context() {
		WCPay_Test_Request_Context::set( 'admin' );
		$this->assertFalse( \WCPay\MultiCurrency\Utils::is_non_admin_request() );

		WCPay_Test_Request_Context::set( 'cron' );
		$this->assertFalse( \WCPay\MultiCurrency\Utils::is_non_admin_request() );

		WCPay_Test_Request_Context::set( 'rest' );
		$this->assertTrue( \WCPay\MultiCurrency\Utils::is_non_admin_request() );

		WCPay_Test_Request_Context::set( 'store_api' );
		$this->assertTrue( \WCPay\MultiCurrency\Utils::is_non_admin_request() );
	}

	public function test_is_cron_request_uses_the_request_context_when_given() {
		$this->assertFalse( \WCPay\MultiCurrency\Utils::is_cron_request( 'frontend' ) );
		$this->assertTrue( \WCPay\MultiCurrency\Utils::is_cron_request( 'cron' ) );
	}

	public function test_is_in_shipping_method_rates_needs_the_rates_operation_and_the_method() {
		$subject = new \stdClass();

		$scope = ActiveCurrency::scope( '', 'shipping.rates', $subject );
		$this->assertTrue( $this->utils->is_in_shipping_method_rates( 'stdClass' ) );
		$this->assertFalse( $this->utils->is_in_shipping_method_rates( 'WC_Shipping_Fedex' ) );
		$scope->release();

		$scope = ActiveCurrency::scope( '', 'cart.calculate_totals', $subject );
		$this->assertFalse( $this->utils->is_in_shipping_method_rates( 'stdClass' ) );
		$scope->release();
	}

	public function test_helper_scopes_do_not_hide_order_and_shipping_operations() {
		$subject = new \stdClass();

		// phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Scope guards, held for their lifetime.
		$rates = ActiveCurrency::scope( '', 'shipping.rates', $subject );
		$order = ActiveCurrency::scope( '', 'order' );
		$help  = ActiveCurrency::scope( '', 'my-plugin.helper' );

		$this->assertTrue( $this->utils->is_in_shipping_method_rates( 'stdClass' ) );
		$this->assertTrue( ActiveCurrency::is_in_operation( 'order' ) );

		$help  = null;
		$order = null;
		$rates = null;
		// phpcs:enable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$this->assertFalse( $this->utils->is_in_shipping_method_rates( 'stdClass' ) );
	}
}
