<?php
/**
 * Class Get_Transactions_Summary_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Server\Request\Get_Transactions_Summary;

/**
 * WCPay\Core\Server\Get_Transactions_Summary_Test unit tests.
 */
class Get_Transactions_Summary_Test extends WCPAY_UnitTestCase {
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

	public function test_get_transactions_summary_request_uses_summary_endpoint() {
		$request = new Get_Transactions_Summary( $this->mock_api_client, $this->mock_wc_payments_http_client );

		$this->assertSame( 'GET', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::TRANSACTIONS_API . '/summary', $request->get_api() );
	}

	public function test_get_transactions_summary_request_sets_filters_and_deposit_id() {
		$request = new Get_Transactions_Summary( $this->mock_api_client, $this->mock_wc_payments_http_client );

		$request->set_filters(
			[
				'source_is'   => 'card',
				'type_is_in'  => [ 'charge' ],
				'search'      => [ 'txn_123' ],
				'empty_value' => '',
			]
		);
		$request->set_deposit_id( 'po_mock' );

		$params = $request->get_params();

		$this->assertSame( 'card', $params['source_is'] );
		$this->assertSame( [ 'charge' ], $params['type_is_in'] );
		$this->assertSame( [ 'txn_123' ], $params['search'] );
		$this->assertSame( 'po_mock', $params['deposit_id'] );
		$this->assertArrayNotHasKey( 'empty_value', $params );
	}

	public function test_get_transactions_summary_request_omits_empty_deposit_id() {
		$request = new Get_Transactions_Summary( $this->mock_api_client, $this->mock_wc_payments_http_client );

		$request->set_deposit_id( '' );

		$this->assertArrayNotHasKey( 'deposit_id', $request->get_params() );
	}

	public function test_get_transactions_summary_request_formats_response_as_raw_array() {
		$request  = new Get_Transactions_Summary( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$response = [ 'count' => 1 ];

		$this->assertSame( $response, $request->format_response( $response ) );
	}
}
