<?php
/**
 * Tests for WCPay\Internal\Abilities\Domain\AcceptDispute.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities\Domain;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\AbilitiesRegistrar;
use WCPay\Internal\Abilities\Domain\AcceptDispute;

/**
 * @coversDefaultClass \WCPay\Internal\Abilities\Domain\AcceptDispute
 */
class AcceptDisputeTest extends WCPAY_UnitTestCase {

	public function test_name(): void {
		$this->assertSame( 'woocommerce-payments/accept-dispute', AcceptDispute::get_name() );
	}

	public function test_registration_args_has_destructive_irreversible_annotations(): void {
		$args = AcceptDispute::get_registration_args();

		$this->assertFalse( $args['meta']['annotations']['readonly'] );
		$this->assertTrue( $args['meta']['annotations']['destructive'] );
		$this->assertFalse( $args['meta']['annotations']['idempotent'] );
		$this->assertSame( 0.0, $args['meta']['annotations']['reversibility'] );
		$this->assertFalse( $args['meta']['mcp']['public'] );
		$this->assertContains( 'dispute_id', $args['input_schema']['required'] );
	}

	public function test_execute_returns_error_when_dispute_id_missing(): void {
		$result = AcceptDispute::execute( [] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_missing_dispute_id', $result->get_error_code() );
	}

	public function test_execute_returns_error_when_dispute_id_not_a_string(): void {
		$result = AcceptDispute::execute( [ 'dispute_id' => 123 ] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_missing_dispute_id', $result->get_error_code() );
	}
}
