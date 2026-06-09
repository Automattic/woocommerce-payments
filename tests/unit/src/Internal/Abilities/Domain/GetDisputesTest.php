<?php
/**
 * Tests for WCPay\Internal\Abilities\Domain\GetDisputes.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities\Domain;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\AbilitiesRegistrar;
use WCPay\Internal\Abilities\Domain\GetDisputes;

/**
 * @coversDefaultClass \WCPay\Internal\Abilities\Domain\GetDisputes
 */
class GetDisputesTest extends WCPAY_UnitTestCase {


	public function test_name(): void {
		$this->assertSame( 'woocommerce-payments/get-disputes', GetDisputes::get_name() );
	}

	public function test_registration_args_uses_paginated_input_and_envelope_output(): void {
		$args = GetDisputes::get_registration_args();

		// Pagination input properties from the base class.
		$this->assertArrayHasKey( 'page', $args['input_schema']['properties'] );
		$this->assertArrayHasKey( 'per_page', $args['input_schema']['properties'] );
		$this->assertSame( 25, $args['input_schema']['properties']['per_page']['default'] );
		$this->assertSame( 100, $args['input_schema']['properties']['per_page']['maximum'] );
		$this->assertSame( 1, $args['input_schema']['properties']['page']['default'] );
		$this->assertFalse( $args['input_schema']['additionalProperties'] );

		// Paginated output envelope.
		$this->assertSame( 'object', $args['output_schema']['type'] );
		$this->assertArrayHasKey( 'disputes', $args['output_schema']['properties'] );
		$this->assertSame( 'array', $args['output_schema']['properties']['disputes']['type'] );
		$this->assertArrayHasKey( 'total_pages', $args['output_schema']['properties'] );
		$this->assertArrayHasKey( 'page', $args['output_schema']['properties'] );
		$this->assertArrayHasKey( 'per_page', $args['output_schema']['properties'] );
		$this->assertFalse( $args['output_schema']['additionalProperties'] );
	}

	public function test_registration_args_has_readonly_meta_and_correct_category(): void {
		$args = GetDisputes::get_registration_args();

		$this->assertSame( AbilitiesRegistrar::CATEGORY_SLUG, $args['category'] );
		$this->assertSame( [ GetDisputes::class, 'execute' ], $args['execute_callback'] );
		$this->assertSame( [ AbilitiesRegistrar::class, 'current_user_can_manage_woocommerce' ], $args['permission_callback'] );
		$this->assertTrue( $args['meta']['annotations']['readonly'] );
		$this->assertFalse( $args['meta']['annotations']['destructive'] );
		$this->assertTrue( $args['meta']['annotations']['idempotent'] );
		$this->assertTrue( $args['meta']['show_in_rest'] );
		$this->assertTrue( $args['meta']['mcp']['public'] );
	}

	public function test_registration_args_includes_filter_properties(): void {
		$args       = GetDisputes::get_registration_args();
		$properties = $args['input_schema']['properties'];

		$this->assertArrayHasKey( 'match', $properties );
		$this->assertArrayHasKey( 'store_currency_is', $properties );
		$this->assertArrayHasKey( 'date_before', $properties );
		$this->assertArrayHasKey( 'date_after', $properties );
		$this->assertArrayHasKey( 'date_between', $properties );
		$this->assertArrayHasKey( 'search', $properties );
		$this->assertArrayHasKey( 'status_is', $properties );
		$this->assertArrayHasKey( 'status_is_not', $properties );
		$this->assertArrayHasKey( 'orderby', $properties );
		$this->assertArrayHasKey( 'order', $properties );
	}

	public function test_execute_defaults_page_and_per_page_when_input_is_empty(): void {
		$fixture = [
			'data'        => [ [ 'id' => 'dp_1' ], [ 'id' => 'dp_2' ] ],
			'total_count' => 53,
		];
		$filter  = function ( $result, $server, $request ) use ( $fixture ) {
			if ( $request->get_route() === '/wc/v3/payments/disputes' ) {
				return new \WP_REST_Response( $fixture, 200 );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );

		try {
			$result = GetDisputes::execute( [] );
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'disputes', $result );
		$this->assertSame( [ [ 'id' => 'dp_1' ], [ 'id' => 'dp_2' ] ], $result['disputes'] );
		$this->assertSame( 1, $result['page'] );
		$this->assertSame( 25, $result['per_page'] );
		// total_pages = ceil(53 / 25) = 3.
		$this->assertSame( 3, $result['total_pages'] );
	}

	public function test_execute_uses_provided_pagination(): void {
		$fixture = [
			'data'        => [ [ 'id' => 'dp_x' ] ],
			'total_count' => 100,
		];
		$filter  = function ( $result, $server, $request ) use ( $fixture ) {
			if ( $request->get_route() === '/wc/v3/payments/disputes' ) {
				return new \WP_REST_Response( $fixture, 200 );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );

		try {
			$result = GetDisputes::execute(
				[
					'page'     => 4,
					'per_page' => 10,
				]
			);
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertIsArray( $result );
		$this->assertSame( 4, $result['page'] );
		$this->assertSame( 10, $result['per_page'] );
		// total_pages = ceil(100 / 10) = 10.
		$this->assertSame( 10, $result['total_pages'] );
	}

	public function test_execute_propagates_wp_error(): void {
		$filter = function ( $result, $server, $request ) {
			if ( $request->get_route() === '/wc/v3/payments/disputes' ) {
				return new \WP_REST_Response(
					[
						'code'    => 'wcpay_test_error',
						'message' => 'Intentional test failure',
						'data'    => [ 'status' => 500 ],
					],
					500
				);
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );

		try {
			$result = GetDisputes::execute( [] );
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertInstanceOf( \WP_Error::class, $result );
	}
}
