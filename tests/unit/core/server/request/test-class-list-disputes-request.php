<?php
/**
 * Class List_Disputes_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\List_Disputes;

/**
 * WCPay\Core\Server\List_Deposits_Test unit tests.
 */
class List_Disputes_Test extends WCPAY_UnitTestCase {

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

	public function test_exception_will_throw_if_created_after_is_invalid_format() {
		$request = new List_Disputes( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_created_after( '2022-01-01' );
	}
	public function test_exception_will_throw_if_created_before_is_invalid_format() {
		$request = new List_Disputes( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_created_before( '2022-01-01' );
	}

	public function test_list_disputes_request_will_be_created() {
		$page            = 2;
		$page_size       = 50;
		$direction       = 'asc';
		$sort            = 'created';
		$filters         = [
			'key' => 'value',
		];
		$created_after   = '2022-01-01 00:00:00';
		$created_before  = '2022-02-01 00:00:00';
		$created_between = [ $created_after, $created_before ];
		$match           = 'match';
		$currency        = 'usd';
		$status          = 'completed';
		$status_is_not   = 'failed';
		$search          = 'term';

		$request = new List_Disputes( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_page( $page );
		$request->set_page_size( $page_size );
		$request->set_sort_direction( $direction );
		$request->set_sort_by( $sort );
		$request->set_created_after( $created_after );
		$request->set_created_before( $created_before );
		$request->set_created_between( $created_between );
		$request->set_match( $match );
		$request->set_currency_is( $currency );
		$request->set_status_is( $status );
		$request->set_status_is_not( $status_is_not );
		$request->set_search( $search );
		$request->set_filters( $filters );

		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertSame( $page, $params['page'] );
		$this->assertSame( $page_size, $params['pagesize'] );
		$this->assertSame( $sort, $params['sort'] );
		$this->assertSame( $direction, $params['direction'] );
		$this->assertSame( $created_after, $params['created_after'] );
		$this->assertSame( $created_before, $params['created_before'] );
		$this->assertSame( $created_between, $params['created_between'] );
		$this->assertSame( $match, $params['match'] );
		$this->assertSame( $currency, $params['currency_is'] );
		$this->assertSame( $status, $params['status_is'] );
		$this->assertSame( $status_is_not, $params['status_is_not'] );
		$this->assertSame( $search, $params['search'] );
		$this->assertSame( $filters['key'], $params['key'] );
		$this->assertSame( 'GET', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::DISPUTES_API, $request->get_api() );

	}
	public function test_list_disputes_request_will_be_created_using_from_rest_request_function() {
		$page            = 2;
		$page_size       = 50;
		$direction       = 'asc';
		$sort            = 'created';
		$created_after   = '2022-01-01 00:00:00';
		$created_before  = '2022-02-01 00:00:00';
		$created_between = [ $created_after, $created_before ];
		$match           = 'match';
		$currency        = 'usd';
		$status          = 'completed';
		$status_is_not   = 'failed';
		$search          = 'term';

		$rest_request = new WP_REST_Request( 'GET' );
		$rest_request->set_param( 'page', $page );
		$rest_request->set_param( 'pagesize', $page_size );
		$rest_request->set_param( 'sort', $sort );
		$rest_request->set_param( 'direction', $direction );
		$rest_request->set_param( 'date_after', $created_after );
		$rest_request->set_param( 'date_before', $created_before );
		$rest_request->set_param( 'date_between', $created_between );
		$rest_request->set_param( 'match', $match );
		$rest_request->set_param( 'store_currency_is', $currency );
		$rest_request->set_param( 'status_is', $status );
		$rest_request->set_param( 'status_is_not', $status_is_not );
		$rest_request->set_param( 'search', $search );

		$request = List_Disputes::from_rest_request( $rest_request );

		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertSame( $page, $params['page'] );
		$this->assertSame( $page_size, $params['pagesize'] );
		$this->assertSame( $sort, $params['sort'] );
		$this->assertSame( $direction, $params['direction'] );
		$this->assertSame( $created_after, $params['created_after'] );
		$this->assertSame( $created_before, $params['created_before'] );
		$this->assertSame( $created_between, $params['created_between'] );
		$this->assertSame( $match, $params['match'] );
		$this->assertSame( $currency, $params['currency_is'] );
		$this->assertSame( $status, $params['status_is'] );
		$this->assertSame( $status_is_not, $params['status_is_not'] );
		$this->assertSame( $search, $params['search'] );
		$this->assertSame( 'GET', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::DISPUTES_API, $request->get_api() );

	}
}
