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

		$this->mock_api_client = $this->getMockBuilder( WC_Payments_API_Client::class )
			->disableOriginalConstructor()
			->getMock();

		$this->controller = new WC_REST_Payments_Terminal_Locations_Controller( $this->mock_api_client );

		// Setup a test request.
		$this->request = new WP_REST_Request(
			'GET',
			'/wc/v3/payments/terminal/locations'
		);

		$this->request->set_header( 'Content-Type', 'application/json' );

		$this->location = [
			'id'           => 'tml_XXXXXX',
			'livemode'     => true,
			'display_name' => get_bloginfo(),
			'address'      => [
				'city'        => WC()->countries->get_base_city(),
				'country'     => WC()->countries->get_base_country(),
				'line1'       => WC()->countries->get_base_address(),
				'line2'       => WC()->countries->get_base_address_2(),
				'postal_code' => WC()->countries->get_base_postcode(),
				'state'       => WC()->countries->get_base_state(),
			],
		];
	}

	public function test_creates_location_from_scatch() {
		delete_transient( Controller::STORE_LOCATIONS_TRANSIENT_KEY );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_terminal_locations' )
			->willReturn( [] );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_terminal_location' )
			->with( $this->location['display_name'], $this->location['address'] )
			->willReturn(
				$this->location,
			);

		$result = $this->controller->get_store_location( $this->request );

		$this->assertSame( [ $this->location ], get_transient( Controller::STORE_LOCATIONS_TRANSIENT_KEY ) );
		$this->assertEquals( $this->location, $result->get_data() );
	}

	public function test_creates_location_upon_mismatch() {
		// This location will have a slight change compared to the current settings.
		$mismatched_location = array_merge(
			$this->location,
			[
				'display_name' => 'Example',
			]
		);

		set_transient(
			Controller::STORE_LOCATIONS_TRANSIENT_KEY,
			[ $mismatched_location ],
			DAY_IN_SECONDS
		);

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_terminal_locations' );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'create_terminal_location' )
			->with( $this->location['display_name'], $this->location['address'] )
			->willReturn(
				$this->location,
			);

		$result = $this->controller->get_store_location( $this->request );

		$this->assertSame(
			[
				$mismatched_location,
				$this->location,
			],
			get_transient( Controller::STORE_LOCATIONS_TRANSIENT_KEY )
		);
		$this->assertEquals( $this->location, $result->get_data() );
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

		$result = $this->controller->get_store_location( $this->request );
		$this->assertEquals( $this->location, $result->get_data() );
		$this->assertEquals(
			[ $this->location ],
			get_transient( Controller::STORE_LOCATIONS_TRANSIENT_KEY )
		);
	}

	public function test_uses_existing_location_with_cache() {
		set_transient(
			Controller::STORE_LOCATIONS_TRANSIENT_KEY,
			[ $this->location ]
		);

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_terminal_locations' );

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'create_terminal_location' );

		$result = $this->controller->get_store_location( $this->request );
		$this->assertEquals( $this->location, $result->get_data() );
	}
}
