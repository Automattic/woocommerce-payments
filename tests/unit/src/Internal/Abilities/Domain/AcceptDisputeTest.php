<?php
/**
 * Tests for WCPay\Internal\Abilities\Domain\AcceptDispute.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities\Domain;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\Domain\AcceptDispute;

/**
 * @coversDefaultClass \WCPay\Internal\Abilities\Domain\AcceptDispute
 */
class AcceptDisputeTest extends WCPAY_UnitTestCase {

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

	public function test_execute_delegates_to_dispute_service(): void {
		$mock_service = $this->createMock( \WCPay\Internal\Service\DisputeService::class );
		$mock_service->expects( $this->once() )->method( 'accept' )
			->with( 'du_1' )
			->willReturn( [ 'id' => 'du_1' ] );
		wcpay_get_test_container()->replace( \WCPay\Internal\Service\DisputeService::class, $mock_service );
		try {
			$result = AcceptDispute::execute( [ 'dispute_id' => 'du_1' ] );
		} finally {
			wcpay_get_test_container()->reset_all_replacements();
		}
		$this->assertSame( [ 'id' => 'du_1' ], $result );
	}
}
