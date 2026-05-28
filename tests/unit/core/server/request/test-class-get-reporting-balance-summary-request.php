<?php
/**
 * Class Get_Reporting_Balance_Summary_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\Get_Reporting_Balance_Summary;

/**
 * WCPay\Core\Server\Get_Reporting_Balance_Summary_Test unit tests.
 */
class Get_Reporting_Balance_Summary_Test extends WCPAY_UnitTestCase {
	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|PHPUnit\Framework\MockObject\MockObject
	 */
	private $mock_api_client;

	/**
	 * Mock WC_Payments_Http_Interface.
	 *
	 * @var WC_Payments_Http_Interface|PHPUnit\Framework\MockObject\MockObject
	 */
	private $mock_wc_payments_http_client;

	/**
	 * Sets up the test case.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_api_client              = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_wc_payments_http_client = $this->createMock( WC_Payments_Http_Interface::class );
	}

	public function test_get_reporting_balance_summary_request_uses_balance_summary_endpoint() {
		$request = new Get_Reporting_Balance_Summary( $this->mock_api_client, $this->mock_wc_payments_http_client );

		$this->assertSame( 'GET', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::REPORTING_API . '/balance_summary', $request->get_api() );
		$this->assertSame( 'wcpay_get_reporting_balance_summary_request', $request->get_hook() );
	}

	public function test_get_reporting_balance_summary_request_sets_query_params() {
		$request = new Get_Reporting_Balance_Summary( $this->mock_api_client, $this->mock_wc_payments_http_client );

		$request->set_date_start( '2024-03-01T00:00:00.000Z' );
		$request->set_date_end( '2024-03-31T23:59:59.999Z' );
		$request->set_currency( 'USD' );

		$this->assertSame(
			[
				'date_start' => '2024-03-01T00:00:00.000Z',
				'date_end'   => '2024-03-31T23:59:59.999Z',
				'currency'   => 'usd',
			],
			$request->get_params()
		);
	}

	public function test_get_reporting_balance_summary_request_rejects_invalid_currency() {
		$request = new Get_Reporting_Balance_Summary( $this->mock_api_client, $this->mock_wc_payments_http_client );

		$this->expectException( Invalid_Request_Parameter_Exception::class );

		$request->set_currency( 'usd1' );
	}

	/**
	 * @dataProvider invalid_date_setter_provider
	 *
	 * @param string $setter Setter method.
	 */
	public function test_get_reporting_balance_summary_request_rejects_invalid_dates( string $setter ) {
		$request = new Get_Reporting_Balance_Summary( $this->mock_api_client, $this->mock_wc_payments_http_client );

		$this->expectException( Invalid_Request_Parameter_Exception::class );

		$request->{$setter}( 'March 1, 2024' );
	}

	public function invalid_date_setter_provider(): array {
		return [
			'date_start' => [ 'set_date_start' ],
			'date_end'   => [ 'set_date_end' ],
		];
	}
}
