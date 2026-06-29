<?php
/**
 * Tests for WCPay\Internal\Abilities\Domain\GetActiveLoanSummary.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities\Domain;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\AbilitiesRegistrar;
use WCPay\Internal\Abilities\Domain\GetActiveLoanSummary;

/**
 * @coversDefaultClass \WCPay\Internal\Abilities\Domain\GetActiveLoanSummary
 */
class GetActiveLoanSummaryTest extends WCPAY_UnitTestCase {


	public function test_name(): void {
		$this->assertSame( 'woocommerce-payments/get-active-loan-summary', GetActiveLoanSummary::get_name() );
	}

	public function test_registration_args_shape(): void {
		$args = GetActiveLoanSummary::get_registration_args();

		$this->assertSame( AbilitiesRegistrar::CATEGORY_SLUG, $args['category'] );
		$this->assertSame( [ GetActiveLoanSummary::class, 'execute' ], $args['execute_callback'] );
		$this->assertSame( [ AbilitiesRegistrar::class, 'current_user_can_manage_woocommerce' ], $args['permission_callback'] );
		$this->assertTrue( $args['meta']['show_in_rest'] );
		$this->assertTrue( $args['meta']['annotations']['readonly'] );
		$this->assertSame( 'object', $args['input_schema']['type'] );
		$this->assertFalse( $args['input_schema']['additionalProperties'] );
	}

	public function test_execute_delegates_to_active_loan_summary_endpoint(): void {
		$canned = [ 'details' => [] ];
		$filter = function ( $result, $server, $request ) use ( $canned ) {
			if ( $request->get_route() === '/wc/v3/payments/capital/active_loan_summary' ) {
				return new \WP_REST_Response( $canned, 200 );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );

		try {
			$result = GetActiveLoanSummary::execute( null );
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertSame(
			$canned,
			$result,
			'execute() must delegate to /wc/v3/payments/capital/active_loan_summary and unwrap the response.'
		);
	}
}
