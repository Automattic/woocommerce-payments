<?php
/**
 * Tests for WCPay\Internal\Abilities\Domain\GetFeesSummary.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities\Domain;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\AbilitiesRegistrar;
use WCPay\Internal\Abilities\Domain\GetFeesSummary;

/**
 * @coversDefaultClass \WCPay\Internal\Abilities\Domain\GetFeesSummary
 */
class GetFeesSummaryTest extends WCPAY_UnitTestCase {

	public function test_name(): void {
		$this->assertSame( 'woocommerce-payments/get-fees-summary', GetFeesSummary::get_name() );
	}

	public function test_registration_args_read_annotations(): void {
		$args = GetFeesSummary::get_registration_args();

		$this->assertTrue( $args['meta']['annotations']['readonly'] );
		$this->assertTrue( $args['meta']['mcp']['public'] );
		$this->assertFalse( $args['input_schema']['additionalProperties'] );
	}

	public function test_execute_delegates_and_returns_summary(): void {
		$fixture = [
			'count'    => 15,
			'fees'     => 12500,
			'net'      => 87500,
			'currency' => 'usd',
		];
		$filter  = function ( $result, $server, $request ) use ( $fixture ) {
			if ( $request->get_route() === '/wc/v3/payments/reports/fees/summary' ) {
				return new \WP_REST_Response( $fixture, 200 );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );
		try {
			$result = GetFeesSummary::execute( [ 'date_after' => '2026-01-01' ] );
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertSame( $fixture, $result );
	}
}
