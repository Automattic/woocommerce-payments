<?php
/**
 * Tests for WCPay\Internal\Abilities\Domain\GetAuthorizationsSummary.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities\Domain;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\AbilitiesRegistrar;
use WCPay\Internal\Abilities\Domain\GetAuthorizationsSummary;

/**
 * @coversDefaultClass \WCPay\Internal\Abilities\Domain\GetAuthorizationsSummary
 */
class GetAuthorizationsSummaryTest extends WCPAY_UnitTestCase {


	public function test_name(): void {
		$this->assertSame( 'woocommerce-payments/get-authorizations-summary', GetAuthorizationsSummary::get_name() );
	}

	public function test_registration_args_shape(): void {
		$args = GetAuthorizationsSummary::get_registration_args();

		$this->assertSame( AbilitiesRegistrar::CATEGORY_SLUG, $args['category'] );
		$this->assertSame( [ GetAuthorizationsSummary::class, 'execute' ], $args['execute_callback'] );
		$this->assertSame( [ AbilitiesRegistrar::class, 'current_user_can_manage_woocommerce' ], $args['permission_callback'] );
		$this->assertTrue( $args['meta']['show_in_rest'] );
		$this->assertTrue( $args['meta']['annotations']['readonly'] );
		$this->assertSame( 'object', $args['input_schema']['type'] );
		$this->assertFalse( $args['input_schema']['additionalProperties'] );
	}

	public function test_execute_delegates_to_authorizations_summary_endpoint(): void {
		$canned = [
			'count' => 7,
			'total' => 500,
		];
		$filter = function ( $result, $server, $request ) use ( $canned ) {
			if ( $request->get_route() === '/wc/v3/payments/authorizations/summary' ) {
				return new \WP_REST_Response( $canned, 200 );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );

		try {
			$result = GetAuthorizationsSummary::execute( null );
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertSame(
			$canned,
			$result,
			'execute() must delegate to /wc/v3/payments/authorizations/summary and unwrap the response.'
		);
	}
}
