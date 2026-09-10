<?php
/**
 * Persistence failure tests for captured payment evidence.
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Internal\Service\CapturedPaymentSnapshot;
use WCPay\Internal\Service\CapturedPaymentSnapshotStore;

/** Exercises real order CRUD with injected database write failures. */
class CapturedPaymentSnapshotStoreTest extends WCPAY_UnitTestCase {
	/**
	 * A failed save cannot hide a contradiction or report newly saved evidence.
	 *
	 * @dataProvider failed_write_provider
	 * @param bool   $hpos Whether to use HPOS.
	 * @param string $target Metadata write to fail.
	 * @param bool   $conflict Whether qualified evidence already exists.
	 */
	public function test_failed_write_recovery( bool $hpos, string $target, bool $conflict ): void {
		global $wpdb;
		$previous = get_option( 'woocommerce_custom_orders_table_enabled' );
		update_option( 'woocommerce_custom_orders_table_enabled', $hpos ? 'yes' : 'no' );
		$this->assertSame( $hpos, \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() );
		$order = new WC_Order();
		$order->set_payment_method( 'woocommerce_payments' );
		$order->set_currency( 'GBP' );
		$order->set_total( 36 );
		$order->update_meta_data( '_charge_id', 'ch_fixture' );
		$order->update_meta_data( '_wcpay_mode', 'prod' );
		$order->save();
		$expected_context = [
			'version'    => 1,
			'account_id' => 'acct_original',
			'site_id'    => 123,
			'test_mode'  => false,
		];
		$store            = new CapturedPaymentSnapshotStore( new CapturedPaymentSnapshot() );
		$charge           = [
			'id'                      => 'ch_fixture',
			'livemode'                => true,
			'wcpay_reporting_context' => $expected_context,
			'paid'                    => true,
			'captured'                => true,
			'status'                  => 'succeeded',
			'amount'                  => 3600,
			'amount_captured'         => 3600,
			'currency'                => 'gbp',
			'amount_refunded'         => 0,
			'disputed'                => false,
			'balance_transaction'     => [
				'id'       => 'txn_fixture',
				'amount'   => 4190,
				'currency' => 'eur',
				'created'  => 1700000000,
			],
		];
		$fail             = static function ( $sql ) use ( $target ) {
			if ( preg_match( '/^(INSERT|UPDATE)/i', $sql ) && false !== strpos( $sql, "'" . $target . "'" ) ) {
				return 'SELECT * FROM task_43781_deliberately_missing_table';
			}
			return $sql;
		};
		$suppressed       = $wpdb->suppress_errors( true );
		try {
			if ( $conflict ) {
				$this->assertSame( 'ready', $store->record( $order, $charge, 'EUR', $expected_context )['state'] );
				$unrelated                    = $charge;
				$unrelated['id']              = 'ch_other';
				$unrelated['amount_refunded'] = 500;
				$this->assertSame( 'charge_mismatch', $store->record( $order, $unrelated, 'EUR', $expected_context )['reason'] );
				$this->assertSame( 'ready', $store->read( $order, 'EUR', $expected_context )['state'] );
				$charge['balance_transaction']['amount'] = 4290;
			}
			add_filter( 'query', $fail );
			$this->assertSame(
				[
					'state'  => 'incomplete',
					'reason' => 'snapshot_write_failed',
				],
				$store->record( $order, $charge, 'EUR', $expected_context )
			);
			$this->assertSame( 'incomplete', $store->read( $order, 'EUR', $expected_context )['state'] );
			$charge['balance_transaction']['amount'] = 4190;
			// Repeated failed repairs must retry the same conflict, not nest new copies.
			$stable = null;
			for ( $attempt = 0; $attempt < 3; ++$attempt ) {
				$this->assertSame(
					[
						'state'  => 'incomplete',
						'reason' => 'snapshot_write_failed',
					],
					$store->record( $order, $charge, 'EUR', $expected_context )
				);
				$fresh = wc_get_order( $order->get_id() );
				$fresh->read_meta_data( true );
				$records = wp_json_encode(
					[
						$fresh->get_meta( CapturedPaymentSnapshotStore::CURRENT_META ),
						array_map(
							static function ( $meta ) {
								return $meta->value;
							},
							$fresh->get_meta( CapturedPaymentSnapshotStore::REVISION_META, false )
						),
					]
				);
				if ( null !== $stable ) {
					$this->assertSame( $stable, $records );
				}
				$stable = $records;
			}
			remove_filter( 'query', $fail );
			$expected = $conflict ? 'inconsistent' : 'ready';
			$this->assertSame( $expected, $store->record( $order, $charge, 'EUR', $expected_context )['state'] );
			$this->assertSame( $expected, $store->read( $order, 'EUR', $expected_context )['state'] );
			$this->assertEquals( 36, wc_get_order( $order->get_id() )->get_total() );
		} finally {
			remove_filter( 'query', $fail );
			$wpdb->suppress_errors( $suppressed );
			$order->delete( true );
			update_option( 'woocommerce_custom_orders_table_enabled', $previous );
		}
	}

	/** @return array Storage, failed write and existing-evidence combinations. */
	public function failed_write_provider(): array {
		$cases = [];
		foreach ( [ true, false ] as $hpos ) {
			foreach ( [ CapturedPaymentSnapshotStore::CURRENT_META, CapturedPaymentSnapshotStore::REVISION_META ] as $target ) {
				foreach ( [ true, false ] as $conflict ) {
					$cases[] = [ $hpos, $target, $conflict ];
				}
			}
		}
		return $cases;
	}
}
