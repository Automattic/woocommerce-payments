<?php
/**
 * Class WC_REST_Payments_Disputes_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Database_Cache;

/**
 * WC_REST_Payments_Disputes_Controller unit tests.
 */
class WC_REST_Payments_Disputes_Controller_Test extends WCPAY_UnitTestCase {

	/**
	 * The system under test.
	 *
	 * @var WC_REST_Payments_Disputes_Controller
	 */
	private $controller;

	/**
	 * @var WP_REST_Request
	 */
	private $request;

	/**
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $api_client;

	/**
	 * @var Database_Cache|MockObject
	 */
	private $db_cache;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->api_client = $this->getMockBuilder( WC_Payments_API_Client::class )
			->disableOriginalConstructor()
			->getMock();

		$this->db_cache = $this->createMock( Database_Cache::class );

		$this->controller = new WC_REST_Payments_Disputes_Controller( $this->api_client, $this->db_cache );

		// Setup a test request.
		$this->request = new WP_REST_Request(
			'POST',
			'/wc/v3/payments/disputes'
		);

		$this->request->set_header( 'Content-Type', 'application/json' );
	}

	public function test_get_dispute_status_counts() {
		$this->db_cache
			->expects( $this->once() )
			->method( 'get_or_add' )
			->with(
				Database_Cache::DISPUTE_STATUS_COUNTS_KEY,
				$this->callback(
					function( $args ) {
						return $args[0] === $this->api_client && 'get_dispute_status_counts' === $args[1];
					}
				),
				$this->callback(
					function( $args ) {
						return 'is_array' === $args;
					}
				)
			)
			->willReturn(
				[
					'warning_needs_response' => 2,
					'needs_response'         => 1,
					'under_review'           => 3,
				]
			);

		$response = $this->controller->get_dispute_status_counts( $this->request );

		$response_data = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			[
				'warning_needs_response' => 2,
				'needs_response'         => 1,
				'under_review'           => 3,
			],
			$response_data
		);
	}

	public function test_get_dispute_status_counts_empty() {
		$this->db_cache
			->expects( $this->once() )
			->method( 'get_or_add' )
			->with(
				Database_Cache::DISPUTE_STATUS_COUNTS_KEY,
				$this->callback(
					function( $args ) {
						return $args[0] === $this->api_client && 'get_dispute_status_counts' === $args[1];
					}
				),
				$this->callback(
					function( $args ) {
						return 'is_array' === $args;
					}
				)
			)
			->willReturn( '' );

		$response = $this->controller->get_dispute_status_counts( $this->request );

		$response_data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( wp_json_encode( new stdClass() ), wp_json_encode( $response_data ) );
	}
}
