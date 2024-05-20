<?php
/**
 * Class Get_Reporting_Payment_Activity_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\Get_Reporting_Payment_Activity;

/**
 * WCPay\Core\Server\Request unit tests.
 */
class Get_Reporting_Payment_Activity_Test extends WCPAY_UnitTestCase {

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;
	/**
	 * Mock WC_Payments_Http_Interface.
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

	public function test_exception_will_throw_if_required_params_are_not_set() {
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$this->expectExceptionMessage( 'date_start' );
		$this->expectExceptionMessage( 'date_end' );
		$this->expectExceptionMessage( 'timezone' );

		$request = new Get_Reporting_Payment_Activity( $this->mock_api_client, $this->mock_wc_payments_http_client, null );
		$request->get_params();
	}

	public function test_exception_will_throw_if_date_start_is_invalid() {
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$this->expectExceptionMessage( 'abc is not a valid date' );

		$request = new Get_Reporting_Payment_Activity( $this->mock_api_client, $this->mock_wc_payments_http_client, null );
		$request->set_date_start( 'abc' );
		$request->set_date_end( '2024-05-06T23:59:59' );
		$request->set_timezone( 'America/Los_Angeles' );
	}

	public function test_exception_will_throw_if_date_end_is_invalid() {
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$this->expectExceptionMessage( 'abc is not a valid date' );

		$request = new Get_Reporting_Payment_Activity( $this->mock_api_client, $this->mock_wc_payments_http_client, null );
		$request->set_date_start( '2024-05-06T23:59:59' );
		$request->set_date_end( 'abc' );
		$request->set_timezone( 'America/Los_Angeles' );
	}

	public function test_exception_will_throw_if_timezone_is_invalid() {
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$this->expectExceptionMessage( 'abc is not a valid timezone' );

		$request = new Get_Reporting_Payment_Activity( $this->mock_api_client, $this->mock_wc_payments_http_client, null );
		$request->set_date_start( '2024-05-06T00:00:00' );
		$request->set_date_end( '2024-05-06T23:59:59' );
		$request->set_timezone( 'abc' );
	}

	public function test_if_parameters_are_valid() {
		$request = new Get_Reporting_Payment_Activity( $this->mock_api_client, $this->mock_wc_payments_http_client, null );
		$request->set_date_start( '2024-05-06T00:00:00' );
		$request->set_date_end( '2024-05-06T23:59:59' );
		$request->set_timezone( 'America/Los_Angeles' );

		$this->assertSame( '2024-05-06T00:00:00', $request->get_param( 'date_start' ) );
		$this->assertSame( '2024-05-06T23:59:59', $request->get_param( 'date_end' ) );
		$this->assertSame( 'America/Los_Angeles', $request->get_param( 'timezone' ) );
	}
}
