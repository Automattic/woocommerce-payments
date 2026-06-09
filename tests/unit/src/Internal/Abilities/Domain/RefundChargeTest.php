<?php
/**
 * Tests for WCPay\Internal\Abilities\Domain\RefundCharge.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities\Domain;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\AbilitiesRegistrar;
use WCPay\Internal\Abilities\Domain\RefundCharge;

/**
 * @coversDefaultClass \WCPay\Internal\Abilities\Domain\RefundCharge
 */
class RefundChargeTest extends WCPAY_UnitTestCase {

	public function test_name(): void {
		$this->assertSame( 'woocommerce-payments/refund-charge', RefundCharge::get_name() );
	}

	public function test_registration_args_has_write_annotations(): void {
		$args = RefundCharge::get_registration_args();

		$this->assertSame( AbilitiesRegistrar::CATEGORY_SLUG, $args['category'] );
		$this->assertSame( [ AbilitiesRegistrar::class, 'current_user_can_manage_woocommerce' ], $args['permission_callback'] );
		$this->assertFalse( $args['meta']['annotations']['readonly'] );
		$this->assertTrue( $args['meta']['annotations']['destructive'] );
		$this->assertTrue( $args['meta']['annotations']['idempotent'] );
		$this->assertFalse( $args['meta']['mcp']['public'] );
		$this->assertContains( 'charge_id', $args['input_schema']['required'] );
		$this->assertContains( 'idempotency_key', $args['input_schema']['required'] );
		$this->assertFalse( $args['input_schema']['additionalProperties'] );
	}

	public function test_execute_returns_error_when_charge_id_missing(): void {
		$result = RefundCharge::execute( [] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_missing_charge_id', $result->get_error_code() );
	}

	public function test_execute_returns_error_when_charge_id_not_a_string(): void {
		$result = RefundCharge::execute( [ 'charge_id' => 123 ] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_missing_charge_id', $result->get_error_code() );
	}

	public function test_execute_returns_error_when_idempotency_key_missing(): void {
		$result = RefundCharge::execute( [ 'charge_id' => 'ch_1' ] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_missing_idempotency_key', $result->get_error_code() );
	}

	public function test_execute_delegates_to_refund_service(): void {
		$mock_service = $this->createMock( \WCPay\Internal\Service\RefundService::class );
		$mock_service->expects( $this->once() )->method( 'refund_charge' )
			->with( 'ch_1', 500, 'requested_by_customer', 'ik_1' )
			->willReturn( [ 'id' => 're_1' ] );
		wcpay_get_test_container()->replace( \WCPay\Internal\Service\RefundService::class, $mock_service );
		try {
			$result = RefundCharge::execute(
				[
					'charge_id'       => 'ch_1',
					'amount'          => 500,
					'reason'          => 'requested_by_customer',
					'idempotency_key' => 'ik_1',
				]
			);
		} finally {
			wcpay_get_test_container()->reset_all_replacements();
		}
		$this->assertSame( [ 'id' => 're_1' ], $result );
	}
}
