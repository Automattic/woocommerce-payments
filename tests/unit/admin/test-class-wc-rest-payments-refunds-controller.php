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
			->method( 'set_full_reason' )
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

	public function test_process_refund_without_order_id_or_reason(): void {
		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'  => null,
				'charge_id' => 'ch_test',
				'amount'    => 5000,
				'reason'    => null,
			]
		);

		$refund_request = $this->mock_wcpay_request( Refund_Charge::class );

		$refund_request->expects( $this->once() )
			->method( 'set_charge' )
			->with( 'ch_test' );
		$refund_request->expects( $this->once() )
			->method( 'set_amount' )
			->with( 5000 );

		// The gating lives in Refund_Charge::set_full_reason(); the controller just delegates.
		$refund_request->expects( $this->once() )
			->method( 'set_full_reason' )
			->with( null );

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

	public function test_process_refund_explains_an_order_already_refunded_in_woocommerce(): void {
		$order_total   = 18;
		$charge_amount = 1800;
		$order         = WC_Helper_Order::create_order( 1, $order_total );
		wc_create_refund(
			[
				'order_id'       => $order->get_id(),
				'amount'         => $order_total,
				'refund_payment' => false,
			]
		);

		$this->mock_wcpay_request( Refund_Charge::class, 0 );

		$response = $this->controller->process_refund( $this->order_refund_request( $order, $charge_amount ) );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'wcpay_refund_exceeds_order_remaining', $response->get_error_code() );
		$this->assertSame( [ 'status' => 400 ], $response->get_error_data() );
		$this->assertSame(
			sprintf(
				'Order #%s already has $18.00 refunded in WooCommerce, which leaves $0.00 to refund. If a refund on the order was recorded without refunding the payment, delete it from the order and refund again.',
				$order->get_order_number()
			),
			$response->get_error_message()
		);
	}

	public function test_process_refund_names_what_is_left_after_a_partial_woocommerce_refund(): void {
		$order_total   = 18;
		$local_refund  = 5;
		$charge_amount = 1800;
		$order         = WC_Helper_Order::create_order( 1, $order_total );
		wc_create_refund(
			[
				'order_id'       => $order->get_id(),
				'amount'         => $local_refund,
				'refund_payment' => false,
			]
		);

		$this->mock_wcpay_request( Refund_Charge::class, 0 );

		$response = $this->controller->process_refund( $this->order_refund_request( $order, $charge_amount ) );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame(
			sprintf(
				'Order #%s already has $5.00 refunded in WooCommerce, which leaves $13.00 to refund. If a refund on the order was recorded without refunding the payment, delete it from the order and refund again.',
				$order->get_order_number()
			),
			$response->get_error_message()
		);
	}

	public function test_process_refund_passes_through_why_woocommerce_refused_the_refund(): void {
		// The helper order is paid with BACS, which cannot refund automatically.
		$order = WC_Helper_Order::create_order( 1, 50 );

		$response = $this->controller->process_refund( $this->order_refund_request( $order, 5000 ) );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'wcpay_refund_payment', $response->get_error_code() );
		$this->assertSame(
			'The payment gateway for this order does not support automatic refunds.',
			$response->get_error_message()
		);
	}

	/**
	 * Builds a refund request for a charge that belongs to an order.
	 *
	 * @param WC_Order $order  The order.
	 * @param int      $amount Amount in the smallest currency unit.
	 * @return WP_REST_Request
	 */
	private function order_refund_request( WC_Order $order, int $amount ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST' );
		$request->set_body_params(
			[
				'order_id'  => $order->get_id(),
				'charge_id' => 'ch_test',
				'amount'    => $amount,
				'reason'    => null,
			]
		);
		return $request;
	}
}
