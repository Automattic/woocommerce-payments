<?php
/**
 * Retrieves historical charge evidence for the local snapshot store.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WC_Order;
use WCPay\Core\Server\Request\Get_Charge;

/**
 * One recovery attempt. Scheduling and complete payment history are separate.
 */
class CapturedPaymentSnapshotRecovery {

	/**
	 * Local evidence store.
	 *
	 * @var CapturedPaymentSnapshotStore
	 */
	private $store;

	/**
	 * Construct the recovery service.
	 *
	 * @param CapturedPaymentSnapshotStore $store Local evidence store.
	 */
	public function __construct( CapturedPaymentSnapshotStore $store ) {
		$this->store = $store;
	}

	/**
	 * Fetch the identified historical charge without changing sales valuations.
	 *
	 * Do not call while rendering reports. A failed fetch leaves stored evidence
	 * untouched; the caller owns retry policy and must inspect the returned state.
	 *
	 * @param WC_Order $order Order owning the historical payment.
	 * @param string   $currency Requested reporting currency.
	 * @param array    $expected_context Trusted queued account/site/mode and protocol version.
	 * @return array Recovery result, not proof of complete order payment history.
	 */
	public function recover( WC_Order $order, string $currency, array $expected_context ): array {
		$fresh = wc_get_order( $order->get_id() );
		if ( ! $fresh instanceof WC_Order || 'woocommerce_payments' !== $fresh->get_payment_method() ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'unsupported_order',
			];
		}
		if ( ! preg_match( '/^[a-zA-Z]{3}$/', $currency ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'reporting_currency_invalid',
			];
		}
		$mode = $fresh->get_meta( '_wcpay_mode' );
		if ( ! in_array( $mode, [ 'test', 'prod' ], true ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'payment_mode_missing',
			];
		}
		if ( 4 !== count( $expected_context ) || 1 !== ( $expected_context['version'] ?? null ) || ! is_string( $expected_context['account_id'] ?? null ) || ! preg_match( '/^acct_[a-zA-Z0-9]+$/D', $expected_context['account_id'] ) || ! is_int( $expected_context['site_id'] ?? null ) || $expected_context['site_id'] <= 0 || ( $expected_context['test_mode'] ?? null ) !== ( 'test' === $mode ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'charge_expected_context_invalid',
			];
		}
		$charge_id = (string) $fresh->get_meta( '_charge_id' );
		if ( ! preg_match( '/^(ch|py)_[a-zA-Z0-9]+$/', $charge_id ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'charge_missing',
			];
		}
		try {
			$request = Get_Charge::create( $charge_id );
			$request->set_hook_args( $charge_id );
			$request->set_test_mode( 'test' === $mode );
			$request->set_include_reporting_context();
			$charge = $request->send();
		} catch ( \Throwable $exception ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'charge_retrieval_failed',
			];

		}

		if ( ! is_array( $charge ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'charge_retrieval_failed',
			];
		}
		$context = $charge['wcpay_reporting_context'] ?? null;
		foreach ( $expected_context as $key => $expected_value ) {
			if ( ! is_array( $context ) || ( $context[ $key ] ?? null ) !== $expected_value ) {
				return [
					'state'  => 'incomplete',
					'reason' => 'charge_context_mismatch',
				];
			}
		}
		if ( ( $charge['livemode'] ?? null ) !== ( 'prod' === $mode ) ) {
			return [
				'state'  => 'incomplete',
				'reason' => 'payment_mode_mismatch',
			];
		}
		// The store reloads order inputs after the network call and validates identity.
		return $this->store->record( $fresh, $charge, strtoupper( $currency ), $expected_context );
	}
}
