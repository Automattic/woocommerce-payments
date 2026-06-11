<?php
/**
 * Class RefundServiceTest
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Service;

use WCPay\Core\Server\Request\Refund_Charge;
use WCPay\Internal\Service\RefundService;
use WCPAY_UnitTestCase;

/**
 * @covers \WCPay\Internal\Service\RefundService
 */
class RefundServiceTest extends WCPAY_UnitTestCase {

	/**
	 * @var RefundService
	 */
	private $sut;

	protected function setUp(): void {
		parent::setUp();
		$this->sut = new RefundService();
	}

	public function test_refund_charge_builds_request_with_all_params(): void {
		$request = $this->mock_wcpay_request( Refund_Charge::class );
		$request->expects( $this->once() )->method( 'set_charge' )->with( 'ch_1' );
		$request->expects( $this->once() )->method( 'set_amount' )->with( 500 );
		$request->expects( $this->once() )->method( 'set_reason' )->with( 'requested_by_customer' );
		$request->expects( $this->once() )->method( 'set_idempotency_key' )->with( 'ik_1' );
		$request->expects( $this->once() )->method( 'set_source' )->with( 'woopayments_ability' );
		$request->expects( $this->once() )->method( 'format_response' )->willReturn( [ 'id' => 're_1' ] );

		$result = $this->sut->refund_charge( 'ch_1', 500, 'requested_by_customer', 'ik_1' );

		$this->assertSame( [ 'id' => 're_1' ], $result );
	}

	public function test_refund_charge_omits_optional_params_when_null(): void {
		$request = $this->mock_wcpay_request( Refund_Charge::class );
		$request->expects( $this->once() )->method( 'set_charge' )->with( 'ch_2' );
		$request->expects( $this->never() )->method( 'set_amount' );
		$request->expects( $this->never() )->method( 'set_reason' );
		$request->expects( $this->never() )->method( 'set_idempotency_key' );
		$request->expects( $this->once() )->method( 'set_source' )->with( 'woopayments_ability' );
		$request->expects( $this->once() )->method( 'format_response' )->willReturn( [ 'id' => 're_2' ] );

		$result = $this->sut->refund_charge( 'ch_2' );

		$this->assertSame( [ 'id' => 're_2' ], $result );
	}

	public function test_refund_charge_skips_idempotency_key_when_empty_string(): void {
		$request = $this->mock_wcpay_request( Refund_Charge::class );
		$request->expects( $this->once() )->method( 'set_charge' )->with( 'ch_3' );
		$request->expects( $this->never() )->method( 'set_idempotency_key' );
		$request->expects( $this->once() )->method( 'set_source' )->with( 'woopayments_ability' );
		$request->expects( $this->once() )->method( 'format_response' )->willReturn( [ 'id' => 're_3' ] );

		$result = $this->sut->refund_charge( 'ch_3', null, null, '' );

		$this->assertSame( [ 'id' => 're_3' ], $result );
	}

	public function test_refund_charge_returns_wp_error_when_request_throws(): void {
		$request = $this->mock_wcpay_request( Refund_Charge::class );
		$request->expects( $this->once() )->method( 'set_charge' )->with( 'ch_4' );
		$request->expects( $this->once() )->method( 'set_source' )->with( 'woopayments_ability' );
		$request->expects( $this->once() )->method( 'format_response' )
			->willThrowException( new \WCPay\Exceptions\API_Exception( 'boom', 'test_err', 500 ) );

		$result = $this->sut->refund_charge( 'ch_4', null, null, 'ik_4' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_refund_failed', $result->get_error_code() );
	}
}
