<?php
/**
 * Class Create_And_Confirm_Intention_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\Get_Intent;

/**
 * WCPay\Core\Server\Request unit tests.
 */
class Get_Intention_Test extends WCPAY_UnitTestCase {

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

	public function test_exception_will_throw_if_payment_intent_is_not_set() {
		$request = new Get_Intent( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->get_api();
	}
	public function test_payment_intent_is_immutable_once_set() {
		$request = new Get_Intent( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_intent_id( 'pi_1' );
		$api = $request->get_api();
		$request->set_intent_id( 'pi_2' );
		$this->assertSame( $api, $request->get_api() );
	}
}
