<?php
/**
 * Class Checkout_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Core\Exceptions\Extend_Request_Exception;
use WCPay\Core\Exceptions\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\Create_And_Confirm_Intention;
use WCPay\Core\Server\Request\Generic;
use WCPay\Core\Server\Request\WooPay_Create_And_Confirm_Intention;
use WCPay\WooPay\Service\Checkout_Service;

/**
 * WCPay\Core\Mode unit tests.
 */
class Checkout_Service_Test extends WCPAY_UnitTestCase {

	/**
	 * @var \WCPay\Core\Server\Request $request
	 */
	private $request;

	/**
	 * @var Checkout_Service $checkout_service
	 */
	private $checkout_service;
	public function set_up() {
		parent::set_up();

		$this->checkout_service = new Checkout_Service();
		$this->request          = new Create_And_Confirm_Intention( $this->createMock( WC_Payments_API_Client::class ), $this->createMock( WC_Payments_Http_Interface::class ) );
	}

	public function test_exception_will_throw_if_base_request_parameter_is_invalid() {
		$this->expectException( Extend_Request_Exception::class );
		$this->checkout_service->create_woopay_intention_request( new Generic( 'api', Requests::POST ), new WC_Order(), true );
	}
	public function test_exception_will_throw_if_create_order_is_invalid() {
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$this->checkout_service->create_woopay_intention_request( $this->request, null, true );
	}
	public function test_exception_will_throw_if_create_woopay_intention_request_is_called_directly() {
		$this->expectException( Extend_Request_Exception::class );
		$this->checkout_service->create_woopay_intention_request( $this->request, new WC_Order(), true );
	}
	public function test_is_platform_payment_method_will_return_if_saved_payment_method_is_used() {
		$this->assertFalse( $this->checkout_service->is_platform_payment_method( true ) );
	}
	public function test_is_platform_payment_method_will_return_if_is_platform_payment_method_parameter_is_missing() {
		$this->assertFalse( $this->checkout_service->is_platform_payment_method( false ) );
	}
	public function test_is_platform_payment_method_will_return_if_is_platform_payment_method_parameter_is_not_boolean() {
		$_POST['wcpay-is-platform-payment-method'] = 'foo';
		$this->assertFalse( $this->checkout_service->is_platform_payment_method( false ) );
	}

	public function test_create_woopay_intention_request_will_create_request() {
		$fn = function( $request, $order, $using_saved_payment_method ) {
			$checkout_service = new Checkout_Service();

			return $checkout_service->create_woopay_intention_request( $request, $order, $using_saved_payment_method );
		};
		add_filter( 'test_create_woopay_intention_request_will_create_request_with_filter', $fn, 10, 3 );
		$order = wc_create_order();
		$this->request->set_amount( 1 );
		$this->request->set_currency_code( 'usd' );
		$this->request->set_payment_method( 'pm_1' );
		$this->request->set_customer( 'cus_1' );
		$this->request->set_metadata( [ 'order_number' => 1 ] );
		$request = $this->request->apply_filters( 'test_create_woopay_intention_request_will_create_request_with_filter', $order, true );
		$this->assertInstanceOf( WooPay_Create_And_Confirm_Intention::class, $request );
		remove_filter( 'test_create_woopay_intention_request_will_create_request', $fn, 10, 3 );

	}
}
