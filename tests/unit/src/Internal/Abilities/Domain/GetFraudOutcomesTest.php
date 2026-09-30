<?php
/**
 * Tests for WCPay\Internal\Abilities\Domain\GetFraudOutcomes.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities\Domain;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\AbilitiesRegistrar;
use WCPay\Internal\Abilities\Domain\GetFraudOutcomes;

/**
 * @coversDefaultClass \WCPay\Internal\Abilities\Domain\GetFraudOutcomes
 */
class GetFraudOutcomesTest extends WCPAY_UnitTestCase {

	public function test_name(): void {
		$this->assertSame( 'woocommerce-payments/get-fraud-outcomes', GetFraudOutcomes::get_name() );
	}

	public function test_registration_args_paginated_and_requires_status(): void {
		$args = GetFraudOutcomes::get_registration_args();

		$this->assertTrue( $args['meta']['annotations']['readonly'] );
		$this->assertTrue( $args['meta']['mcp']['public'] );
		$this->assertContains( 'status', $args['input_schema']['required'] );
		$this->assertArrayHasKey( 'page', $args['input_schema']['properties'] );
		$this->assertArrayHasKey( 'per_page', $args['input_schema']['properties'] );
		$this->assertArrayHasKey( 'fraud_outcomes', $args['output_schema']['properties'] );
	}

	public function test_execute_returns_error_when_status_missing(): void {
		$result = GetFraudOutcomes::execute( [] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_missing_status', $result->get_error_code() );
	}

	public function test_execute_delegates_and_wraps_envelope(): void {
		$fixture = [
			'data'        => [ [ 'order_id' => 1 ], [ 'order_id' => 2 ] ],
			'total_count' => 2,
		];
		$filter  = function ( $result, $server, $request ) use ( $fixture ) {
			if ( $request->get_route() === '/wc/v3/payments/transactions/fraud-outcomes' ) {
				return new \WP_REST_Response( $fixture, 200 );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );
		try {
			$result = GetFraudOutcomes::execute( [ 'status' => 'review' ] );
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertArrayHasKey( 'fraud_outcomes', $result );
		$this->assertSame( [ [ 'order_id' => 1 ], [ 'order_id' => 2 ] ], $result['fraud_outcomes'] );
		$this->assertSame( 1, $result['page'] );
		$this->assertSame( 25, $result['per_page'] );
	}
}
