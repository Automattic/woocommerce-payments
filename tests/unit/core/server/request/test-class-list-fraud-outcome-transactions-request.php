<?php
/**
 * Class List_Deposits_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Server\Request\List_Fraud_Outcome_Transactions;

/**
 * WCPay\Core\Server\List_Fraud_Outcome_Transactions_Test unit tests.
 */
class List_Fraud_Outcome_Transactions_Test extends WCPAY_UnitTestCase {

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

	public function test_list_fraud_outcome_transactions_request() {
		$page        = 2;
		$page_size   = 50;
		$direction   = 'asc';
		$sort        = 'date';
		$search      = [ 'search' ];
		$search_term = 'search_term';
		$status      = 'block';

		$request = new List_Fraud_Outcome_Transactions( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_page( $page );
		$request->set_page_size( $page_size );
		$request->set_sort_direction( $direction );
		$request->set_sort_by( $sort );
		$request->set_search( $search );
		$request->set_search_term( $search_term );
		$request->set_status( $status );

		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertSame( $page, $params['page'] );
		$this->assertSame( $page_size, $params['pagesize'] );
		$this->assertSame( $sort, $params['sort'] );
		$this->assertSame( $direction, $params['direction'] );
		$this->assertSame( $search, $params['search'] );
		$this->assertSame( $search_term, $params['search_term'] );
		$this->assertSame( 'GET', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::FRAUD_OUTCOMES_API . '/status/' . $status, $request->get_api() );

	}
	public function test_list_fraud_outcome_transactions_request_using_from_rest_request_function() {
		$page        = 2;
		$page_size   = 50;
		$direction   = 'asc';
		$sort        = 'date';
		$search      = [ 'search' ];
		$search_term = 'search_term';
		$status      = 'block';

		$rest_request = new WP_REST_Request( 'GET' );
		$rest_request->set_param( 'page', $page );
		$rest_request->set_param( 'pagesize', $page_size );
		$rest_request->set_param( 'sort', $sort );
		$rest_request->set_param( 'direction', $direction );
		$rest_request->set_param( 'search', $search );
		$rest_request->set_param( 'search_term', $search_term );
		$rest_request->set_param( 'status', $status );

		$request = List_Fraud_Outcome_Transactions::from_rest_request( $rest_request );

		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertSame( $page, $params['page'] );
		$this->assertSame( $page_size, $params['pagesize'] );
		$this->assertSame( $sort, $params['sort'] );
		$this->assertSame( $direction, $params['direction'] );
		$this->assertSame( $search, $params['search'] );
		$this->assertSame( $search_term, $params['search_term'] );
		$this->assertSame( 'GET', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::FRAUD_OUTCOMES_API . '/status/' . $status, $request->get_api() );
	}
}
