<?php
/**
 * Webhook identity and invalidation ordering.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Service;

use WCPay\Internal\Service\PaymentEventWebhook;
use WCPay\Internal\Service\PaymentReceiptIndex;
use WCPay\Internal\Service\PaymentReportRepository;
use WCPay\Internal\Service\PaymentEventRecoveryScheduler;

/** Verifies identity routing and persistence before scheduling. */
class PaymentEventWebhookTest extends \WCPAY_UnitTestCase {
	/**
	 * Supported events resolve their charge identity without scheduling an unknown receipt.
	 *
	 * @dataProvider event_provider
	 * @param string $type Event type.
	 * @param array $event_object Provider object shape.
	 */
	public function test_supported_event_charge_identity( string $type, array $event_object ) {
		$index = $this->createMock( PaymentReceiptIndex::class );
		$index->expects( $this->once() )->method( 'resolve' )->with( 'acct_original', 123, false, 'ch_original' )->willReturn( [ 'state' => 'missing' ] );
		$reports = $this->createMock( PaymentReportRepository::class );
		$reports->expects( $this->never() )->method( 'invalidate_event' );
		$queue = $this->createMock( PaymentEventRecoveryScheduler::class );
		$queue->expects( $this->never() )->method( 'enqueue' );
		( new PaymentEventWebhook( $index, $reports, $queue ) )->process(
			[
				'id'       => 'evt_original',
				'type'     => $type,
				'account'  => 'acct_original',
				'livemode' => true,
				'data'     => [ 'object' => $event_object ],
			],
			123,
			'EUR'
		);
	}

	/** @return array Supported provider event object shapes. */
	public function event_provider(): array {
		return [
			[ 'charge.refunded', [ 'id' => 'ch_original' ] ],
			[ 'charge.refund.updated', [ 'charge' => 'ch_original' ] ],
			[ 'charge.dispute.created', [ 'charge' => 'ch_original' ] ],
			[ 'charge.dispute.closed', [ 'charge' => 'ch_original' ] ],
			[ 'charge.dispute.updated', [ 'charge' => 'ch_original' ] ],
			[ 'charge.dispute.funds_withdrawn', [ 'charge' => 'ch_original' ] ],
			[ 'charge.dispute.funds_reinstated', [ 'charge' => 'ch_original' ] ],
		];
	}

	/** Historical identity must come from the event and durable index. */
	public function test_invalidation_precedes_queue_and_failure_propagates() {
		$index   = $this->createMock( PaymentReceiptIndex::class );
		$reports = $this->createMock( PaymentReportRepository::class );
		$queue   = $this->createMock( PaymentEventRecoveryScheduler::class );
		$scope   = [
			'account_id' => 'acct_old',
			'site_id'    => 123,
			'test_mode'  => true,
			'order_id'   => 456,
			'event_id'   => 'pi_old',
		];
		$index->expects( $this->exactly( 2 ) )->method( 'resolve' )->with( 'acct_old', 123, true, 'ch_old' )->willReturn(
			[
				'state' => 'found',
				'scope' => $scope,
			]
		);
		$invalidated = false;
		$reports->expects( $this->exactly( 2 ) )->method( 'invalidate_event' )->with( $scope, 'evt_refund' )->willReturnCallback(
			static function () use ( &$invalidated ) {
				if ( $invalidated ) {
					return [ 'state' => 'unavailable' ];
				}
				$invalidated = true;
				return [ 'state' => 'published' ];
			}
		);
		$queue->expects( $this->once() )->method( 'enqueue' )->with( $scope, 'EUR' )->willReturnCallback(
			function () use ( &$invalidated ) {
				$this->assertTrue( $invalidated );
				return 123;
			}
		);
		$service = new PaymentEventWebhook( $index, $reports, $queue );
		$event   = [
			'id'       => 'evt_refund',
			'type'     => 'charge.refund.updated',
			'account'  => 'acct_old',
			'livemode' => false,
			'data'     => [ 'object' => [ 'charge' => 'ch_old' ] ],
		];
		$service->process( $event, 123, 'EUR' );
		$this->expectException( \RuntimeException::class );
		$service->process( $event, 123, 'EUR' );
	}
}
