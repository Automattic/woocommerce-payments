<?php
/**
 * Class Paginated_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Server\Request\Paginated;

/**
 * WCPay\Core\Server\Paginated_Test unit tests.
 */
class Paginated_Test extends WCPAY_UnitTestCase {

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


	public function test_paginated_request_will_be_created() {
		$page      = 2;
		$page_size = 50;
		$direction = 'asc';
		$sort      = 'created';
		$filters   = [
			'key' => 'value',
		];

		$request = new class( $this->mock_api_client, $this->mock_wc_payments_http_client ) extends Paginated
		{
			public function get_api(): string {
				return '';
			}
		};
		$request->set_page( $page );
		$request->set_page_size( $page_size );
		$request->set_sort_direction( $direction );
		$request->set_sort_by( $sort );
		$request->set_filters( $filters );

		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertSame( $page, $params['page'] );
		$this->assertSame( $page_size, $params['pagesize'] );
		$this->assertSame( $sort, $params['sort'] );
		$this->assertSame( $direction, $params['direction'] );
		$this->assertSame( $filters['key'], $params['key'] );
		$this->assertSame( 'GET', $request->get_method() );

	}

	public function test_create_from_rest_request() {
		$page      = 2;
		$page_size = 50;
		$direction = 'asc';
		$sort      = 'created';

		$rest_request = new WP_REST_Request( 'GET' );
		$rest_request->set_param( 'page', $page );
		$rest_request->set_param( 'pagesize', $page_size );
		$rest_request->set_param( 'sort', $sort );
		$rest_request->set_param( 'sort', $sort );
		$rest_request->set_param( 'direction', $direction );

		$class   = new class( $this->mock_api_client, $this->mock_wc_payments_http_client ) extends Paginated
		{
			public function get_api(): string {
				return '';
			}
		};
		$request = $class::from_rest_request( $rest_request );

		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertSame( $page, $params['page'] );
		$this->assertSame( $page_size, $params['pagesize'] );
		$this->assertSame( $sort, $params['sort'] );
		$this->assertSame( $direction, $params['direction'] );

	}
}
