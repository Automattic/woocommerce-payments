<?php
/**
 * Tests for WCPay\Internal\Abilities\Domain\AbstractWCPayAbility.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities\Domain;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\Domain\AbstractWCPayAbility;

/**
 * @coversDefaultClass \WCPay\Internal\Abilities\Domain\AbstractWCPayAbility
 */
class AbstractWCPayAbilityTest extends WCPAY_UnitTestCase {

	public function test_collection_output_schema_shape(): void {
		$schema = self::invoke_protected( 'get_collection_output_schema', [ 'transactions', [ 'type' => 'object' ] ] );

		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'transactions', $schema['properties'] );
		$this->assertSame( 'array', $schema['properties']['transactions']['type'] );
		$this->assertSame( [ 'type' => 'object' ], $schema['properties']['transactions']['items'] );
		$this->assertSame( 'integer', $schema['properties']['total_pages']['type'] );
		$this->assertSame( 'integer', $schema['properties']['page']['type'] );
		$this->assertSame( 'integer', $schema['properties']['per_page']['type'] );
		$this->assertFalse( $schema['additionalProperties'] );
	}

	public function test_pagination_input_properties_defaults(): void {
		$props = self::invoke_protected( 'get_pagination_input_properties' );

		$this->assertSame( 1, $props['page']['default'] );
		$this->assertSame( 1, $props['page']['minimum'] );
		$this->assertSame( 25, $props['per_page']['default'] );
		$this->assertSame( 1, $props['per_page']['minimum'] );
		$this->assertSame( 100, $props['per_page']['maximum'] );
	}

	public function test_pagination_input_properties_custom_caps(): void {
		$props = self::invoke_protected( 'get_pagination_input_properties', [ 10, 200 ] );

		$this->assertSame( 10, $props['per_page']['default'] );
		$this->assertSame( 200, $props['per_page']['maximum'] );
	}

	public function test_compute_total_pages(): void {
		$this->assertSame( 0, self::invoke_protected( 'compute_total_pages', [ 0, 25 ] ) );
		$this->assertSame( 0, self::invoke_protected( 'compute_total_pages', [ -5, 25 ] ) );
		$this->assertSame( 0, self::invoke_protected( 'compute_total_pages', [ 100, 0 ] ) );
		$this->assertSame( 1, self::invoke_protected( 'compute_total_pages', [ 1, 25 ] ) );
		$this->assertSame( 1, self::invoke_protected( 'compute_total_pages', [ 25, 25 ] ) );
		$this->assertSame( 2, self::invoke_protected( 'compute_total_pages', [ 26, 25 ] ) );
		$this->assertSame( 4, self::invoke_protected( 'compute_total_pages', [ 100, 25 ] ) );
	}

	public function test_wrap_paginated_response_preserves_wp_error(): void {
		$err = new \WP_Error( 'boom', 'Something broke' );
		$out = self::invoke_protected( 'wrap_paginated_response', [ $err, 'transactions', 1, 25 ] );
		$this->assertSame( $err, $out );
	}

	public function test_wrap_paginated_response_handles_modern_shape(): void {
		$response = [
			'data'        => [ [ 'id' => 'txn_1' ], [ 'id' => 'txn_2' ] ],
			'total_count' => 53,
		];
		$out      = self::invoke_protected( 'wrap_paginated_response', [ $response, 'transactions', 2, 25 ] );

		$this->assertSame( [ [ 'id' => 'txn_1' ], [ 'id' => 'txn_2' ] ], $out['transactions'] );
		$this->assertSame( 3, $out['total_pages'] );
		$this->assertSame( 2, $out['page'] );
		$this->assertSame( 25, $out['per_page'] );
	}

	public function test_wrap_paginated_response_handles_flat_list(): void {
		$response = [ [ 'id' => 'd_1' ], [ 'id' => 'd_2' ], [ 'id' => 'd_3' ] ];
		$out      = self::invoke_protected( 'wrap_paginated_response', [ $response, 'disputes', 1, 25 ] );

		$this->assertSame( $response, $out['disputes'] );
		$this->assertSame( 1, $out['total_pages'] );
		$this->assertSame( 1, $out['page'] );
		$this->assertSame( 25, $out['per_page'] );
	}

	public function test_wrap_paginated_response_handles_non_array(): void {
		$out = self::invoke_protected( 'wrap_paginated_response', [ null, 'transactions', 1, 25 ] );
		$this->assertSame( [], $out['transactions'] );
		$this->assertSame( 0, $out['total_pages'] );
	}

	public function test_wrap_paginated_response_wraps_associative_payload(): void {
		$response = [
			'summary' => 'partial',
			'count'   => 7,
		];
		$out      = self::invoke_protected( 'wrap_paginated_response', [ $response, 'rows', 1, 25 ] );

		$this->assertSame( [ $response ], $out['rows'] );
		$this->assertSame( 1, $out['total_pages'] );
		$this->assertSame( 1, $out['page'] );
		$this->assertSame( 25, $out['per_page'] );
	}

	public function test_wrap_paginated_response_handles_empty_flat_list(): void {
		$out = self::invoke_protected( 'wrap_paginated_response', [ [], 'rows', 1, 25 ] );

		$this->assertSame( [], $out['rows'] );
		$this->assertSame( 0, $out['total_pages'] );
		$this->assertSame( 1, $out['page'] );
		$this->assertSame( 25, $out['per_page'] );
	}

	/**
	 * Invoke a protected static helper via reflection.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Positional arguments.
	 * @return mixed
	 */
	private static function invoke_protected( string $method, array $args = [] ) {
		$ref = new \ReflectionMethod( AbstractWCPayAbility::class, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( null, $args );
	}
}
