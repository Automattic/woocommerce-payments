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

	public function test_registration_args_has_destructive_write_annotations(): void {
		$args = SubmitDisputeEvidence::get_registration_args();

		$this->assertFalse( $args['meta']['annotations']['readonly'] );
		$this->assertTrue( $args['meta']['annotations']['destructive'] );
		$this->assertFalse( $args['meta']['annotations']['idempotent'] );
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

	public function test_execute_returns_error_when_metadata_key_too_long(): void {
		$result = SubmitDisputeEvidence::execute(
			[
				'dispute_id' => 'du_1',
				'metadata'   => [ str_repeat( 'k', 41 ) => 'value' ],
			]
		);
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_invalid_metadata_key', $result->get_error_code() );
	}

	public function test_execute_returns_error_when_metadata_value_too_long(): void {
		$result = SubmitDisputeEvidence::execute(
			[
				'dispute_id' => 'du_1',
				'metadata'   => [ 'key' => str_repeat( 'v', 501 ) ],
			]
		);
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_invalid_metadata_value', $result->get_error_code() );
	}

	public function test_execute_returns_error_when_too_many_metadata_keys(): void {
		$metadata = [];
		for ( $i = 0; $i < 11; $i++ ) {
			$metadata[ 'key' . $i ] = 'value';
		}
		$result = SubmitDisputeEvidence::execute(
			[
				'dispute_id' => 'du_1',
				'metadata'   => $metadata,
			]
		);
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_too_many_metadata_keys', $result->get_error_code() );
	}

	public function test_execute_delegates_to_dispute_service(): void {
		$evidence     = [ 'customer_communication' => 'file_1' ];
		$metadata     = [ 'k' => 'v' ];
		$mock_service = $this->createMock( \WCPay\Internal\Service\DisputeService::class );
		$mock_service->expects( $this->once() )->method( 'submit_evidence' )
			->with( 'du_1', $evidence, true, $metadata )
			->willReturn( [ 'id' => 'du_1' ] );
		wcpay_get_test_container()->replace( \WCPay\Internal\Service\DisputeService::class, $mock_service );
		try {
			$result = SubmitDisputeEvidence::execute(
				[
					'dispute_id' => 'du_1',
					'evidence'   => $evidence,
					'submit'     => true,
					'metadata'   => $metadata,
				]
			);
		} finally {
			wcpay_get_test_container()->reset_all_replacements();
		}
		$this->assertSame( [ 'id' => 'du_1' ], $result );
	}
}
