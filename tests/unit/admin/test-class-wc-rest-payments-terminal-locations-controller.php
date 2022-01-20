<?php
/**
 * Class WC_REST_Payments_Terminal_Locations_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WC_REST_Payments_Terminal_Locations_Controller as Controller;

/**
 * WC_REST_Payments_Tos_Controller unit tests.
 */
class WC_REST_Payments_Terminal_Locations_Controller_Test extends WP_UnitTestCase {

	/**
	 * The system under test.
	 *
	 * @var WC_REST_Payments_Terminal_Locations_Controller
	 */
	private $controller;

	/**
	 * An API client mock.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		// Set the store location for running test cases as intended.
		update_option( 'woocommerce_store_city', 'San Francisco' );
		update_option( 'woocommerce_default_country', 'US:CA' );
		update_option( 'woocommerce_store_address', '60 29th Street Suite 343' );
		update_option( 'woocommerce_store_postcode', '94110' );

		$this->mock_api_client = $this->getMockBuilder( WC_Payments_API_Client::class )
			->disableOriginalConstructor()
			->getMock();

		$this->controller = new WC_REST_Payments_Terminal_Locations_Controller( $this->mock_api_client );
		$this->location   = [
			'id'           => 'tml_XXXXXX',
			'livemode'     => true,
			'display_name' => get_bloginfo(),
			'address'      => [
				'city'        => WC()->countries->get_base_city(),
				'country'     => WC()->countries->get_base_country(),
				'line1'       => WC()->countries->get_base_address(),
				'postal_code' => WC()->countries->get_base_postcode(),
				'state'       => WC()->countries->get_base_state(),
			],
		];
	}

	/**
	 * Post test cleanup
	 */
	public function tearDown() {
		parent::tearDown();
		delete_transient( Controller::STORE_LOCATIONS_TRANSIENT_KEY );
	}

	public function test_emits_error_when_address_not_populated() {
		delete_transient( Controller::STORE_LOCATIONS_TRANSIENT_KEY );

		// Set the store location settings for running the test case as intended.
		delete_option( 'woocommerce_store_city' );
		delete_option( 'woocommerce_store_address' );
		delete_option( 'woocommerce_store_postcode' );

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_terminal_locations' );
		$this->mock_api_client
			->expects( $this->never() )
			->method( 'create_terminal_location' );

		// Setup the request.
		$request = new WP_REST_Request(
			'GET',
			'/wc/v3/payments/terminal/locations'
		);
		$request->set_header( 'Content-Type', 'application/json' );

		$result = $this->controller->get_store_location( $request );
		$this->assertSame( 'store_address_is_incomplete', $result->get_error_code() );
		$this->assertStringEndsWith( '/admin.php?page=wc-settings&tab=general', $result->get_error_message() );
	}

	public function test_creates_location_from_scratch() {
		// First call is to populate empty cache, force fetching the location individually and repopulate cache.
		$this->mock_api_client
			->expects( $this->exactly( 2 ) )
			->method( 'get_terminal_locations' )
			->willReturnOnConsecutiveCalls( [], [ $this->location ] );
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_terminal_location' )
			->with( $this->location['display_name'], $this->location['address'] )
			->willReturn( $this->location );

		// Setup the request.
		$request = new WP_REST_Request(
			'GET',
			'/wc/v3/payments/terminal/locations'
		);
		$request->set_header( 'Content-Type', 'application/json' );

		$result = $this->controller->get_store_location( $request );
		$this->assertSame( [ $this->location ], get_transient( Controller::STORE_LOCATIONS_TRANSIENT_KEY ) );
		$this->assertEquals( $this->location, $result->get_data() );
	}

	public function test_creates_location_upon_mismatch() {
		// This location will have a slight change compared to the current settings.
		$mismatched_location = array_merge( $this->location, [ 'display_name' => 'Example' ] );
		set_transient( Controller::STORE_LOCATIONS_TRANSIENT_KEY, [ $mismatched_location ] );

		// Ensures the transient will be re-populated on mismatch.
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_terminal_locations' )
			->willReturn( [ $mismatched_location, $this->location ] );
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_terminal_location' )
			->with( $this->location['display_name'], $this->location['address'] )
			->willReturn( $this->location );

		// Setup the request.
		$request = new WP_REST_Request(
			'GET',
			'/wc/v3/payments/terminal/locations'
		);
		$request->set_header( 'Content-Type', 'application/json' );

		$result = $this->controller->get_store_location( $request );
		$this->assertEquals( $this->location, $result->get_data() );
		$this->assertSame( [ $mismatched_location, $this->location ], get_transient( Controller::STORE_LOCATIONS_TRANSIENT_KEY ) );
	}

	public function test_uses_existing_location_without_cache() {
		delete_transient( Controller::STORE_LOCATIONS_TRANSIENT_KEY );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_terminal_locations' )
			->willReturn( [ $this->location ] );
		$this->mock_api_client
			->expects( $this->never() )
			->method( 'create_terminal_location' );

		// Setup the request.
		$request = new WP_REST_Request(
			'GET',
			'/wc/v3/payments/terminal/locations'
		);
		$request->set_header( 'Content-Type', 'application/json' );

		$result = $this->controller->get_store_location( $request );
		$this->assertEquals( $this->location, $result->get_data() );
		$this->assertEquals( [ $this->location ], get_transient( Controller::STORE_LOCATIONS_TRANSIENT_KEY ) );
	}

	public function test_uses_existing_location_with_cache() {
		set_transient( Controller::STORE_LOCATIONS_TRANSIENT_KEY, [ $this->location ] );

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_terminal_locations' );
		$this->mock_api_client
			->expects( $this->never() )
			->method( 'create_terminal_location' );

		// Setup the request.
		$request = new WP_REST_Request(
			'GET',
			'/wc/v3/payments/terminal/locations'
		);
		$request->set_header( 'Content-Type', 'application/json' );

		$result = $this->controller->get_store_location( $request );
		$this->assertEquals( $this->location, $result->get_data() );
	}

	public function test_repopulates_cache_on_successful_delete_request() {
		set_transient( Controller::STORE_LOCATIONS_TRANSIENT_KEY, [] );

		// Repopulate cache after deletion.
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_terminal_locations' )
			->willReturn( [ $this->location ] );
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'delete_terminal_location' )
			->willReturn(
				[
					'id'      => $this->location['id'],
					'object'  => 'terminal.location',
					'deleted' => true,
				]
			);

		// Setup the request.
		$request = new WP_REST_Request(
			'DELETE',
			'/wc/v3/payments/terminal/locations/'
		);
		$request->set_param( 'location_id', $this->location['id'] );
		$request->set_header( 'Content-Type', 'application/json' );

		$this->controller->delete_location( $request );
		$this->assertEquals( [ $this->location ], get_transient( Controller::STORE_LOCATIONS_TRANSIENT_KEY ) );
	}

	public function test_repopulates_cache_on_successful_update_request() {
		set_transient( Controller::STORE_LOCATIONS_TRANSIENT_KEY, [] );

		// Repopulate cache after update.
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_terminal_locations' )
			->willReturn( [ $this->location ] );
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'update_terminal_location' )
			->willReturn( array_merge( $this->location, [ 'display_name' => 'New display name!' ] ) );

		// Setup the request.
		$request = new WP_REST_Request(
			'POST',
			'/wc/v3/payments/terminal/locations/',
			[ 'display_name' => 'New display name!' ]
		);
		$request->set_param( 'location_id', $this->location['id'] );
		$request->set_header( 'Content-Type', 'application/json' );

		$this->controller->update_location( $request );
		$this->assertEquals( [ $this->location ], get_transient( Controller::STORE_LOCATIONS_TRANSIENT_KEY ) );
	}

	public function test_retreive_non_cached_location_repopulates_cache() {
		set_transient( Controller::STORE_LOCATIONS_TRANSIENT_KEY, [] );

		// First call is to populate empty cache, force fetching the location individually and repopulate cache.
		$this->mock_api_client
			->expects( $this->exactly( 2 ) )
			->method( 'get_terminal_locations' )
			->willReturnOnConsecutiveCalls( [], [ $this->location ] );
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_terminal_location' )
			->willReturn( $this->location );

		// Setup the request.
		$request = new WP_REST_Request(
			'GET',
			'/wc/v3/payments/terminal/locations'
		);
		$request->set_param( 'location_id', $this->location['id'] );
		$request->set_header( 'Content-Type', 'application/json' );

		$this->controller->get_location( $request );
		$this->assertSame( [ $this->location ], get_transient( Controller::STORE_LOCATIONS_TRANSIENT_KEY ) );
	}

	public function test_retreive_uses_cache_for_existing_location() {
		set_transient( Controller::STORE_LOCATIONS_TRANSIENT_KEY, [ $this->location ] );

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_terminal_location' );

		// Setup the request.
		$request = new WP_REST_Request(
			'GET',
			'/wc/v3/payments/terminal/locations'
		);
		$request->set_param( 'location_id', $this->location['id'] );
		$request->set_header( 'Content-Type', 'application/json' );

		$result = $this->controller->get_location( $request );
		$this->assertEquals( $this->location, $result->get_data() );
	}

	public function test_creating_new_location_repopulates_cache() {
		set_transient( Controller::STORE_LOCATIONS_TRANSIENT_KEY, [] );

		// Ensures the transient will be re-populated upon creation.
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_terminal_locations' )
			->willReturn( [ $this->location ] );
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_terminal_location' )
			->willReturn( $this->location );

		// Setup the request.
		$request = new WP_REST_Request(
			'POST',
			'/wc/v3/payments/terminal/locations/',
			[
				'display_name' => $this->location['display_name'],
				'address'      => $this->location['address'],
			]
		);
		$request->set_header( 'Content-Type', 'application/json' );

		$this->controller->create_location( $request );
		$this->assertSame( [ $this->location ], get_transient( Controller::STORE_LOCATIONS_TRANSIENT_KEY ) );
	}

	public function test_fetching_all_uses_cache_for_existing_locations() {
		set_transient( Controller::STORE_LOCATIONS_TRANSIENT_KEY, [ $this->location ] );

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_terminal_locations' );

		// Setup the request.
		$request = new WP_REST_Request(
			'GET',
			'/wc/v3/payments/terminal/locations'
		);
		$request->set_header( 'Content-Type', 'application/json' );

		$result = $this->controller->get_all_locations( $request );
		$this->assertEquals( [ $this->location ], $result->get_data() );
	}
}
