<?php
/**
 * Tests for WCPay\Internal\Abilities\Domain\GetDispute.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities\Domain;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\AbilitiesRegistrar;
use WCPay\Internal\Abilities\Domain\GetDispute;

/**
 * @coversDefaultClass \WCPay\Internal\Abilities\Domain\GetDispute
 */
class GetDisputeTest extends WCPAY_UnitTestCase {

	public function test_name(): void {
		$this->assertSame( 'woocommerce-payments/get-dispute', GetDispute::get_name() );
	}

	public function test_registration_args_shape(): void {
		$args = GetDispute::get_registration_args();

		$this->assertSame( AbilitiesRegistrar::CATEGORY_SLUG, $args['category'] );
		$this->assertSame( [ GetDispute::class, 'execute' ], $args['execute_callback'] );
		$this->assertSame( [ AbilitiesRegistrar::class, 'current_user_can_manage_woocommerce' ], $args['permission_callback'] );
		$this->assertTrue( $args['meta']['show_in_rest'] );
		$this->assertSame( 'object', $args['input_schema']['type'] );
		$this->assertFalse( $args['input_schema']['additionalProperties'] );
		$this->assertSame( [ 'dispute_id' ], $args['input_schema']['required'] );
		$this->assertSame( 'string', $args['input_schema']['properties']['dispute_id']['type'] );
	}

	public function test_execute_rejects_missing_id(): void {
		$result = GetDispute::execute( [] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_missing_dispute_id', $result->get_error_code() );
	}

	public function test_execute_rejects_non_string_id(): void {
		$result = GetDispute::execute( [ 'dispute_id' => 123 ] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_missing_dispute_id', $result->get_error_code() );
	}

	public function test_execute_rejects_empty_string_id(): void {
		$result = GetDispute::execute( [ 'dispute_id' => '' ] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_missing_dispute_id', $result->get_error_code() );
	}

	public function test_execute_delegates_to_disputes_route(): void {
		$captured = null;
		$filter   = function ( $result, $server, $request ) use ( &$captured ) {
			if ( strpos( $request->get_route(), '/wc/v3/payments/disputes/' ) === 0 ) {
				$captured = $request->get_route();
				return new \WP_REST_Response( [ 'id' => 'du_abc' ], 200 );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );

		try {
			$result = GetDispute::execute( [ 'dispute_id' => 'du_abc' ] );
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertSame( '/wc/v3/payments/disputes/du_abc', $captured );
		$this->assertSame( [ 'id' => 'du_abc' ], $result );
	}
}
