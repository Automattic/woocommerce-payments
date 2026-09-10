<?php
/**
 * Installation retry tests using real disposable database tables.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Service;

use WCPay\Internal\Service\PaymentEventRepository;
use WCPay\Internal\Service\PaymentEventSchema;

/** Verifies failure does not advance the independently retried schema marker. */
class PaymentEventSchemaTest extends \WCPAY_UnitTestCase {
	/** Verify partial DDL failure, retry, idempotence and future-version preservation. */
	public function test_installation_retry() {
		global $wpdb;
		$database          = clone $wpdb;
		$database->prefix .= 'task_event_schema_test_';
		foreach ( [ 'wcpay_event_heads', 'wcpay_event_revisions' ] as $suffix ) {
			$this->assertNull( $database->get_var( $database->prepare( 'SHOW TABLES LIKE %s', $database->esc_like( $database->prefix . $suffix ) ) ) );
		}
		$previous = get_option( PaymentEventSchema::VERSION_OPTION, null );
		delete_option( PaymentEventSchema::VERSION_OPTION );
		$this->assertSame( '0', get_option( PaymentEventSchema::VERSION_OPTION, '0' ) );
		$repository   = new PaymentEventRepository( $database );
		$installer    = new PaymentEventSchema( $repository, $wpdb );
		$fail         = static function ( $sql ) use ( $database ) {
			return 0 === strpos( $sql, 'CREATE ' ) && false !== strpos( $sql, $database->prefix . 'wcpay_event_heads' ) ? 'SELECT FROM' : $sql;
		};
		$prior_errors = $database->suppress_errors( true );
		try {
			add_filter( 'query', $fail );
			$this->assertFalse( $installer->maybe_install() );
			$this->assertSame( '0', get_option( 'wcpay_payment_event_schema_version', '0' ) );
			remove_filter( 'query', $fail );
			$this->assertTrue( ( new PaymentEventSchema( $repository, $wpdb ) )->maybe_install() );
			$this->assertSame( '1', get_option( 'wcpay_payment_event_schema_version' ) );
			$this->assertTrue( $repository->is_schema_compatible() );
			$this->assertTrue( $installer->maybe_install() );
			delete_option( PaymentEventSchema::VERSION_OPTION );
			$newer = static function ( $sql ) {
				if ( 0 === strpos( $sql, 'SHOW INDEX FROM ' ) ) {
					update_option( PaymentEventSchema::VERSION_OPTION, '2', false );
				}
				return $sql;
			};
			add_filter( 'query', $newer );
			try {
				$this->assertFalse( $installer->maybe_install() );
				$this->assertSame( '2', get_option( 'wcpay_payment_event_schema_version' ) );
			} finally {
				remove_filter( 'query', $newer );
			}
			foreach ( [ '2', 'unexpected' ] as $future ) {
				update_option( PaymentEventSchema::VERSION_OPTION, $future, false );
				$this->assertFalse( $installer->maybe_install() );
				$this->assertSame( $future, get_option( 'wcpay_payment_event_schema_version' ) );
			}
		} finally {
			remove_filter( 'query', $fail );
			$database->suppress_errors( $prior_errors );
			foreach ( [ 'wcpay_event_heads', 'wcpay_event_revisions' ] as $suffix ) {
				$this->assertNotFalse( $database->query( "DROP TABLE IF EXISTS {$database->prefix}{$suffix}" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed test-owned suffixes.
			}
			delete_option( PaymentEventSchema::VERSION_OPTION );
			if ( null !== $previous ) {
				update_option( PaymentEventSchema::VERSION_OPTION, $previous, false );
			}
		}
	}

	/** Schema installation must run even when the overall plugin version is current. */
	public function test_startup_with_current_plugin_version() {
		$previous_schema = get_option( PaymentEventSchema::VERSION_OPTION, null );
		$previous_plugin = get_option( 'woocommerce_woocommerce_payments_version', null );
		delete_option( PaymentEventSchema::VERSION_OPTION );
		update_option( 'woocommerce_woocommerce_payments_version', WCPAY_VERSION_NUMBER );
		try {
			\WC_Payments::install_actions();
			$this->assertSame( '1', get_option( 'wcpay_payment_event_schema_version' ) );
			$this->assertTrue( wcpay_get_container()->get( PaymentEventRepository::class )->is_schema_compatible() );
			$this->assertSame( WCPAY_VERSION_NUMBER, get_option( 'woocommerce_woocommerce_payments_version' ) );
		} finally {
			foreach ( [
				PaymentEventSchema::VERSION_OPTION         => $previous_schema,
				'woocommerce_woocommerce_payments_version' => $previous_plugin,
			] as $option => $value ) {
				delete_option( $option );
				if ( null !== $value ) {
					update_option( $option, $value, false );
				}
			}
		}
	}
}
