<?php
/**
 * Class WC_REST_Payments_Reader_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\API_Exception;

/**
 * WC_REST_Payments_Files_Controller_Test unit tests.
 */
class WC_REST_Payments_Files_Controller_Test extends WP_UnitTestCase {
	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Payments_Files_Controller
	 */
	private $controller;

	/**
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	public function setUp() {
		parent::setUp();
		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		$this->controller      = new WC_REST_Payments_Files_Controller( $this->mock_api_client );
	}

	public function test_get_file() {
		$file_response = [
			'file_content' => base64_encode( 'test_file_content' ), // @codingStandardsIgnoreLine
			'content_type' => 'image/png',
		];

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_file' )
			->with( 'file_mock_ID' )
			->willReturn( [ 'purpose' => 'business_logo' ] );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_file_contents' )
			->with( 'file_mock_ID' )
			->willReturn( $file_response );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'file_id', 'file_mock_ID' );

		$response = $this->controller->get_file( $request );

		$this->assertSame( 'test_file_content', $response->get_data() );
		$this->assertSame( 200, $response->status );

		delete_transient( WC_Payments_File_Service::CACHE_KEY_PREFIX_PURPOSE . 'file_mock_ID' );
	}

	public function test_get_file_no_file() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_file' )
			->with( 'file_mock_ID' )
			->willReturn( [ 'purpose' => 'business_logo' ] );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_file_contents' )
			->with( 'file_mock_ID' )
			->willThrowException( new API_Exception( "Error: No such file upload: 'file_mock_ID'", 'resource_missing', 500 ) );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'file_id', 'file_mock_ID' );
		$response = $this->controller->get_file( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 404, $data['status'] );

		delete_transient( WC_Payments_File_Service::CACHE_KEY_PREFIX_PURPOSE . 'file_mock_ID' );
	}

	public function test_get_file_content_exception() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_file' )
			->with( 'file_mock_ID' )
			->willReturn( [ 'purpose' => 'business_logo' ] );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_file_contents' )
			->with( 'file_mock_ID' )
			->willThrowException( new API_Exception( 'Error: test', 'wcpay_bad_request', 400 ) );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'file_id', 'file_mock_ID' );
		$response = $this->controller->get_file( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 500, $data['status'] );

		delete_transient( WC_Payments_File_Service::CACHE_KEY_PREFIX_PURPOSE . 'file_mock_ID' );
	}

	public function test_get_file_no_permission() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_file' )
			->with( 'file_mock_ID' )
			->willReturn( [ 'purpose' => 'check_permission_purpose' ] );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'file_id', 'file_mock_ID' );
		$response = $this->controller->get_file( $request );
		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertSame( $data['status'], 401 );

		delete_transient( WC_Payments_File_Service::CACHE_KEY_PREFIX_PURPOSE . 'file_mock_ID' );
	}

	public function test_get_file_exception() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_file' )
			->with( 'file_mock_ID' )
			->willThrowException( new API_Exception( 'Error: test', 'wcpay_bad_request', 400 ) );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'file_id', 'file_mock_ID' );
		$response = $this->controller->get_file( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 500, $data['status'] );
	}
}
