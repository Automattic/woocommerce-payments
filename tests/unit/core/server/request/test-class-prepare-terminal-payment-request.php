<?php
/**
 * Class Prepare_Terminal_Payment_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\Prepare_Terminal_Payment;

/**
 * WCPay\Core\Server\Prepare_Terminal_Payment_Test unit tests.
 */
class Prepare_Terminal_Payment_Test extends WCPAY_UnitTestCase {

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

	public function test_exception_will_throw_if_intent_is_invalid() {
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		new Prepare_Terminal_Payment( $this->mock_api_client, $this->mock_wc_payments_http_client, '1' );
	}

	public function test_prepare_terminal_payment_request_will_be_created() {
		$intent_id = 'pi_1';
		$order_id  = 123;

		$request = new Prepare_Terminal_Payment( $this->mock_api_client, $this->mock_wc_payments_http_client, $intent_id );
		$request->set_order_id( $order_id );

		$this->assertSame( [ 'order_id' => $order_id ], $request->get_params() );
		$this->assertSame( 'POST', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::INTENTIONS_API . '/' . $intent_id . '/prepare_terminal_payment', $request->get_api() );
	}

	public function test_exception_will_throw_if_order_id_is_missing() {
		$this->expectException( Invalid_Request_Parameter_Exception::class );

		$request = new Prepare_Terminal_Payment( $this->mock_api_client, $this->mock_wc_payments_http_client, 'pi_1' );
		$request->get_params();
	}
}
