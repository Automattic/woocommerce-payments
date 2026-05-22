<?php
/**
 * Tests for WCPay\Internal\Abilities\Domain\GetTimeline.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\Internal\Abilities\Domain;

use WCPAY_UnitTestCase;
use WCPay\Internal\Abilities\AbilitiesRegistrar;
use WCPay\Internal\Abilities\Domain\GetTimeline;

/**
 * @coversDefaultClass \WCPay\Internal\Abilities\Domain\GetTimeline
 */
class GetTimelineTest extends WCPAY_UnitTestCase {

	public function test_name(): void {
		$this->assertSame( 'woocommerce-payments/get-timeline', GetTimeline::get_name() );
	}

	public function test_registration_args_shape(): void {
		$args = GetTimeline::get_registration_args();

		$this->assertSame( AbilitiesRegistrar::CATEGORY_SLUG, $args['category'] );
		$this->assertSame( [ GetTimeline::class, 'execute' ], $args['execute_callback'] );
		$this->assertSame( [ AbilitiesRegistrar::class, 'current_user_can_manage_woocommerce' ], $args['permission_callback'] );
		$this->assertTrue( $args['meta']['show_in_rest'] );
		$this->assertSame( 'object', $args['input_schema']['type'] );
		$this->assertFalse( $args['input_schema']['additionalProperties'] );
		$this->assertSame( [ 'intention_id' ], $args['input_schema']['required'] );
		$this->assertSame( 'string', $args['input_schema']['properties']['intention_id']['type'] );
	}

	public function test_execute_rejects_missing_id(): void {
		$result = GetTimeline::execute( [] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_missing_intention_id', $result->get_error_code() );
	}

	public function test_execute_rejects_non_string_id(): void {
		$result = GetTimeline::execute( [ 'intention_id' => 123 ] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_missing_intention_id', $result->get_error_code() );
	}

	public function test_execute_rejects_empty_string_id(): void {
		$result = GetTimeline::execute( [ 'intention_id' => '' ] );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wcpay_missing_intention_id', $result->get_error_code() );
	}

	public function test_execute_delegates_to_timeline_route(): void {
		$captured = null;
		$filter   = function ( $result, $server, $request ) use ( &$captured ) {
			if ( strpos( $request->get_route(), '/wc/v3/payments/timeline/' ) === 0 ) {
				$captured = $request->get_route();
				return new \WP_REST_Response( [ 'events' => [] ], 200 );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );

		try {
			$result = GetTimeline::execute( [ 'intention_id' => 'pi_abc' ] );
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}

		$this->assertSame( '/wc/v3/payments/timeline/pi_abc', $captured );
		$this->assertSame( [ 'events' => [] ], $result );
	}
}
