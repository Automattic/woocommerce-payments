<?php
/**
 * Historical charge lookup against persisted receipt evidence.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Service;

use WCPay\Internal\Service\PaymentEventRepository;
use WCPay\Internal\Service\PaymentReceiptIndex;

/** The index must not depend on mutable order metadata. */
class PaymentReceiptIndexTest extends \WCPAY_UnitTestCase {
	/** A charge remains associated with its original receipt after the order changes. */
	public function test_historical_charge_binding() {
		global $wpdb;
		$db          = clone $wpdb;
		$db->prefix .= 'task_receipt_index_test_';
		$db->options = $db->prefix . 'options';
		$events      = new PaymentEventRepository( $db );
		$this->assertTrue( $events->create_schema() );
		$this->assertNotFalse( $db->query( "CREATE TABLE {$db->options} (option_name varchar(191) NOT NULL PRIMARY KEY, option_value longtext NOT NULL, autoload varchar(20) NOT NULL)" ) );
		try {
			$index   = new PaymentReceiptIndex( $db, $events );
			$scope   = [
				'account_id' => 'acct_original',
				'site_id'    => 123,
				'order_id'   => 456,
				'test_mode'  => false,
				'event_id'   => 'pi_original',
			];
			$receipt = $events->publish(
				$scope,
				'',
				[
					'kind'      => 'intent_context',
					'charge_id' => 'ch_original',
				]
			);
			$this->assertSame( 'bound', $index->bind( $scope, $receipt['revision'] )['state'] );
			$this->assertSame( 'bound', $index->bind( array_reverse( $scope, true ), $receipt['revision'] )['state'] );
			$found = $index->resolve( 'acct_original', 123, false, 'ch_original' );
			$this->assertSame( 'found', $found['state'] );
			$this->assertEquals( $scope, $found['scope'] );
			$this->assertSame( $receipt['revision'], $found['receipt_revision'] );
			$this->assertSame( 'missing', $index->resolve( 'acct_other', 123, false, 'ch_original' )['state'] );
			$this->assertSame( 'missing', $index->resolve( 'acct_original', 123, true, 'ch_original' )['state'] );
			$other         = array_merge(
				$scope,
				[
					'order_id' => 457,
					'event_id' => 'pi_later',
				]
			);
			$other_receipt = $events->publish(
				$other,
				'',
				[
					'kind'      => 'intent_context',
					'charge_id' => 'ch_original',
				]
			);
			$this->assertSame( 'conflict', $index->bind( $other, $other_receipt['revision'] )['state'] );
			$this->assertEquals( $scope, $index->resolve( 'acct_original', 123, false, 'ch_original' )['scope'] );
			$next = $events->publish(
				$scope,
				$receipt['revision'],
				[
					'kind'      => 'intent_context',
					'charge_id' => 'ch_original',
				]
			);
			$this->assertSame( 'bound', $index->bind( $scope, $next['revision'] )['state'] );
			$this->assertSame( $receipt['revision'], $index->resolve( 'acct_original', 123, false, 'ch_original' )['receipt_revision'] );
			$large_scope = array_merge( $scope, [ 'event_id' => 'pi_' . str_repeat( 'x', 5000 ) ] );
			$large       = $events->publish(
				$large_scope,
				'',
				[
					'kind'      => 'intent_context',
					'charge_id' => 'ch_large',
				]
			);
			$this->assertSame( 'invalid', $index->bind( $large_scope, $large['revision'] )['state'] );
			$this->assertSame( '2', $db->get_var( "SELECT COUNT(*) FROM {$db->options}" ) );
			$known = $index->find_for_order( 456, 1 );
			$this->assertSame( 'known', $known['state'] );
			$this->assertFalse( $known['has_more'] );
			$this->assertCount( 1, $known['receipts'] );
			$this->assertEquals( $scope, $known['receipts'][0]['scope'] );
			$this->assertSame( 'unknown', $index->find_for_order( 457 )['state'] );
			$this->assertSame( 'unknown', $index->find_for_order( 4560 )['state'] );
			// A pre-locator receipt becomes discoverable when its verified binding is retried.
			$db->query( "DELETE FROM {$db->options} WHERE option_name LIKE 'wcpay_order_receipt_%'" );
			$this->assertSame( 'unknown', $index->find_for_order( 456 )['state'] );
			$this->assertSame( 'bound', $index->bind( $scope, $next['revision'] )['state'] );
			$this->assertSame( $receipt['revision'], $index->find_for_order( 456 )['receipts'][0]['receipt_revision'] );
			$later_scope = array_merge(
				$scope,
				[
					'account_id' => 'acct_later',
					'event_id'   => 'pi_second',
					'test_mode'  => true,
				]
			);
			$later       = $events->publish(
				$later_scope,
				'',
				[
					'kind'      => 'intent_context',
					'charge_id' => 'ch_second',
				]
			);
			$this->assertSame( 'bound', $index->bind( $later_scope, $later['revision'] )['state'] );
			$first = $index->find_for_order( 456, 1 );
			$this->assertTrue( $first['has_more'] );
			$second = $index->find_for_order( 456, 1, $first['next_cursor'] );
			$this->assertFalse( $second['has_more'] );
			$this->assertNotEquals( $first['receipts'][0]['scope'], $second['receipts'][0]['scope'] );
			$this->assertSame( 'invalid', $index->find_for_order( 0 )['state'] );
			$this->assertSame( 'invalid', $index->find_for_order( 456, 101 )['state'] );
			$this->assertSame( 'invalid', $index->find_for_order( 456, 1, 'bad' )['state'] );
			// Corrupt locator data must not become an empty or partially successful result.
			$db->query( "UPDATE {$db->options} SET option_value='{}' WHERE option_name LIKE 'wcpay_order_receipt_%'" );
			$this->assertSame( 'invalid', $index->find_for_order( 456 )['state'] );
		} finally {
			$db->query( "DROP TABLE {$db->options}" );
			$db->query( "DROP TABLE {$db->prefix}wcpay_event_heads" );
			$db->query( "DROP TABLE {$db->prefix}wcpay_event_revisions" );
		}
	}
}
