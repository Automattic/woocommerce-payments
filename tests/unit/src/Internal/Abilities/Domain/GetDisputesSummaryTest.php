<?php
/**
 * Tests for WCPay\Internal\Abilities\Domain\GetDisputesSummary.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities\Domain;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\Domain\GetDisputesSummary;

/**
 * @coversDefaultClass \WCPay\Internal\Abilities\Domain\GetDisputesSummary
 */
class GetDisputesSummaryTest extends WCPAY_UnitTestCase {

	public function test_execute_delegates_to_disputes_summary_endpoint(): void {
		$canned = [
			'count'    => 10,
			'currency' => 'usd',
		];
		$filter = function ( $result, $server, $request ) use ( $canned ) {
			if ( $request->get_route() === '/wc/v3/payments/disputes/summary' ) {
				return new \WP_REST_Response( $canned, 200 );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );

		try {
			$result = GetDisputesSummary::execute( [] );
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertSame(
			$canned,
			$result,
			'execute() must delegate to /wc/v3/payments/disputes/summary and unwrap the response.'
		);
	}
}
