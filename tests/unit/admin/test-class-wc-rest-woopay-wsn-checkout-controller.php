<?php
/**
 * Class WC_REST_WooPay_WSN_Checkout_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Unit tests for WC_REST_WooPay_WSN_Checkout_Controller.
 */
class WC_REST_WooPay_WSN_Checkout_Controller_Test extends WCPAY_UnitTestCase {

	/**
	 * @var WC_REST_WooPay_WSN_Checkout_Controller
	 */
	private $controller;

	public function set_up() {
		parent::set_up();
		$this->controller = new WC_REST_WooPay_WSN_Checkout_Controller();
	}

	// ---- check_permission ----

	public function test_check_permission_returns_true_in_dev_mode() {
		WC_Payments::mode()->dev();
		$this->assertTrue( $this->controller->check_permission() );
		WC_Payments::mode()->live();
	}

	public function test_check_permission_returns_false_in_live_mode() {
		WC_Payments::mode()->live();
		$this->assertFalse( $this->controller->check_permission() );
	}

	// ---- handle_checkout: error paths ----

	public function test_handle_checkout_returns_400_for_empty_items() {
		$request = new WP_REST_Request( 'POST', '/wcpay/v1/woopay/wsn-checkout' );
		$request->set_param( 'items', [] );

		$result = $this->controller->handle_checkout( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wsn_checkout_no_items', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}
}
