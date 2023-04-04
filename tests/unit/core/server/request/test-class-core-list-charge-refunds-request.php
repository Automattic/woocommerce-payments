<?php
/**
 * Class Refund_Charge_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\List_Charge_Refunds;

/**
 * WCPay\Core\Server\Request unit tests.
 */
class List_Charge_Refunds_Test extends WCPAY_UnitTestCase {

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

	public function test_exception_will_throw_if_limit_is_invalid() {
		$request = new List_Charge_Refunds( $this->mock_api_client, $this->mock_wc_payments_http_client, 'py_xyz' );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_limit( 0 );
	}

	public function test_exception_will_throw_if_charge_id_is_not_set() {
		$this->expectException( Invalid_Request_Parameter_Exception::class );

		new List_Charge_Refunds( $this->mock_api_client, $this->mock_wc_payments_http_client, null );
	}

	public function test_exception_will_throw_if_charge_id_is_invalid() {
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		new List_Charge_Refunds( $this->mock_api_client, $this->mock_wc_payments_http_client, '1' );
	}

	public function test_py_prefix_will_not_throw_exception() {
		new List_Charge_Refunds( $this->mock_api_client, $this->mock_wc_payments_http_client, 'py_xyz' );
		$this->addToAssertionCount( 1 ); // We're not asserting anything, just not expecting an exception.
	}

	public function test_list_charge_refunds_request_class_is_created() {
		$request = new List_Charge_Refunds( $this->mock_api_client, $this->mock_wc_payments_http_client, 'ch_mock' );
		$this->assertSame( WC_Payments_API_Client::REFUNDS_API . '/ch_mock', $request->get_api() );
	}
}
