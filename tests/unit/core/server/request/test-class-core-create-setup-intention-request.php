<?php
/**
 * Class Create_Setup_Intention_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Constants\Payment_Method;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\Create_Setup_Intention;

/**
 * WCPay\Core\Server\Create_Setup_Intention_Test unit tests.
 */
class Create_Setup_Intention_Test extends WCPAY_UnitTestCase {

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;
	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_Http_Interface|MockObject
	 */
	private $mock_wc_payments_http_client;


	/**
	 * Set up the unit tests objects.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_api_client              = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_wc_payments_http_client = $this->createMock( WC_Payments_Http_Interface::class );
	}



	public function test_exception_will_throw_if_customer_id_is_invalid() {
		$request = new Create_Setup_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_customer( '1' );
	}

	public function test_exception_will_throw_if_customer_id_is_not_set() {
		$request = new Create_Setup_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_payment_method_types( [ Payment_Method::CARD ] );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->get_params();
	}
	public function test_exception_will_throw_if_payment_method_types_is_not_set() {
		$request = new Create_Setup_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_customer( 'cus_1' );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->get_params();
	}
	public function test_create_intent_request_will_be_created() {
		$customer = 'cus_1';
		$pm       = [ Payment_Method::CARD ];
		$request  = new Create_Setup_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_customer( 'cus_1' );
		$request->set_payment_method_types( [ Payment_Method::CARD ] );
		$this->assertInstanceOf( Create_Setup_Intention::class, $request );
		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertArrayHasKey( 'customer', $params );
		$this->assertSame( $customer, $params['customer'] );
		$this->assertArrayHasKey( 'payment_method_types', $params );
		$this->assertSame( $pm, $params['payment_method_types'] );
		$this->assertSame( 'false', $params['confirm'] );
		$this->assertSame( 'POST', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::SETUP_INTENTS_API, $request->get_api() );
	}


}
