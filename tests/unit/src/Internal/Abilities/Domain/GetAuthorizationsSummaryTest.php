<?php
/**
 * Tests for WCPay\Internal\Abilities\Domain\GetAuthorizationsSummary.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities\Domain;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\Domain\GetAuthorizationsSummary;

/**
 * @coversDefaultClass \WCPay\Internal\Abilities\Domain\GetAuthorizationsSummary
 */
class GetAuthorizationsSummaryTest extends WCPAY_UnitTestCase {

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
