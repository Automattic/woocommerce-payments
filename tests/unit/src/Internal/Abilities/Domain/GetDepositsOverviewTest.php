<?php
/**
 * Tests for WCPay\Internal\Abilities\Domain\GetDepositsOverview.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities\Domain;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\Domain\GetDepositsOverview;

/**
 * @coversDefaultClass \WCPay\Internal\Abilities\Domain\GetDepositsOverview
 */
class GetDepositsOverviewTest extends WCPAY_UnitTestCase {

	public function test_execute_delegates_to_deposits_overview_endpoint(): void {
		$canned = [ 'overviews' => [] ];
		$filter = function ( $result, $server, $request ) use ( $canned ) {
			if ( $request->get_route() === '/wc/v3/payments/deposits/overview-all' ) {
				return new \WP_REST_Response( $canned, 200 );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );

		try {
			$result = GetDepositsOverview::execute( null );
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertSame(
			$canned,
			$result,
			'execute() must delegate to /wc/v3/payments/deposits/overview-all and unwrap the response.'
		);
	}
}
