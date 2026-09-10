<?php
/**
 * Tests for historical captured payment evidence.
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Internal\Service\CapturedPaymentSnapshot;

require_once dirname( __DIR__, 5 ) . '/src/Internal/Service/CapturedPaymentSnapshot.php';

/** Tests the source boundary without WordPress or gateway requests. */
class CapturedPaymentSnapshotTest extends \PHPUnit\Framework\TestCase {

	/** Gross receipts and net proceeds must stay separate and source-qualified. */
	public function test_preserves_gross_fee_and_net_separately(): void {
		$service = new CapturedPaymentSnapshot();
		$charge  = $this->charge();
		$result  = $service->build( $charge, 'ch_fixture', 'GBP', 3600, 'EUR' );
		$this->assertSame( 'unavailable', $result['net_state'] );
		$this->assertNull( $result['net_amount'] );
		$charge['balance_transaction']['fee'] = 193;
		$charge['balance_transaction']['net'] = 3997;
		$result                               = $service->build( $charge, 'ch_fixture', 'GBP', 3600, 'EUR' );
		$this->assertSame( 4190, $result['amount'] );
		$this->assertSame( 193, $result['fee_amount'] );
		$this->assertSame( 3997, $result['net_amount'] );
		$this->assertSame( 'ready', $result['net_state'] );
		$charge['balance_transaction']['net'] = 4000;
		$this->assertSame( 'balance_components_inconsistent', $service->build( $charge, 'ch_fixture', 'GBP', 3600, 'EUR' )['reason'] );
	}

	/** Contradictory source evidence cannot be declared ready. */
	public function test_rejects_contradictory_source_evidence(): void {
		$service            = new CapturedPaymentSnapshot();
		$charge             = $this->charge();
		$charge['currency'] = '';
		$this->assertSame( 'incomplete', $service->build( $charge, 'ch_fixture', '', 3600, 'EUR' )['state'] );
		$charge             = $this->charge();
		$charge['currency'] = 'eur';
		$this->assertSame( 'incomplete', $service->build( $charge, 'ch_fixture', 'EUR', 3600, 'EUR' )['state'] );
		$charge                                  = $this->charge();
		$charge['balance_transaction']['source'] = 'ch_other';
		$this->assertSame( 'incomplete', $service->build( $charge, 'ch_fixture', 'GBP', 3600, 'EUR' )['state'] );
	}

	/** Later adjustments cannot erase the original capture event. */
	public function test_records_original_capture_independently_of_adjustments(): void {
		$service  = new CapturedPaymentSnapshot();
		$original = $service->build( $this->charge(), 'ch_fixture', 'GBP', 3600, 'EUR' );
		foreach ( [ [ 500, false ], [ 3600, false ], [ 0, true ] ] as [ $refunded, $disputed ] ) {
			$charge                    = $this->charge();
			$charge['amount_refunded'] = $refunded;
			$charge['disputed']        = $disputed;
			$event                     = $service->build_event( $charge, 'ch_fixture', 'GBP', 3600, 'EUR' );
			// The original monetary facts survive any later adjustment; only the
			// observed dispute state, which no refund row records, tracks the charge.
			$this->assertSame( $disputed ? 'disputed' : 'none', $event['dispute_state'] );
			$this->assertSame( array_diff_key( $original, [ 'dispute_state' => true ] ), array_diff_key( $event, [ 'dispute_state' => true ] ) );
			$this->assertSame( 'adjustments_unresolved', $service->build( $charge, 'ch_fixture', 'GBP', 3600, 'EUR' )['reason'] );
			$unrelated       = $charge;
			$unrelated['id'] = 'ch_other';
			$this->assertSame( 'charge_mismatch', $service->build( $unrelated, 'ch_fixture', 'GBP', 3600, 'EUR' )['reason'] );
			$charge['balance_transaction']['source'] = 'ch_other';
			$this->assertSame( 'balance_source_mismatch', $service->build_event( $charge, 'ch_fixture', 'GBP', 3600, 'EUR' )['reason'] );
		}
	}

	/** @return array Complete synthetic historical charge. */
	private function charge(): array {
		return [
			'id'                  => 'ch_fixture',
			'paid'                => true,
			'captured'            => true,
			'status'              => 'succeeded',
			'amount'              => 3600,
			'amount_captured'     => 3600,
			'currency'            => 'gbp',
			'amount_refunded'     => 0,
			'disputed'            => false,
			'balance_transaction' => [
				'id'            => 'txn_fixture',
				'amount'        => 4190,
				'currency'      => 'eur',
				'created'       => 1700000000,
				'exchange_rate' => 1.16,
			],
		];
	}

	/** Use the recorded minor-unit amount, never a rounded exchange rate. */
	public function test_uses_original_converted_amount(): void {
		$service = new CapturedPaymentSnapshot();
		$result  = $service->build( $this->charge(), 'ch_fixture', 'GBP', 3600, 'EUR' );
		$this->assertSame( 'ready', $result['state'] );
		$this->assertSame( 4190, $result['amount'] );
		$this->assertSame( 'EUR', $result['currency'] );
		$this->assertSame( 'txn_fixture', $result['balance_transaction_id'] );
		$this->assertSame( $result, $service->build( $this->charge(), 'ch_fixture', 'GBP', 3600, 'EUR' ) );
	}

	/** Reject evidence that cannot establish the requested metric. */
	public function test_rejects_incomplete_or_conflicting_evidence(): void {
		$service = new CapturedPaymentSnapshot();
		$cases   = [
			[ 'id', 'ch_other', 'charge_mismatch' ],
			[ 'captured', false, 'capture_unconfirmed' ],
			[ 'paid', null, 'capture_unconfirmed' ],
			[ 'amount_captured', 1000, 'capture_amount_mismatch' ],
			[ 'amount', '3600', 'capture_amount_mismatch' ],
			[ 'currency', 'usd', 'original_currency_mismatch' ],
			[ 'amount_refunded', 100, 'adjustments_unresolved' ],
			[ 'disputed', true, 'adjustments_unresolved' ],
			[ 'balance_transaction', 'txn_not_expanded', 'balance_transaction_missing' ],
		];
		foreach ( $cases as [ $key, $value, $reason ] ) {
			$charge         = $this->charge();
			$charge[ $key ] = $value;
			$this->assertSame(
				[
					'state'  => 'incomplete',
					'reason' => $reason,
				],
				$service->build( $charge, 'ch_fixture', 'GBP', 3600, 'EUR' )
			);
		}
		foreach ( [
			'amount'   => 4190.0,
			'created'  => null,
			'currency' => 'usd',
		] as $key => $value ) {
			$charge                                = $this->charge();
			$charge['balance_transaction'][ $key ] = $value;
			$this->assertSame( 'incomplete', $service->build( $charge, 'ch_fixture', 'GBP', 3600, 'EUR' )['state'] );
		}
	}
}
