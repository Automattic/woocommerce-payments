<?php
/**
 * Class WC_REST_Payments_Disputes_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_REST_Payments_Disputes_Controller unit tests.
 */
class WC_REST_Payments_Disputes_Controller_Test extends WCPAY_UnitTestCase {

	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Payments_Disputes_Controller
	 */
	private $controller;

	/**
	 * Mock API client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	public function set_up() {
		parent::set_up();

		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		$this->controller      = new WC_REST_Payments_Disputes_Controller( $this->mock_api_client );
	}

	public function tear_down() {
		delete_option( WC_Payments_Features::DISPUTE_DEFENDER_AI );
		parent::tear_down();
	}

	public function test_generate_defense_draft_returns_403_when_flag_off() {
		delete_option( WC_Payments_Features::DISPUTE_DEFENDER_AI );

		// API client must not be touched when the flag is off.
		$this->mock_api_client->expects( $this->never() )->method( 'get_dispute' );

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'dispute_id', 'dp_test_123' );

		$response = $this->controller->generate_defense_draft( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'wcpay_dispute_defender_disabled', $response->get_error_code() );
		$this->assertSame( 403, $response->get_error_data()['status'] );
	}

	public function test_generate_defense_draft_rejects_non_fraudulent() {
		update_option( WC_Payments_Features::DISPUTE_DEFENDER_AI, '1' );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_dispute' )
			->with( 'dp_test_456' )
			->willReturn(
				[
					'id'     => 'dp_test_456',
					'reason' => 'duplicate',
				]
			);

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'dispute_id', 'dp_test_456' );

		$response = $this->controller->generate_defense_draft( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'wcpay_dispute_defender_unsupported_reason', $response->get_error_code() );
		$this->assertSame( 400, $response->get_error_data()['status'] );
	}
}
