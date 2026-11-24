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
class WC_REST_Payments_Files_Controller_Test extends WCPAY_UnitTestCase {

	private const FILE_ROUTE = '/wc/v3/payments/file/';

	private const STORE_LOGO_FILE_ID = 'file_store_logo';

	private const OTHER_FILE_ID = 'file_other';

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

	/**
	 * The controller instance behind the registered file route.
	 *
	 * @var WC_REST_Payments_Files_Controller
	 */
	private $registered_controller;

	/**
	 * The API client the registered controller had before the test.
	 *
	 * @var WC_Payments_API_Client
	 */
	private $original_api_client;

	public function set_up() {
		parent::set_up();
		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		$this->controller      = new WC_REST_Payments_Files_Controller( $this->mock_api_client );

		$this->registered_controller = $this->get_registered_file_controller();
		$this->original_api_client   = $this->swap_api_client( $this->mock_api_client );
	}

	public function tear_down() {
		$this->swap_api_client( $this->original_api_client );
		WC_Payments::get_gateway()->update_option( 'platform_checkout_store_logo', '' );
		delete_transient( WC_Payments_File_Service::CACHE_KEY_PREFIX_PURPOSE . self::STORE_LOGO_FILE_ID . '_0' );
		delete_transient( WC_Payments_File_Service::CACHE_KEY_PREFIX_PURPOSE . self::OTHER_FILE_ID . '_0' );
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	public function test_file_route_rejects_logged_out_caller_before_looking_up_other_file() {
		WC_Payments::get_gateway()->update_option( 'platform_checkout_store_logo', self::STORE_LOGO_FILE_ID );
		wp_set_current_user( 0 );

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_file' );
		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_file_contents' );

		$response = rest_do_request( new WP_REST_Request( 'GET', self::FILE_ROUTE . self::OTHER_FILE_ID ) );

		$this->assertSame( 401, $response->get_status() );
		$this->assertFalse( get_transient( 'file_purpose_file_other_0' ) );
	}

	public function test_file_route_rejects_logged_out_caller_sending_file_id_array_in_query() {
		WC_Payments::get_gateway()->update_option( 'platform_checkout_store_logo', self::STORE_LOGO_FILE_ID );
		wp_set_current_user( 0 );

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_file' );

		$request = new WP_REST_Request( 'GET', self::FILE_ROUTE . self::OTHER_FILE_ID );
		$request->set_query_params( [ 'file_id' => [ self::STORE_LOGO_FILE_ID ] ] );
		$response = rest_do_request( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	public function test_file_route_judges_query_file_id_that_overrides_saved_logo_path() {
		WC_Payments::get_gateway()->update_option( 'platform_checkout_store_logo', self::STORE_LOGO_FILE_ID );
		wp_set_current_user( 0 );

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_file' );

		$request = new WP_REST_Request( 'GET', self::FILE_ROUTE . self::STORE_LOGO_FILE_ID );
		$request->set_query_params( [ 'file_id' => self::OTHER_FILE_ID ] );
		$response = rest_do_request( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	public function test_file_route_rejects_logged_out_caller_when_no_store_logo_is_saved() {
		WC_Payments::get_gateway()->update_option( 'platform_checkout_store_logo', '' );
		wp_set_current_user( 0 );

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_file' );

		$response = rest_do_request( new WP_REST_Request( 'GET', self::FILE_ROUTE . self::STORE_LOGO_FILE_ID ) );

		$this->assertSame( 401, $response->get_status() );
	}

	public function test_file_route_serves_saved_store_logo_to_logged_out_caller() {
		WC_Payments::get_gateway()->update_option( 'platform_checkout_store_logo', self::STORE_LOGO_FILE_ID );
		wp_set_current_user( 0 );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_file' )
			->with( 'file_store_logo' )
			->willReturn( [ 'purpose' => 'business_logo' ] );
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_file_contents' )
			->with( 'file_store_logo' )
			->willReturn(
				[
					'file_content' => base64_encode( 'logo_bytes' ), // @codingStandardsIgnoreLine
					'content_type' => 'image/png',
				]
			);

		$response = rest_do_request( new WP_REST_Request( 'GET', self::FILE_ROUTE . self::STORE_LOGO_FILE_ID ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'logo_bytes', $response->get_data() );
	}

	public function test_file_route_rejects_logged_out_caller_when_saved_store_logo_is_not_public() {
		WC_Payments::get_gateway()->update_option( 'platform_checkout_store_logo', self::STORE_LOGO_FILE_ID );
		wp_set_current_user( 0 );

		$this->mock_api_client
			->method( 'get_file' )
			->willReturn( [ 'purpose' => 'dispute_evidence' ] );
		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_file_contents' );

		$response = rest_do_request( new WP_REST_Request( 'GET', self::FILE_ROUTE . self::STORE_LOGO_FILE_ID ) );

		$this->assertSame( 401, $response->get_status() );
	}

	public function test_file_route_serves_any_file_to_store_manager() {
		WC_Payments::get_gateway()->update_option( 'platform_checkout_store_logo', self::STORE_LOGO_FILE_ID );
		wp_set_current_user( 1 );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_file' )
			->with( 'file_other' )
			->willReturn( [ 'purpose' => 'dispute_evidence' ] );
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_file_contents' )
			->with( 'file_other' )
			->willReturn(
				[
					'file_content' => base64_encode( 'evidence_bytes' ), // @codingStandardsIgnoreLine
					'content_type' => 'application/pdf',
				]
			);

		$response = rest_do_request( new WP_REST_Request( 'GET', self::FILE_ROUTE . self::OTHER_FILE_ID ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'evidence_bytes', $response->get_data() );
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

		delete_transient( WC_Payments_File_Service::CACHE_KEY_PREFIX_PURPOSE . 'file_mock_ID_0' );
	}

	public function test_get_file_caches_purpose_between_requests() {
		$file_response = [
			'file_content' => base64_encode( 'test_file_content' ), // @codingStandardsIgnoreLine
			'content_type' => 'image/png',
		];

		// The purpose lookup must round-trip to the server only on the first request; the second
		// request has to read the purpose the first one wrote to the transient cache. The cache
		// read and write keys must agree for this to hold.
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_file' )
			->with( 'file_mock_ID' )
			->willReturn( [ 'purpose' => 'business_logo' ] );

		$this->mock_api_client
			->expects( $this->exactly( 2 ) )
			->method( 'get_file_contents' )
			->with( 'file_mock_ID' )
			->willReturn( $file_response );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'file_id', 'file_mock_ID' );

		$this->controller->get_file( $request );
		$second_response = $this->controller->get_file( $request );

		$this->assertSame( 'test_file_content', $second_response->get_data() );
		$this->assertSame( 200, $second_response->status );

		delete_transient( WC_Payments_File_Service::CACHE_KEY_PREFIX_PURPOSE . 'file_mock_ID_0' );
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

		delete_transient( WC_Payments_File_Service::CACHE_KEY_PREFIX_PURPOSE . 'file_mock_ID_0' );
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

		delete_transient( WC_Payments_File_Service::CACHE_KEY_PREFIX_PURPOSE . 'file_mock_ID_0' );
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

		delete_transient( WC_Payments_File_Service::CACHE_KEY_PREFIX_PURPOSE . 'file_mock_ID_0' );
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

	/**
	 * Finds the controller that the plugin registered for the single-file route.
	 *
	 * @return WC_REST_Payments_Files_Controller
	 */
	private function get_registered_file_controller(): WC_REST_Payments_Files_Controller {
		foreach ( rest_get_server()->get_routes() as $handlers ) {
			foreach ( $handlers as $handler ) {
				$callback = $handler['callback'] ?? null;
				if ( is_array( $callback ) && $callback[0] instanceof WC_REST_Payments_Files_Controller && 'get_file' === $callback[1] ) {
					return $callback[0];
				}
			}
		}

		$this->fail( 'The single-file route is not registered.' );
	}

	/**
	 * Replaces the API client on the registered controller.
	 *
	 * @param WC_Payments_API_Client $api_client The client to use.
	 *
	 * @return WC_Payments_API_Client The previous client.
	 */
	private function swap_api_client( $api_client ) {
		$property = new ReflectionProperty( WC_Payments_REST_Controller::class, 'api_client' );
		$property->setAccessible( true );
		$previous = $property->getValue( $this->registered_controller );
		$property->setValue( $this->registered_controller, $api_client );

		return $previous;
	}
}
