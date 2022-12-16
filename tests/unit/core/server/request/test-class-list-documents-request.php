<?php
/**
 * Class List_Deposits_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\List_Documents;

/**
 * WCPay\Core\Server\List_Documents_Test unit tests.
 */
class List_Documents_Test extends WCPAY_UnitTestCase {

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


	public function test_exception_will_throw_if_date_after_is_invalid_format() {
		$request = new List_Documents( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_date_after( '2022-01-01' );
	}
	public function test_exception_will_throw_if_date_before_is_invalid_format() {
		$request = new List_Documents( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_date_before( '2022-01-01' );
	}

	public function test_list_deposits_request_will_be_date() {
		$page         = 2;
		$page_size    = 50;
		$direction    = 'asc';
		$sort         = 'date';
		$filters      = [
			'key'  => 'value',
			'page' => 3,
		];
		$date_after   = '2022-01-01 00:00:00';
		$date_before  = '2022-02-01 00:00:00';
		$date_between = $date_after . '-' . $date_before;
		$match        = 'match';
		$type         = 'bill';
		$type_is_not  = 'passport';

		$request = new List_Documents( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_page( $page );
		$request->set_page_size( $page_size );
		$request->set_sort_direction( $direction );
		$request->set_sort_by( $sort );
		$request->set_date_after( $date_after );
		$request->set_date_before( $date_before );
		$request->set_date_between( $date_between );
		$request->set_match( $match );
		$request->set_type_is( $type );
		$request->set_type_is_not( $type_is_not );
		$request->set_filters( $filters );
		$this->assertNotSame( $filters['page'], $request->get_param( 'page' ) ); // Test immutability of filter key.

		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertSame( $page, $params['page'] );
		$this->assertSame( $page_size, $params['pagesize'] );
		$this->assertSame( $sort, $params['sort'] );
		$this->assertSame( $direction, $params['direction'] );
		$this->assertSame( $date_after, $params['date_after'] );
		$this->assertSame( $date_before, $params['date_before'] );
		$this->assertSame( $date_between, $params['date_between'] );
		$this->assertSame( $match, $params['match'] );
		$this->assertSame( $type, $params['type_is'] );
		$this->assertSame( $type_is_not, $params['type_is_not'] );
		$this->assertSame( $filters['key'], $params['key'] );
		$this->assertSame( 'GET', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::DOCUMENTS_API, $request->get_api() );

	}
	public function test_list_deposits_request_will_be_date_using_from_rest_request_function() {
		$page         = 2;
		$page_size    = 50;
		$direction    = 'asc';
		$sort         = 'date';
		$date_after   = '2022-01-01 00:00:00';
		$date_before  = '2022-02-01 00:00:00';
		$date_between = $date_after . '-' . $date_before;
		$match        = 'match';
		$type         = 'bill';
		$type_is_not  = 'passport';

		$rest_request = new WP_REST_Request( 'GET' );
		$rest_request->set_param( 'page', $page );
		$rest_request->set_param( 'pagesize', $page_size );
		$rest_request->set_param( 'sort', $sort );
		$rest_request->set_param( 'direction', $direction );
		$rest_request->set_param( 'date_after', $date_after );
		$rest_request->set_param( 'date_before', $date_before );
		$rest_request->set_param( 'date_between', $date_between );
		$rest_request->set_param( 'match', $match );
		$rest_request->set_param( 'type_is', $type );
		$rest_request->set_param( 'type_is_not', $type_is_not );

		$request = List_Documents::from_rest_request( $rest_request );

		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertSame( $page, $params['page'] );
		$this->assertSame( $page_size, $params['pagesize'] );
		$this->assertSame( $sort, $params['sort'] );
		$this->assertSame( $direction, $params['direction'] );
		$this->assertSame( $date_after, $params['date_after'] );
		$this->assertSame( $date_before, $params['date_before'] );
		$this->assertSame( $date_between, $params['date_between'] );
		$this->assertSame( $match, $params['match'] );
		$this->assertSame( $type, $params['type_is'] );
		$this->assertSame( $type_is_not, $params['type_is_not'] );
		$this->assertSame( 'GET', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::DOCUMENTS_API, $request->get_api() );
	}
}
