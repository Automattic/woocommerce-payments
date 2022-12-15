<?php
/**
 * Class List_Deposits_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Server\Request\List_Deposits;

/**
 * WCPay\Core\Server\List_Deposits_Test unit tests.
 */
class List_Deposits_Test extends WCPAY_UnitTestCase {

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


	public function test_list_deposits_request_will_be_created() {
		$page      = 2;
		$page_size = 50;
		$direction = 'asc';
		$sort      = 'created';
		$filters   = [
			'key'  => 'value',
			'page' => 3,
		];

		$request = new List_Deposits( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_page( $page );
		$request->set_page_size( $page_size );
		$request->set_sort_direction( $direction );
		$request->set_sort_by( $sort );
		$request->set_filters( $filters );
		$this->assertNotSame( $filters['page'], $request->get_param( 'page' ) ); // Test immutability of filter key.

		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertSame( $page, $params['page'] );
		$this->assertSame( $page_size, $params['pagesize'] );
		$this->assertSame( $sort, $params['sort'] );
		$this->assertSame( $direction, $params['direction'] );
		$this->assertSame( $filters['key'], $params['key'] );
		$this->assertSame( 'GET', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::DEPOSITS_API, $request->get_api() );

	}
}
