<?php
/**
 * Historical refund event qualification tests.
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Internal\Service\RefundBalanceSnapshot;

/**
 * Monetary evidence tests independent of order storage.
 */
class RefundBalanceSnapshotTest extends WCPAY_UnitTestCase {
	/**
	 * Build synthetic expanded provider evidence.
	 *
	 * @return array Refund fixture.
	 */
	private function fixture(): array {
		return [
			'object'              => 'refund',
			'id'                  => 're_fixture',
			'charge'              => 'ch_fixture',
			'currency'            => 'eur',
			'amount'              => 500,
			'status'              => 'succeeded',
			'balance_transaction' => [
				'object'   => 'balance_transaction',
				'id'       => 'txn_debit',
				'source'   => 're_fixture',
				'currency' => 'usd',
				'amount'   => -600,
				'fee'      => 0,
				'net'      => -600,
				'created'  => 1611232911,
				'status'   => 'available',
			],
		];
	}

	/**
	 * Preserve separately recorded debit and reversal, including fees and FX.
	 */
	public function test_recorded_refund_and_failure_amounts_are_preserved() {
		require_once dirname( __DIR__, 5 ) . '/src/Internal/Service/RefundBalanceSnapshot.php';
		$validator = new RefundBalanceSnapshot();
		$refund    = $this->fixture();
		$result    = $validator->build( $refund, 're_fixture', 'ch_fixture', 'EUR', 'USD' );
		$this->assertSame( 'events_qualified', $result['state'] );
		$this->assertSame( -600, $result['events'][0]['amount'] );
		$this->assertSame( 500, $result['original_amount'] );
		$refund['status']                      = 'failed';
		$refund['failure_balance_transaction'] = array_merge(
			$refund['balance_transaction'],
			[
				'id'     => 'txn_reversal',
				'amount' => 590,
				'fee'    => 10,
				'net'    => 580,
			]
		);
		$result                                = $validator->build( $refund, 're_fixture', 'ch_fixture', 'EUR', 'USD' );
		$this->assertSame( 'events_qualified', $result['state'] );
		$this->assertSame( 'failed', $result['refund_status'] );
		$this->assertSame( [ -600, 590 ], array_column( $result['events'], 'amount' ) );
		$this->assertSame( [ -600, 580 ], array_column( $result['events'], 'net' ) );
		$this->assertSame( 'refund_failure_reversal', $result['events'][1]['kind'] );
		foreach ( [ 'pending', 'requires_action', 'succeeded' ] as $status ) {
			$refund['status'] = $status;
			$this->assertSame(
				[
					'state'  => 'incomplete',
					'reason' => 'refund_status_inconsistent',
				],
				$validator->build( $refund, 're_fixture', 'ch_fixture', 'EUR', 'USD' )
			);
		}
	}

	/**
	 * Missing and contradictory evidence must not qualify.
	 *
	 * @dataProvider invalid_evidence_provider
	 */
	public function test_invalid_refund_evidence( $field, $value, $reason ) {
		require_once dirname( __DIR__, 5 ) . '/src/Internal/Service/RefundBalanceSnapshot.php';
		$refund = $this->fixture();
		if ( 'status' === $field ) {
			$refund['status'] = $value;
		} elseif ( 'balance_transaction' === $field ) {
			$refund[ $field ] = $value;
		} else {
			$refund['balance_transaction'][ $field ] = $value;
		}
		$result = ( new RefundBalanceSnapshot() )->build( $refund, 're_fixture', 'ch_fixture', 'EUR', 'USD' );
		$this->assertSame(
			[
				'state'  => 'incomplete',
				'reason' => $reason,
			],
			$result
		);
	}

	/**
	 * Invalid evidence cases.
	 *
	 * @return array Cases.
	 */
	public function invalid_evidence_provider() {
		return [
			[ 'balance_transaction', 'txn_unexpanded', 'refund_balance_evidence_missing' ],
			[ 'source', 're_other', 'refund_balance_evidence_missing' ],
			[ 'currency', 'eur', 'refund_reporting_currency_mismatch' ],
			[ 'amount', '-600', 'refund_balance_components_invalid' ],
			[ 'net', -599, 'refund_balance_components_invalid' ],
			[ 'created', 0, 'refund_balance_components_invalid' ],
			[ 'status', 'failed', 'refund_balance_evidence_missing' ],
			[ 'status', 'unknown', 'refund_status_unknown' ],
		];
	}
}
