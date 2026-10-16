<?php
/**
 * Tests for WCPay\Internal\Abilities\Domain\GetActiveLoanSummary.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities\Domain;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\Domain\GetActiveLoanSummary;

/**
 * @coversDefaultClass \WCPay\Internal\Abilities\Domain\GetActiveLoanSummary
 */
class GetActiveLoanSummaryTest extends WCPAY_UnitTestCase {

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
