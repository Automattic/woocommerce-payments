<?php
/**
 * Tests for WCPay\Internal\Abilities\Domain\GetDepositsSummary.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities\Domain;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\Domain\GetDepositsSummary;

/**
 * @coversDefaultClass \WCPay\Internal\Abilities\Domain\GetDepositsSummary
 */
class GetDepositsSummaryTest extends WCPAY_UnitTestCase {

	public function test_execute_delegates_to_deposits_summary_endpoint(): void {
		$canned = [
			'count'    => 5,
			'currency' => 'usd',
		];
		$filter = function ( $result, $server, $request ) use ( $canned ) {
			if ( $request->get_route() === '/wc/v3/payments/deposits/summary' ) {
				return new \WP_REST_Response( $canned, 200 );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );

		try {
			$result = GetDepositsSummary::execute( [] );
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertSame(
			$canned,
			$result,
			'execute() must delegate to /wc/v3/payments/deposits/summary and unwrap the response.'
		);
	}
}
