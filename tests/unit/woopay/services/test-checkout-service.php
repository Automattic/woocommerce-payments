<?php
/**
 * Class Checkout_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Payment_Type;
use WCPay\Core\Exceptions\Server\Request\Extend_Request_Exception;
use WCPay\Core\Server\Request\Create_And_Confirm_Intention;
use WCPay\Core\Server\Request\Create_And_Confirm_Setup_Intention;
use WCPay\Core\Server\Request\Generic;
use WCPay\Core\Server\Request\WooPay_Create_And_Confirm_Intention;
use WCPay\Core\Server\Request\WooPay_Create_And_Confirm_Setup_Intention;
use WCPay\Payment_Information;
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
	 * @var Payment_Information $payment_information
	 */
	private $payment_information;

	/**
	 * @var Checkout_Service $checkout_service
	 */
	private $checkout_service;
	public function set_up() {
		parent::set_up();

		$this->checkout_service    = new Checkout_Service();
		$this->request             = new Create_And_Confirm_Intention( $this->createMock( WC_Payments_API_Client::class ), $this->createMock( WC_Payments_Http_Interface::class ) );
		$this->payment_information = new Payment_Information( 'pm_mock', wc_create_order(), Payment_Type::SINGLE(), null );
	}

	public function test_exception_will_throw_if_base_request_parameter_is_invalid() {
		$this->expectException( Extend_Request_Exception::class );
		$this->checkout_service->create_intention_request( new Generic( 'api', Requests::POST ), $this->payment_information );
	}
	public function test_exception_will_throw_if_create_intention_request_is_called_directly() {
		$this->expectException( Extend_Request_Exception::class );
		$this->checkout_service->create_intention_request( $this->request, $this->payment_information );
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

	public function test_create_intention_request_will_create_request() {
		$this->request->set_amount( 1 );
		$this->request->set_currency_code( 'usd' );
		$this->request->set_payment_method( 'pm_1' );
		$this->request->set_customer( 'cus_1' );
		$this->request->set_metadata( [ 'order_number' => 1 ] );
		$request = $this->request->apply_filters( 'wcpay_create_intention_request', $this->payment_information );
		$this->assertInstanceOf( WooPay_Create_And_Confirm_Intention::class, $request );
	}
	public function test_create_intention_request_will_use_stripe_platform_on_checkout_page() {
		// Simulate behavior that current request is platform payment.
		$class = new class() extends Checkout_Service
		{
			public function is_platform_payment_method( bool $is_using_saved_payment_method ) {
				return true;
			}
		};
		add_filter( 'test_create_intention_request_will_use_stripe_platform_on_checkout_page', [ $class, 'create_intention_request' ], 1, 3 );
		$this->request->set_amount( 1 );
		$this->request->set_currency_code( 'usd' );
		$this->request->set_payment_method( 'pm_1' );
		$this->request->set_customer( 'cus_1' );
		$this->request->set_metadata( [ 'order_number' => 1 ] );
		$request = $this->request->apply_filters( 'test_create_intention_request_will_use_stripe_platform_on_checkout_page', $this->payment_information );
		$this->assertInstanceOf( WooPay_Create_And_Confirm_Intention::class, $request );
		$this->assertTrue( $request->get_param( 'is_platform_payment_method' ) );
		remove_filter( 'test_create_intention_request_will_use_stripe_platform_on_checkout_page', [ $class, 'create_intention_request' ], 1 );
	}

	public function test_create_and_confirm_setup_intention_request_will_create_request() {
		$request = new Create_And_Confirm_Setup_Intention( $this->createMock( WC_Payments_API_Client::class ), $this->createMock( WC_Payments_Http_Interface::class ) );
		$request->set_customer( 'cus_1' );
		$request->set_metadata( [ 'order_number' => 1 ] );
		$request->set_payment_method( $this->payment_information->get_payment_method() );
		$request = $request->apply_filters( 'wcpay_create_and_confirm_setup_intention_request', $this->payment_information, false, false );
		$this->assertInstanceOf( WooPay_Create_And_Confirm_Setup_Intention::class, $request );
	}
	public function test_create_and_confirm_setup_intention_request_will_create_request_for_platform_checkout() {
		$request = new Create_And_Confirm_Setup_Intention( $this->createMock( WC_Payments_API_Client::class ), $this->createMock( WC_Payments_Http_Interface::class ) );

		$class = new class() extends Checkout_Service
		{
			public function is_platform_payment_method( bool $is_using_saved_payment_method ) {
				return true;
			}
		};
		add_filter( 'test_create_and_confirm_setup_intention_request_will_create_request_for_platform_checkout', [ $class, 'create_and_confirm_setup_intention_request' ], 1, 4 );
		$request->set_customer( 'cus_1' );
		$request->set_metadata( [ 'order_number' => 1 ] );
		$request->set_payment_method( $this->payment_information->get_payment_method() );
		$request = $request->apply_filters( 'test_create_and_confirm_setup_intention_request_will_create_request_for_platform_checkout', $this->payment_information, true, true );
		$this->assertInstanceOf( WooPay_Create_And_Confirm_Setup_Intention::class, $request );
		$this->assertTrue( $request->get_param( 'is_platform_payment_method' ) );
		$this->assertTrue( $request->get_param( 'save_payment_method_to_platform' ) );
		$this->assertTrue( $request->get_param( 'save_in_platform_account' ) );
		remove_filter( 'test_create_and_confirm_setup_intention_request_will_create_request_for_platform_checkout', [ $class, 'create_and_confirm_setup_intention_request' ], 1 );
	}
}
