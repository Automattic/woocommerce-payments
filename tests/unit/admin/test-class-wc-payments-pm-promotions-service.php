<?php
/**
 * Class WC_Payments_PM_Promotions_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Constants\Payment_Method;

/**
 * WC_Payments_PM_Promotions_Service unit tests.
 *
 * Tests validation, filtering, normalization, and action methods for PM promotions.
 */
class WC_Payments_PM_Promotions_Service_Test extends WCPAY_UnitTestCase {
	/**
	 * Service under test.
	 *
	 * @var WC_Payments_PM_Promotions_Service
	 */
	private $service;

	/**
	 * Mock gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $mock_gateway;

	public function set_up() {
		parent::set_up();

		$this->mock_gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$this->service      = new WC_Payments_PM_Promotions_Service( $this->mock_gateway );
	}

	public function tear_down() {
		parent::tear_down();
		delete_transient( WC_Payments_PM_Promotions_Service::PROMOTIONS_CACHE_KEY );
		delete_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION );
		delete_option( WC_Payments_PM_Promotions_Service::ACTIVATED_PROMOTIONS_OPTION );
		$this->service->reset_memo();
	}

	/**
	 * Helper method to invoke private methods for testing.
	 *
	 * @param string $method_name The method name.
	 * @param array  $args        The arguments to pass.
	 *
	 * @return mixed The method result.
	 */
	private function invoke_private_method( string $method_name, array $args = [] ) {
		$reflection = new ReflectionClass( $this->service );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );

		return $method->invokeArgs( $this->service, $args );
	}

	/**
	 * Helper to create a valid promotion array.
	 *
	 * @param array $overrides Optional overrides.
	 *
	 * @return array Promotion data.
	 */
	private function create_valid_promotion( array $overrides = [] ): array {
		return array_merge(
			[
				'id'             => 'test-promo__spotlight',
				'promo_id'       => 'test-promo',
				'payment_method' => 'klarna',
				'type'           => 'spotlight',
				'title'          => 'Test Promotion',
				'description'    => 'Test description',
				'cta_label'      => 'Enable Now',
				'tc_url'         => 'https://example.com/terms',
				'tc_label'       => 'See terms',
			],
			$overrides
		);
	}

	/*
	 * =========================================================================
	 * VALIDATION TESTS
	 * =========================================================================
	 */

	public function test_validate_promotion_accepts_valid_spotlight() {
		$promotion = $this->create_valid_promotion( [ 'type' => 'spotlight' ] );

		$result = $this->invoke_private_method( 'validate_promotion', [ $promotion ] );

		$this->assertTrue( $result );
	}

	public function test_validate_promotion_accepts_valid_badge() {
		$promotion = $this->create_valid_promotion( [ 'type' => 'badge' ] );

		$result = $this->invoke_private_method( 'validate_promotion', [ $promotion ] );

		$this->assertTrue( $result );
	}

	public function test_validate_promotion_rejects_null() {
		$result = $this->invoke_private_method( 'validate_promotion', [ null ] );

		$this->assertFalse( $result );
	}

	public function test_validate_promotion_rejects_empty_array() {
		$result = $this->invoke_private_method( 'validate_promotion', [ [] ] );

		$this->assertFalse( $result );
	}

	public function test_validate_promotion_rejects_non_array() {
		$result = $this->invoke_private_method( 'validate_promotion', [ 'string' ] );

		$this->assertFalse( $result );
	}

	/**
	 * @dataProvider provider_required_fields
	 */
	public function test_validate_promotion_rejects_missing_required_field( string $field ) {
		$promotion = $this->create_valid_promotion();
		unset( $promotion[ $field ] );

		$result = $this->invoke_private_method( 'validate_promotion', [ $promotion ] );

		$this->assertFalse( $result, "Should reject promotion missing required field: $field" );
	}

	public function provider_required_fields(): array {
		return [
			'id'             => [ 'id' ],
			'promo_id'       => [ 'promo_id' ],
			'payment_method' => [ 'payment_method' ],
			'type'           => [ 'type' ],
			'title'          => [ 'title' ],
			'description'    => [ 'description' ],
			'tc_url'         => [ 'tc_url' ],
		];
	}

	/**
	 * @dataProvider provider_required_fields
	 */
	public function test_validate_promotion_rejects_non_string_required_field( string $field ) {
		$promotion           = $this->create_valid_promotion();
		$promotion[ $field ] = 123; // Non-string value.

		$result = $this->invoke_private_method( 'validate_promotion', [ $promotion ] );

		$this->assertFalse( $result, "Should reject promotion with non-string $field" );
	}

	public function test_validate_promotion_rejects_invalid_type() {
		$promotion = $this->create_valid_promotion( [ 'type' => 'invalid_type' ] );

		$result = $this->invoke_private_method( 'validate_promotion', [ $promotion ] );

		$this->assertFalse( $result );
	}

	public function test_validate_promotion_accepts_missing_optional_fields() {
		$promotion = [
			'id'             => 'test-promo__spotlight',
			'promo_id'       => 'test-promo',
			'payment_method' => 'klarna',
			'type'           => 'spotlight',
			'title'          => 'Test Promotion',
			'description'    => 'Test description',
			'tc_url'         => 'https://example.com/terms',
			// cta_label, tc_label, footnote, image are optional.
		];

		$result = $this->invoke_private_method( 'validate_promotion', [ $promotion ] );

		$this->assertTrue( $result );
	}

	/*
	 * =========================================================================
	 * PM VALIDATION TESTS
	 * =========================================================================
	 */

	public function test_is_valid_payment_method_accepts_klarna() {
		$result = $this->invoke_private_method( 'is_valid_payment_method', [ 'klarna' ] );

		$this->assertTrue( $result );
	}

	public function test_is_valid_payment_method_accepts_affirm() {
		$result = $this->invoke_private_method( 'is_valid_payment_method', [ 'affirm' ] );

		$this->assertTrue( $result );
	}

	public function test_is_valid_payment_method_accepts_card() {
		$result = $this->invoke_private_method( 'is_valid_payment_method', [ 'card' ] );

		$this->assertTrue( $result );
	}

	public function test_is_valid_payment_method_rejects_invalid() {
		$result = $this->invoke_private_method( 'is_valid_payment_method', [ 'invalid_pm' ] );

		$this->assertFalse( $result );
	}

	public function test_is_valid_payment_method_rejects_empty_string() {
		$result = $this->invoke_private_method( 'is_valid_payment_method', [ '' ] );

		$this->assertFalse( $result );
	}

	/*
	 * =========================================================================
	 * FILTERING TESTS
	 * =========================================================================
	 */

	public function test_filter_promotions_removes_invalid_payment_method() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		$promotions = [
			$this->create_valid_promotion( [ 'payment_method' => 'invalid_pm' ] ),
		];

		$result = $this->invoke_private_method( 'filter_promotions', [ $promotions ] );

		$this->assertEmpty( $result );
	}

	public function test_filter_promotions_removes_already_enabled_pm() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [ 'klarna' ] ); // Klarna is enabled.

		$promotions = [
			$this->create_valid_promotion( [ 'payment_method' => 'klarna' ] ),
		];

		$result = $this->invoke_private_method( 'filter_promotions', [ $promotions ] );

		$this->assertEmpty( $result );
	}

	public function test_filter_promotions_keeps_not_enabled_pm() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [ 'card' ] ); // Only card is enabled.

		$promotions = [
			$this->create_valid_promotion( [ 'payment_method' => 'klarna' ] ),
		];

		$result = $this->invoke_private_method( 'filter_promotions', [ $promotions ] );

		$this->assertCount( 1, $result );
		$this->assertSame( 'klarna', $result[0]['payment_method'] );
	}

	public function test_filter_promotions_keeps_first_promo_id_per_pm() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		$promotions = [
			$this->create_valid_promotion(
				[
					'id'             => 'first-promo__spotlight',
					'promo_id'       => 'first-promo',
					'payment_method' => 'klarna',
				]
			),
			$this->create_valid_promotion(
				[
					'id'             => 'second-promo__spotlight',
					'promo_id'       => 'second-promo',
					'payment_method' => 'klarna',
				]
			),
		];

		$result = $this->invoke_private_method( 'filter_promotions', [ $promotions ] );

		$this->assertCount( 1, $result );
		$this->assertSame( 'first-promo', $result[0]['promo_id'] );
	}

	public function test_filter_promotions_keeps_all_surfaces_for_same_promo_id() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		$promotions = [
			$this->create_valid_promotion(
				[
					'id'             => 'promo__spotlight',
					'promo_id'       => 'promo',
					'payment_method' => 'klarna',
					'type'           => 'spotlight',
				]
			),
			$this->create_valid_promotion(
				[
					'id'             => 'promo__badge',
					'promo_id'       => 'promo',
					'payment_method' => 'klarna',
					'type'           => 'badge',
				]
			),
		];

		$result = $this->invoke_private_method( 'filter_promotions', [ $promotions ] );

		$this->assertCount( 2, $result );
		$this->assertSame( 'spotlight', $result[0]['type'] );
		$this->assertSame( 'badge', $result[1]['type'] );
	}

	public function test_filter_promotions_allows_different_pm_same_promo_id() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		$promotions = [
			$this->create_valid_promotion(
				[
					'id'             => 'promo__klarna',
					'promo_id'       => 'promo',
					'payment_method' => 'klarna',
				]
			),
			$this->create_valid_promotion(
				[
					'id'             => 'promo__affirm',
					'promo_id'       => 'promo',
					'payment_method' => 'affirm',
				]
			),
		];

		$result = $this->invoke_private_method( 'filter_promotions', [ $promotions ] );

		$this->assertCount( 2, $result );
	}

	/*
	 * =========================================================================
	 * NORMALIZATION TESTS
	 * =========================================================================
	 */

	public function test_normalize_promotions_adds_payment_method_title() {
		$promotions = [
			$this->create_valid_promotion( [ 'payment_method' => 'klarna' ] ),
		];

		$result = $this->invoke_private_method( 'normalize_promotions', [ $promotions ] );

		$this->assertArrayHasKey( 'payment_method_title', $result[0] );
		// Fallback should capitalize the PM ID if no payment method object found.
		$this->assertNotEmpty( $result[0]['payment_method_title'] );
	}

	public function test_normalize_promotions_applies_cta_label_fallback() {
		$promotion = $this->create_valid_promotion( [ 'payment_method' => 'klarna' ] );
		unset( $promotion['cta_label'] );

		$result = $this->invoke_private_method( 'normalize_promotions', [ [ $promotion ] ] );

		$this->assertStringContainsString( 'Enable', $result[0]['cta_label'] );
	}

	public function test_normalize_promotions_keeps_existing_cta_label() {
		$promotions = [
			$this->create_valid_promotion( [ 'cta_label' => 'Custom CTA' ] ),
		];

		$result = $this->invoke_private_method( 'normalize_promotions', [ $promotions ] );

		$this->assertSame( 'Custom CTA', $result[0]['cta_label'] );
	}

	public function test_normalize_promotions_applies_tc_label_fallback() {
		$promotion = $this->create_valid_promotion();
		unset( $promotion['tc_label'] );

		$result = $this->invoke_private_method( 'normalize_promotions', [ [ $promotion ] ] );

		$this->assertArrayHasKey( 'tc_label', $result[0] );
		$this->assertNotEmpty( $result[0]['tc_label'] );
	}

	public function test_normalize_promotions_keeps_existing_tc_label() {
		$promotions = [
			$this->create_valid_promotion( [ 'tc_label' => 'Custom Terms' ] ),
		];

		$result = $this->invoke_private_method( 'normalize_promotions', [ $promotions ] );

		$this->assertSame( 'Custom Terms', $result[0]['tc_label'] );
	}

	public function test_normalize_promotions_sanitizes_text_fields() {
		$promotions = [
			$this->create_valid_promotion(
				[
					'title'       => '<script>alert("xss")</script>Test Title',
					'description' => '<b>Bold</b> description',
				]
			),
		];

		$result = $this->invoke_private_method( 'normalize_promotions', [ $promotions ] );

		// sanitize_text_field strips HTML.
		$this->assertStringNotContainsString( '<script>', $result[0]['title'] );
		$this->assertStringNotContainsString( '<b>', $result[0]['description'] );
	}

	public function test_normalize_promotions_preserves_optional_fields() {
		$promotions = [
			$this->create_valid_promotion(
				[
					'footnote' => 'Test footnote',
					'image'    => 'https://example.com/image.png',
				]
			),
		];

		$result = $this->invoke_private_method( 'normalize_promotions', [ $promotions ] );

		$this->assertSame( 'Test footnote', $result[0]['footnote'] );
		$this->assertSame( 'https://example.com/image.png', $result[0]['image'] );
	}

	/*
	 * =========================================================================
	 * ACTION TESTS - DISMISS
	 * =========================================================================
	 */

	public function test_dismiss_promotion_stores_dismissal_with_id() {
		$id = 'test-promo__spotlight';

		$this->service->dismiss_promotion( $id );

		$dismissals = WC_Payments_PM_Promotions_Service::get_promotion_dismissals();
		$this->assertArrayHasKey( $id, $dismissals );
		$this->assertIsInt( $dismissals[ $id ] );
	}

	public function test_dismiss_promotion_extracts_promo_id_from_id() {
		$id = 'test-promo__spotlight';

		$response = $this->service->dismiss_promotion( $id );

		$this->assertSame( 'test-promo', $response['promo_id'] );
		$this->assertSame( $id, $response['id'] );
	}

	public function test_dismiss_promotion_returns_success_response() {
		$id = 'test-promo__spotlight';

		$response = $this->service->dismiss_promotion( $id );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'dismissed', $response['status'] );
	}

	public function test_dismiss_promotion_clears_cache() {
		// Set up a cache entry.
		set_transient(
			WC_Payments_PM_Promotions_Service::PROMOTIONS_CACHE_KEY,
			[ 'promotions' => [] ],
			300
		);

		$this->service->dismiss_promotion( 'test-promo__spotlight' );

		$this->assertFalse( get_transient( WC_Payments_PM_Promotions_Service::PROMOTIONS_CACHE_KEY ) );
	}

	public function test_dismiss_promotion_allows_multiple_dismissals() {
		$this->service->dismiss_promotion( 'promo1__spotlight' );
		$this->service->dismiss_promotion( 'promo1__badge' );

		$dismissals = WC_Payments_PM_Promotions_Service::get_promotion_dismissals();

		$this->assertCount( 2, $dismissals );
		$this->assertArrayHasKey( 'promo1__spotlight', $dismissals );
		$this->assertArrayHasKey( 'promo1__badge', $dismissals );
	}

	/*
	 * =========================================================================
	 * ACTION TESTS - ACTIVATE
	 * =========================================================================
	 */

	public function test_activate_promotion_stores_activation() {
		$identifier = 'test-promo';

		$this->service->activate_promotion( $identifier );

		$this->assertTrue( WC_Payments_PM_Promotions_Service::is_promotion_activated( $identifier ) );
	}

	public function test_activate_promotion_returns_success_response() {
		$identifier = 'test-promo';

		$response = $this->service->activate_promotion( $identifier );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'active', $response['status'] );
		$this->assertSame( $identifier, $response['identifier'] );
	}

	public function test_activate_promotion_clears_cache() {
		// Set up a cache entry.
		set_transient(
			WC_Payments_PM_Promotions_Service::PROMOTIONS_CACHE_KEY,
			[ 'promotions' => [] ],
			300
		);

		$this->service->activate_promotion( 'test-promo' );

		$this->assertFalse( get_transient( WC_Payments_PM_Promotions_Service::PROMOTIONS_CACHE_KEY ) );
	}

	public function test_activate_promotion_records_timestamp() {
		$identifier = 'test-promo';
		$before     = time();

		$this->service->activate_promotion( $identifier );

		$timestamp = WC_Payments_PM_Promotions_Service::get_promotion_activation_time( $identifier );
		$this->assertGreaterThanOrEqual( $before, $timestamp );
		$this->assertLessThanOrEqual( time(), $timestamp );
	}

	/*
	 * =========================================================================
	 * INTEGRATION TESTS - get_visible_promotions()
	 * =========================================================================
	 */

	public function test_get_visible_promotions_returns_null_when_no_promotions() {
		// The mock data has promotions, but they should all be filtered out
		// when klarna and affirm are enabled.
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [ 'klarna', 'affirm' ] );

		$result = $this->service->get_visible_promotions();

		$this->assertNull( $result );
	}

	public function test_get_visible_promotions_filters_and_normalizes() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] ); // No PMs enabled, promotions should show.

		$result = $this->service->get_visible_promotions();

		// Should have promotions from mock data.
		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );

		// Each promotion should have normalized fields.
		foreach ( $result as $promotion ) {
			$this->assertArrayHasKey( 'payment_method_title', $promotion );
			$this->assertArrayHasKey( 'cta_label', $promotion );
			$this->assertArrayHasKey( 'tc_label', $promotion );
		}
	}

	public function test_get_visible_promotions_returns_array_values() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		$result = $this->service->get_visible_promotions();

		// Should be a sequential array, not associative.
		$this->assertSame( array_values( $result ), $result );
	}

	/*
	 * =========================================================================
	 * CACHE TESTS
	 * =========================================================================
	 */

	public function test_clear_cache_removes_transient() {
		set_transient(
			WC_Payments_PM_Promotions_Service::PROMOTIONS_CACHE_KEY,
			[ 'promotions' => [] ],
			300
		);

		$this->service->clear_cache();

		$this->assertFalse( get_transient( WC_Payments_PM_Promotions_Service::PROMOTIONS_CACHE_KEY ) );
	}

	public function test_reset_memo_allows_refetch() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		// First call - should populate memo.
		$first_result = $this->service->get_visible_promotions();

		// Reset memo.
		$this->service->reset_memo();

		// Second call - should work again.
		$second_result = $this->service->get_visible_promotions();

		$this->assertEquals( $first_result, $second_result );
	}
}
