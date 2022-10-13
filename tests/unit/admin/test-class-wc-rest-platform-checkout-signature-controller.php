<?php
/**
 * Class WC_REST_Platform_Checkout_Signature_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\API_Exception;

/**
 * WC_REST_Payments_Accounts_Controller unit tests.
 */
class WC_REST_Platform_Checkout_Signature_Controller_Test extends WCPAY_UnitTestCase {
	/**
	 * The system under test.
	 *
	 * @var WC_REST_Platform_Checkout_Signature_Controller
	 */
	private $controller;

	/**
	 * Gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		$this->controller = new WC_REST_Platform_Checkout_Signature_Controller();
	}

	public function test_get_platform_checkout_signature_success() {
		$response      = $this->controller->get_platform_checkout_signature();
		$response_data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'signature', $response_data );
	}
}
