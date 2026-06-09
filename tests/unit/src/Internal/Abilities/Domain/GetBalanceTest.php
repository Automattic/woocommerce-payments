<?php
/**
 * Tests for WCPay\Internal\Abilities\Domain\GetBalance.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities\Domain;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\AbilitiesRegistrar;
use WCPay\Internal\Abilities\Domain\GetBalance;

/**
 * @coversDefaultClass \WCPay\Internal\Abilities\Domain\GetBalance
 */
class GetBalanceTest extends WCPAY_UnitTestCase {

	public function test_name(): void {
		$this->assertSame( 'woocommerce-payments/get-balance', GetBalance::get_name() );
	}

	public function test_registration_args_read_annotations_and_required_fields(): void {
		$args = GetBalance::get_registration_args();

		$this->assertTrue( $args['meta']['annotations']['readonly'] );
		$this->assertFalse( $args['meta']['annotations']['destructive'] );
		$this->assertTrue( $args['meta']['annotations']['idempotent'] );
		$this->assertTrue( $args['meta']['mcp']['public'] );
		$this->assertSame( [ 'date_start', 'date_end', 'currency' ], $args['input_schema']['required'] );
	}

	public function test_execute_returns_error_when_required_field_missing(): void {
		$result = GetBalance::execute(
			[
				'date_start' => '2026-01-01',
				'date_end'   => '2026-02-01',
			]
		);
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_missing_currency', $result->get_error_code() );
	}

	public function test_execute_delegates_and_returns_summary(): void {
		$fixture = [
			'currency'       => 'usd',
			'ending_balance' => [ 'amount' => 0 ],
		];
		$filter  = function ( $result, $server, $request ) use ( $fixture ) {
			if ( $request->get_route() === '/wc/v3/payments/reports/balance' ) {
				return new \WP_REST_Response( $fixture, 200 );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );
		try {
			$result = GetBalance::execute(
				[
					'date_start' => '2026-01-01',
					'date_end'   => '2026-02-01',
					'currency'   => 'usd',
				]
			);
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertSame( $fixture, $result );
	}
}
