<?php
/**
 * Tests for WCPay\Internal\Abilities\Domain\SubmitDisputeEvidence.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities\Domain;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\AbilitiesRegistrar;
use WCPay\Internal\Abilities\Domain\SubmitDisputeEvidence;

/**
 * @coversDefaultClass \WCPay\Internal\Abilities\Domain\SubmitDisputeEvidence
 */
class SubmitDisputeEvidenceTest extends WCPAY_UnitTestCase {

	public function test_name(): void {
		$this->assertSame( 'woocommerce-payments/submit-dispute-evidence', SubmitDisputeEvidence::get_name() );
	}

	public function test_registration_args_has_additive_write_annotations(): void {
		$args = SubmitDisputeEvidence::get_registration_args();

		$this->assertFalse( $args['meta']['annotations']['readonly'] );
		$this->assertFalse( $args['meta']['annotations']['destructive'] );
		$this->assertFalse( $args['meta']['annotations']['idempotent'] );
		$this->assertSame( 0.2, $args['meta']['annotations']['reversibility'] );
		$this->assertFalse( $args['meta']['mcp']['public'] );
		$this->assertContains( 'dispute_id', $args['input_schema']['required'] );
		$this->assertFalse( $args['input_schema']['properties']['submit']['default'] );
	}

	public function test_execute_returns_error_when_dispute_id_missing(): void {
		$result = SubmitDisputeEvidence::execute( [] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_missing_dispute_id', $result->get_error_code() );
	}

	public function test_execute_returns_error_when_dispute_id_not_a_string(): void {
		$result = SubmitDisputeEvidence::execute( [ 'dispute_id' => 123 ] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_missing_dispute_id', $result->get_error_code() );
	}
}
