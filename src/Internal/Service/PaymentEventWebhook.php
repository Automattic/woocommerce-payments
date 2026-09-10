<?php
/**
 * Historical report invalidation from delivered payment events.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

/** Uses webhook identity only; money is recovered from qualified provider evidence. */
class PaymentEventWebhook {
	/**
	 * Historical charge locator.
	 *
	 * @var PaymentReceiptIndex
	 */
	private $index;
	/**
	 * Report invalidation storage.
	 *
	 * @var PaymentReportRepository
	 */
	private $reports;
	/**
	 * Evidence recovery queue.
	 *
	 * @var PaymentEventRecoveryScheduler
	 */
	private $queue;

	/**
	 * Construct without registering hooks or retrieving payments.
	 *
	 * @param PaymentReceiptIndex           $index Historical charge locator.
	 * @param PaymentReportRepository       $reports Report publication storage.
	 * @param PaymentEventRecoveryScheduler $queue Evidence recovery queue.
	 */
	public function __construct( PaymentReceiptIndex $index, PaymentReportRepository $reports, PaymentEventRecoveryScheduler $queue ) {
		$this->index   = $index;
		$this->reports = $reports;
		$this->queue   = $queue;
	}

	/**
	 * Invalidate before enqueueing; storage errors must reach the delivery retry boundary.
	 *
	 * @param array  $event Delivered webhook body.
	 * @param int    $site Connected site ID from the local authenticated connection.
	 * @param string $currency Requested recovery currency.
	 * @throws \RuntimeException When qualification, invalidation or queueing fails.
	 */
	public function process( array $event, int $site, string $currency ): void {
		$type = $event['type'] ?? null;
		if ( ! in_array( $type, [ 'charge.refunded', 'charge.refund.updated', 'charge.dispute.created', 'charge.dispute.closed', 'charge.dispute.updated', 'charge.dispute.funds_withdrawn', 'charge.dispute.funds_reinstated' ], true ) ) {
			return;
		}
		$object = $event['data']['object'] ?? null;
		$charge = is_array( $object ) ? ( $object[ 'charge.refunded' === $type ? 'id' : 'charge' ] ?? null ) : null;
		if ( $site <= 0 || ! is_string( $event['account'] ?? null ) || ! preg_match( '/^acct_[a-zA-Z0-9]+$/D', $event['account'] ) || ! is_bool( $event['livemode'] ?? null ) || ! is_string( $event['id'] ?? null ) || ! preg_match( '/^evt_[a-zA-Z0-9]+$/D', $event['id'] ) || ! is_string( $charge ) || ! preg_match( '/^(ch|py)_[a-zA-Z0-9]+$/D', $charge ) || ! preg_match( '/^[a-zA-Z]{3}$/D', $currency ) ) {
			throw new \RuntimeException( 'Payment webhook reporting identity is incomplete.' );
		}
		$receipt = $this->index->resolve( $event['account'], $site, ! $event['livemode'], $charge );
		if ( 'missing' === $receipt['state'] ) {
			return;
		}
		if ( 'found' !== $receipt['state'] ) {
			throw new \RuntimeException( 'Historical payment lookup failed.' );
		}
		$result = $this->reports->invalidate_event( $receipt['scope'], $event['id'] );
		if ( 'published' !== $result['state'] ) {
			throw new \RuntimeException( 'Payment report invalidation failed.' );
		}
		$this->queue->enqueue( $receipt['scope'], strtoupper( $currency ) );
	}
}
