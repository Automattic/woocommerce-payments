<?php
/**
 * Class RefundService
 *
 * @package WooCommerce\Payments
 */

declare( strict_types=1 );

namespace WCPay\Internal\Service;

use WCPay\Core\Server\Request\Refund_Charge;

/**
 * Charge-bound refund service shared by the refund ability (and available to
 * other charge-bound refund callers). Wraps the typed `Refund_Charge` request
 * so callers never re-implement refund plumbing and agent-driven refunds avoid
 * the order-bound note/notification fan-out in the gateway's process_refund().
 */
class RefundService {

	/**
	 * Refund a charge by its Stripe charge/payout ID.
	 *
	 * @see \WCPay\Internal\Abilities\Domain\RefundCharge
	 *
	 * @param string      $charge_id       Stripe charge (`ch_…`/`py_…`) ID.
	 * @param int|null    $amount          Amount in minor units; null for a full refund.
	 * @param string|null $reason          Refund reason (duplicate|fraudulent|requested_by_customer).
	 * @param string|null $idempotency_key Optional caller key so retries dedupe to the original refund.
	 * @return array|\WP_Error Refund object on success, WP_Error on failure.
	 */
	public function refund_charge( string $charge_id, ?int $amount = null, ?string $reason = null, ?string $idempotency_key = null ) {
		try {
			$request = Refund_Charge::create();
			$request->set_charge( $charge_id );
			if ( null !== $amount ) {
				$request->set_amount( $amount );
			}
			if ( null !== $reason ) {
				$request->set_reason( $reason );
			}
			if ( null !== $idempotency_key && '' !== $idempotency_key ) {
				$request->set_idempotency_key( $idempotency_key );
			}
			$request->set_source( 'woopayments_ability' );

			return $request->send();
		} catch ( \Throwable $e ) {
			wc_get_logger()->error(
				sprintf( 'Refund failed for charge %s: %s', $charge_id, $e->getMessage() ),
				[ 'source' => 'woopayments-abilities' ]
			);
			return new \WP_Error( 'wcpay_refund_failed', $e->getMessage(), [ 'exception' => $e ] );
		}
	}
}
