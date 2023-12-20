<?php
/**
 * Class WC_REST_Payments_Refunds_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Server\Request\Refund_Charge;

/**
 * WC_REST_Payments_Refunds_Controller_Test unit tests.
 */
class WC_REST_Payments_Refunds_Controller_Test extends WCPAY_UnitTestCase {

	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Payments_Refunds_Controller
	 */
	private $controller;

	/**
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	public function set_up() {
		parent::set_up();

		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );

		$this->controller = new WC_REST_Payments_Refunds_Controller(
			$this->mock_api_client
		);
	}

	public function test_process_refund_without_order_id(): void {

		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'  => null,
				'charge_id' => 'ch_test',
				'amount'    => 5000,
				'reason'    => 'duplicate',
			]
		);

		$refund_request = $this->mock_wcpay_request( Refund_Charge::class );

		$refund_request->expects( $this->once() )
			->method( 'set_charge' )
			->with( 'ch_test' );
		$refund_request->expects( $this->once() )
			->method( 'set_amount' )
			->with( 5000 );
		$refund_request->expects( $this->once() )
			->method( 'set_reason' )
			->with( 'duplicate' );
		$refund_response = [
			'id' => 're_test',
		];
		$refund_request->expects( $this->once() )
			->method( 'format_response' )
			->with()
			->willReturn( $refund_response );

		$response = $this->controller->process_refund( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $refund_response, $response->get_data() );
	}
}
