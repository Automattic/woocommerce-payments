<?php
/**
 * Tests for WCPay\Internal\Abilities\Domain\GetTransactions.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities\Domain;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\AbilitiesRegistrar;
use WCPay\Internal\Abilities\Domain\GetTransactions;

/**
 * @coversDefaultClass \WCPay\Internal\Abilities\Domain\GetTransactions
 */
class GetTransactionsTest extends WCPAY_UnitTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		if ( ! interface_exists( \Automattic\WooCommerce\Abilities\AbilityDefinition::class ) ) {
			self::markTestSkipped( 'AbilityDefinition interface not available — requires WooCommerce 10.9+.' );
		}
	}

	public function test_name() {
		$this->assertSame( 'woocommerce-payments/get-transactions', GetTransactions::get_name() );
	}

	public function test_registration_args_uses_paginated_input_and_envelope_output() {
		$args = GetTransactions::get_registration_args();

		// Pagination input properties from the base class.
		$this->assertArrayHasKey( 'page', $args['input_schema']['properties'] );
		$this->assertArrayHasKey( 'per_page', $args['input_schema']['properties'] );
		$this->assertSame( 25, $args['input_schema']['properties']['per_page']['default'] );
		$this->assertSame( 100, $args['input_schema']['properties']['per_page']['maximum'] );
		$this->assertSame( 1, $args['input_schema']['properties']['page']['default'] );
		$this->assertFalse( $args['input_schema']['additionalProperties'] );

		// Paginated output envelope.
		$this->assertSame( 'object', $args['output_schema']['type'] );
		$this->assertArrayHasKey( 'transactions', $args['output_schema']['properties'] );
		$this->assertSame( 'array', $args['output_schema']['properties']['transactions']['type'] );
		$this->assertArrayHasKey( 'total_pages', $args['output_schema']['properties'] );
		$this->assertArrayHasKey( 'page', $args['output_schema']['properties'] );
		$this->assertArrayHasKey( 'per_page', $args['output_schema']['properties'] );
		$this->assertFalse( $args['output_schema']['additionalProperties'] );
	}

	public function test_registration_args_has_readonly_meta_and_correct_category() {
		$args = GetTransactions::get_registration_args();

		$this->assertSame( AbilitiesRegistrar::CATEGORY_SLUG, $args['category'] );
		$this->assertSame( [ GetTransactions::class, 'execute' ], $args['execute_callback'] );
		$this->assertSame( [ AbilitiesRegistrar::class, 'current_user_can_manage_woocommerce' ], $args['permission_callback'] );
		$this->assertTrue( $args['meta']['annotations']['readonly'] );
		$this->assertFalse( $args['meta']['annotations']['destructive'] );
		$this->assertTrue( $args['meta']['annotations']['idempotent'] );
		$this->assertTrue( $args['meta']['show_in_rest'] );
		$this->assertTrue( $args['meta']['mcp']['public'] );
	}

	public function test_registration_args_includes_filter_properties() {
		$args       = GetTransactions::get_registration_args();
		$properties = $args['input_schema']['properties'];

		// Filter properties lifted from transactions_list_input_schema().
		$this->assertArrayHasKey( 'match', $properties );
		$this->assertArrayHasKey( 'date_before', $properties );
		$this->assertArrayHasKey( 'date_after', $properties );
		$this->assertArrayHasKey( 'date_between', $properties );
		$this->assertArrayHasKey( 'type_is', $properties );
		$this->assertArrayHasKey( 'source_device_is', $properties );
		$this->assertArrayHasKey( 'channel_is', $properties );
		$this->assertArrayHasKey( 'customer_country_is', $properties );
		$this->assertArrayHasKey( 'risk_level_is', $properties );
		$this->assertArrayHasKey( 'store_currency_is', $properties );
		$this->assertArrayHasKey( 'customer_currency_is', $properties );
		$this->assertArrayHasKey( 'search', $properties );
		$this->assertArrayHasKey( 'orderby', $properties );
		$this->assertArrayHasKey( 'order', $properties );
		$this->assertArrayHasKey( 'deposit_id', $properties );
	}

	public function test_execute_defaults_page_and_per_page_when_input_is_empty() {
		$fixture = [
			'data'        => [ [ 'id' => 'txn_1' ], [ 'id' => 'txn_2' ] ],
			'total_count' => 53,
		];
		$filter  = function ( $result, $server, $request ) use ( $fixture ) {
			if ( $request->get_route() === '/wc/v3/payments/transactions' ) {
				return new \WP_REST_Response( $fixture, 200 );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );

		try {
			$result = GetTransactions::execute( [] );
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'transactions', $result );
		$this->assertSame( [ [ 'id' => 'txn_1' ], [ 'id' => 'txn_2' ] ], $result['transactions'] );
		$this->assertSame( 1, $result['page'] );
		$this->assertSame( 25, $result['per_page'] );
		// total_pages = ceil(53 / 25) = 3.
		$this->assertSame( 3, $result['total_pages'] );
	}

	public function test_execute_uses_provided_pagination() {
		$fixture = [
			'data'        => [ [ 'id' => 'txn_x' ] ],
			'total_count' => 100,
		];
		$filter  = function ( $result, $server, $request ) use ( $fixture ) {
			if ( $request->get_route() === '/wc/v3/payments/transactions' ) {
				return new \WP_REST_Response( $fixture, 200 );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );

		try {
			$result = GetTransactions::execute(
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

	public function test_execute_propagates_wp_error() {
		$filter = function ( $result, $server, $request ) {
			if ( $request->get_route() === '/wc/v3/payments/transactions' ) {
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
			$result = GetTransactions::execute( [] );
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertInstanceOf( \WP_Error::class, $result );
	}
}
