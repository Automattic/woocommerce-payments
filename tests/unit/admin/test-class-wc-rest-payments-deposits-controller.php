<?php
/**
 * Class WC_REST_Payments_Deposits_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_REST_Payments_Deposits_Controller unit tests.
 */
class WC_REST_Payments_Deposits_Controller_Test extends WCPAY_UnitTestCase {

	private const DOWNLOAD_ROUTE = '/wc/v3/payments/deposits/download/';

	private const VALID_EXPORT_ID = 'Ab3dE5gH7jK9mN1';

	/**
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * The controller instance behind the registered download route.
	 *
	 * @var WC_REST_Payments_Deposits_Controller
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

		wp_set_current_user( 1 );

		$this->mock_api_client       = $this->createMock( WC_Payments_API_Client::class );
		$this->registered_controller = $this->get_registered_export_url_controller();
		$this->original_api_client   = $this->swap_api_client( $this->mock_api_client );
	}

	public function tear_down() {
		$this->swap_api_client( $this->original_api_client );

		parent::tear_down();
	}

	public function test_download_route_forwards_valid_export_id() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_payouts_export_url' )
			->with( 'Ab3dE5gH7jK9mN1' )
			->willReturn( [ 'download_url' => 'https://example.com/export.csv' ] );

		$response = rest_do_request( new WP_REST_Request( 'GET', self::DOWNLOAD_ROUTE . self::VALID_EXPORT_ID ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 'download_url' => 'https://example.com/export.csv' ], $response->get_data() );
	}

	/**
	 * @dataProvider path_like_export_id_provider
	 *
	 * @param string $export_id Export ID containing path characters.
	 */
	public function test_download_route_rejects_path_like_export_id( string $export_id ) {
		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_payouts_export_url' );

		$response = rest_do_request( new WP_REST_Request( 'GET', self::DOWNLOAD_ROUTE . $export_id ) );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'rest_no_route', $response->get_data()['code'] );
	}

	public function path_like_export_id_provider(): array {
		return [
			'embedded slash'        => [ 'id/traversal' ],
			'dot-dot-slash'         => [ '../../accounts' ],
			'backslash'             => [ 'id\\traversal' ],
			'encoded slash'         => [ 'id%2ftraversal' ],
			'encoded dot-dot-slash' => [ '%2e%2e%2faccounts' ],
			'percent sign'          => [ 'id%' ],
			'double-encoded slash'  => [ 'id%252ftraversal' ],
		];
	}

	/**
	 * Finds the controller that the plugin registered for the payout export download route.
	 *
	 * @return WC_REST_Payments_Deposits_Controller
	 */
	private function get_registered_export_url_controller(): WC_REST_Payments_Deposits_Controller {
		foreach ( rest_get_server()->get_routes() as $handlers ) {
			foreach ( $handlers as $handler ) {
				$callback = $handler['callback'] ?? null;
				if ( is_array( $callback ) && $callback[0] instanceof WC_REST_Payments_Deposits_Controller && 'get_export_url' === $callback[1] ) {
					return $callback[0];
				}
			}
		}

		$this->fail( 'The deposits export download route is not registered.' );
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
