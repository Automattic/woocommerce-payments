<?php
/**
 * Class WC_Payments_PM_Promotions_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Constants\Payment_Method;
use WCPay\Core\Server\Request\Activate_PM_Promotion;

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

	/**
	 * Original payment gateway map to restore after tests.
	 *
	 * @var array|null
	 */
	private $original_gateway_map;

	public function set_up() {
		parent::set_up();

		// Store original gateway map to restore in tear_down.
		$reflection = new ReflectionClass( WC_Payments::class );
		$property   = $reflection->getProperty( 'payment_gateway_map' );
		$property->setAccessible( true );
		$this->original_gateway_map = $property->getValue();

		// Create and set an admin user (required for get_visible_promotions capability check).
		$admin_user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user );

		$this->mock_gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );

		// Default available payment methods for validation tests.
		$this->mock_gateway->method( 'get_upe_available_payment_methods' )
			->willReturn( [ Payment_Method::CARD, Payment_Method::KLARNA, Payment_Method::AFFIRM, Payment_Method::AFTERPAY, Payment_Method::LINK, Payment_Method::SEPA ] );

		// Note: get_upe_enabled_payment_method_ids is NOT mocked here by default.
		// Tests that need it must configure it explicitly via $this->mock_gateway->method().

		// Default capability key map for tracks events.
		$this->mock_gateway->method( 'get_payment_method_capability_key_map' )
			->willReturn( [] );

		$this->service = new WC_Payments_PM_Promotions_Service( $this->mock_gateway );
	}

	public function tear_down() {
		parent::tear_down();

		// Restore original gateway map to prevent test pollution.
		$reflection = new ReflectionClass( WC_Payments::class );
		$property   = $reflection->getProperty( 'payment_gateway_map' );
		$property->setAccessible( true );
		$property->setValue( null, $this->original_gateway_map );

		delete_transient( WC_Payments_PM_Promotions_Service::PROMOTIONS_CACHE_KEY );
		delete_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION );
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
				'payment_method' => Payment_Method::KLARNA,
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

	/**
	 * Helper to set up the promotions cache with given promotions.
	 *
	 * @param array $promotions Array of promotions to cache.
	 */
	private function set_promotions_cache( array $promotions ): void {
		// Generate the context hash to match what the service will generate.
		$store_context = [
			'dismissals' => $this->service->get_promotion_dismissals(),
			'locale'     => get_locale(),
		];
		$context_hash  = md5(
			wp_json_encode(
				[
					'dismissals' => $store_context['dismissals'],
					'locale'     => $store_context['locale'],
				]
			)
		);

		set_transient(
			WC_Payments_PM_Promotions_Service::PROMOTIONS_CACHE_KEY,
			[
				'promotions'   => $promotions,
				'context_hash' => $context_hash,
				'timestamp'    => time(),
			],
			DAY_IN_SECONDS
		);
	}

	/**
	 * Helper to set up a mock payment gateway in WC_Payments for testing.
	 *
	 * @param string          $payment_method_id The payment method ID (e.g., 'klarna').
	 * @param MockObject|null $gateway_mock      Optional gateway mock. Creates one if not provided.
	 * @param bool            $enabled           Whether the gateway should be mocked as enabled. Default true.
	 */
	private function set_payment_gateway_for_testing( string $payment_method_id, $gateway_mock = null, bool $enabled = true ): void {
		if ( null === $gateway_mock ) {
			$gateway_mock = $this->createMock( WC_Payment_Gateway_WCPay::class );
			$gateway_mock->method( 'enable' )->willReturn( true );
			$gateway_mock->method( 'get_option' )
				->willReturnCallback(
					function ( $key ) use ( $enabled ) {
						return 'enabled' === $key ? ( $enabled ? 'yes' : 'no' ) : '';
					}
				);
			$gateway_mock->method( 'get_option_key' )->willReturn( 'woocommerce_woocommerce_payments_' . $payment_method_id . '_settings' );
			$gateway_mock->method( 'get_payment_method_capability_key_map' )->willReturn( [] );
			$gateway_mock->method( 'get_upe_enabled_payment_method_ids' )->willReturn( [] );
			$gateway_mock->method( 'update_option' )->willReturn( true );
		}

		// Use reflection to access the private static property.
		$reflection = new ReflectionClass( WC_Payments::class );
		$property   = $reflection->getProperty( 'payment_gateway_map' );
		$property->setAccessible( true );

		$gateway_map                       = $property->getValue();
		$gateway_map[ $payment_method_id ] = $gateway_mock;
		$property->setValue( null, $gateway_map );
	}

	/**
	 * Helper to clean up the WC_Payments gateway map after testing.
	 *
	 * @param string $payment_method_id The payment method ID to remove.
	 */
	private function clear_payment_gateway_for_testing( string $payment_method_id ): void {
		$reflection = new ReflectionClass( WC_Payments::class );
		$property   = $reflection->getProperty( 'payment_gateway_map' );
		$property->setAccessible( true );

		$gateway_map = $property->getValue();
		unset( $gateway_map[ $payment_method_id ] );
		$property->setValue( null, $gateway_map );
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
			'payment_method' => Payment_Method::KLARNA,
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
			->willReturn( [ Payment_Method::KLARNA ] ); // Klarna is enabled.

		$promotions = [
			$this->create_valid_promotion( [ 'payment_method' => Payment_Method::KLARNA ] ),
		];

		$result = $this->invoke_private_method( 'filter_promotions', [ $promotions ] );

		$this->assertEmpty( $result );
	}

	public function test_filter_promotions_keeps_not_enabled_pm() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [ Payment_Method::CARD ] ); // Only card is enabled.

		$promotions = [
			$this->create_valid_promotion( [ 'payment_method' => Payment_Method::KLARNA ] ),
		];

		$result = $this->invoke_private_method( 'filter_promotions', [ $promotions ] );

		$this->assertCount( 1, $result );
		$this->assertSame( Payment_Method::KLARNA, $result[0]['payment_method'] );
	}

	public function test_filter_promotions_keeps_first_promo_id_per_pm() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		$promotions = [
			$this->create_valid_promotion(
				[
					'id'             => 'first-promo__spotlight',
					'promo_id'       => 'first-promo',
					'payment_method' => Payment_Method::KLARNA,
				]
			),
			$this->create_valid_promotion(
				[
					'id'             => 'second-promo__spotlight',
					'promo_id'       => 'second-promo',
					'payment_method' => Payment_Method::KLARNA,
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
					'payment_method' => Payment_Method::KLARNA,
					'type'           => 'spotlight',
				]
			),
			$this->create_valid_promotion(
				[
					'id'             => 'promo__badge',
					'promo_id'       => 'promo',
					'payment_method' => Payment_Method::KLARNA,
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
					'payment_method' => Payment_Method::KLARNA,
				]
			),
			$this->create_valid_promotion(
				[
					'id'             => 'promo__affirm',
					'promo_id'       => 'promo',
					'payment_method' => Payment_Method::AFFIRM,
				]
			),
		];

		$result = $this->invoke_private_method( 'filter_promotions', [ $promotions ] );

		$this->assertCount( 2, $result );
	}

	public function test_filter_promotions_removes_pm_with_active_discount() {
		// Create a mock account that returns fees with a discount for klarna.
		$mock_account = $this->createMock( WC_Payments_Account::class );
		$mock_account->method( 'get_fees' )
			->willReturn(
				[
					'klarna' => [
						'base'     => [
							'percentage_rate' => 0.029,
							'fixed_rate'      => 30,
						],
						'discount' => [
							[
								'discount'        => 50,
								'end_time'        => strtotime( '+30 days' ),
								'volume_currency' => 'usd',
							],
						],
					],
				]
			);

		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		// Create service with mock account.
		$service = new WC_Payments_PM_Promotions_Service( $this->mock_gateway, $mock_account );

		$promotions = [
			$this->create_valid_promotion(
				[
					'id'             => 'promo__klarna',
					'promo_id'       => 'promo',
					'payment_method' => Payment_Method::KLARNA,
				]
			),
			$this->create_valid_promotion(
				[
					'id'             => 'promo__affirm',
					'promo_id'       => 'promo',
					'payment_method' => Payment_Method::AFFIRM,
				]
			),
		];

		$reflection = new ReflectionClass( $service );
		$method     = $reflection->getMethod( 'filter_promotions' );
		$method->setAccessible( true );

		$result = $method->invokeArgs( $service, [ $promotions ] );

		// Only affirm promotion should remain (klarna has active discount).
		$this->assertCount( 1, $result );
		$this->assertSame( Payment_Method::AFFIRM, $result[0]['payment_method'] );
	}

	public function test_filter_promotions_keeps_pm_without_discount() {
		// Create a mock account that returns fees without discounts.
		$mock_account = $this->createMock( WC_Payments_Account::class );
		$mock_account->method( 'get_fees' )
			->willReturn(
				[
					Payment_Method::KLARNA => [
						'base'     => [
							'percentage_rate' => 0.029,
							'fixed_rate'      => 30,
						],
						'discount' => [], // Empty discount array.
					],
					Payment_Method::AFFIRM => [
						'base' => [
							'percentage_rate' => 0.029,
							'fixed_rate'      => 30,
						],
						// No discount key at all.
					],
				]
			);

		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		// Create service with mock account.
		$service = new WC_Payments_PM_Promotions_Service( $this->mock_gateway, $mock_account );

		$promotions = [
			$this->create_valid_promotion(
				[
					'id'             => 'promo__klarna',
					'promo_id'       => 'promo',
					'payment_method' => Payment_Method::KLARNA,
				]
			),
			$this->create_valid_promotion(
				[
					'id'             => 'promo__affirm',
					'promo_id'       => 'promo',
					'payment_method' => Payment_Method::AFFIRM,
				]
			),
		];

		$reflection = new ReflectionClass( $service );
		$method     = $reflection->getMethod( 'filter_promotions' );
		$method->setAccessible( true );

		$result = $method->invokeArgs( $service, [ $promotions ] );

		// Both promotions should remain (neither has active discount).
		$this->assertCount( 2, $result );
	}

	/*
	 * =========================================================================
	 * NORMALIZATION TESTS
	 * =========================================================================
	 */

	public function test_normalize_promotions_adds_payment_method_title() {
		$promotions = [
			$this->create_valid_promotion( [ 'payment_method' => Payment_Method::KLARNA ] ),
		];

		$result = $this->invoke_private_method( 'normalize_promotions', [ $promotions ] );

		$this->assertArrayHasKey( 'payment_method_title', $result[0] );
		// Fallback should capitalize the PM ID if no payment method object found.
		$this->assertNotEmpty( $result[0]['payment_method_title'] );
	}

	public function test_normalize_promotions_keeps_existing_payment_method_title() {
		$promotions = [
			$this->create_valid_promotion(
				[
					'payment_method'       => Payment_Method::KLARNA,
					'payment_method_title' => 'Custom Klarna Title',
				]
			),
		];

		$result = $this->invoke_private_method( 'normalize_promotions', [ $promotions ] );

		$this->assertSame( 'Custom Klarna Title', $result[0]['payment_method_title'] );
	}

	public function test_normalize_promotions_applies_cta_label_fallback() {
		$promotion = $this->create_valid_promotion( [ 'payment_method' => Payment_Method::KLARNA ] );
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

	public function test_normalize_promotions_skips_tc_label_fallback_when_tc_url_in_description() {
		$tc_url     = 'https://example.com/terms';
		$promotions = [
			$this->create_valid_promotion(
				[
					'tc_url'      => $tc_url,
					'description' => 'Get 50% off! <a href="' . $tc_url . '">See terms</a>.',
				]
			),
		];
		// Remove tc_label to test fallback behavior.
		unset( $promotions[0]['tc_label'] );

		$result = $this->invoke_private_method( 'normalize_promotions', [ $promotions ] );

		// tc_label should remain empty when tc_url is already in description.
		$this->assertArrayHasKey( 'tc_label', $result[0] );
		$this->assertEmpty( $result[0]['tc_label'] );
	}

	public function test_normalize_promotions_applies_tc_label_fallback_when_tc_url_not_in_description() {
		$promotions = [
			$this->create_valid_promotion(
				[
					'tc_url'      => 'https://example.com/terms',
					'description' => 'Get 50% off on processing fees.',
				]
			),
		];
		// Remove tc_label to test fallback behavior.
		unset( $promotions[0]['tc_label'] );

		$result = $this->invoke_private_method( 'normalize_promotions', [ $promotions ] );

		// tc_label should get fallback when tc_url is not in description.
		$this->assertArrayHasKey( 'tc_label', $result[0] );
		$this->assertNotEmpty( $result[0]['tc_label'] );
		$this->assertSame( 'See terms', $result[0]['tc_label'] );
	}

	public function test_normalize_promotions_sanitizes_title_strips_all_html() {
		$promotions = [
			$this->create_valid_promotion(
				[
					'title' => '<script>alert("xss")</script>Test Title',
				]
			),
		];

		$result = $this->invoke_private_method( 'normalize_promotions', [ $promotions ] );

		// Title should strip all HTML.
		$this->assertStringNotContainsString( '<script>', $result[0]['title'] );
		$this->assertStringContainsString( 'Test Title', $result[0]['title'] );
	}

	public function test_normalize_promotions_sanitizes_key_fields_with_sanitize_key() {
		$promotions = [
			$this->create_valid_promotion(
				[
					'id'             => 'Test-Promo__Spotlight',
					'promo_id'       => 'Test-Promo',
					'payment_method' => 'Klarna',
					'type'           => 'Spotlight',
				]
			),
		];

		$result = $this->invoke_private_method( 'normalize_promotions', [ $promotions ] );

		// sanitize_key converts to lowercase and removes special chars.
		$this->assertSame( 'test-promo__spotlight', $result[0]['id'] );
		$this->assertSame( 'test-promo', $result[0]['promo_id'] );
		$this->assertSame( 'klarna', $result[0]['payment_method'] );
		$this->assertSame( 'spotlight', $result[0]['type'] );
	}

	public function test_normalize_promotions_spotlight_description_allows_light_html() {
		$promotions = [
			$this->create_valid_promotion(
				[
					'type'        => 'spotlight',
					'description' => '<p>Paragraph with <strong>bold</strong>, <em>italic</em>, and <a href="https://example.com">link</a>.</p><br>',
				]
			),
		];

		$result = $this->invoke_private_method( 'normalize_promotions', [ $promotions ] );

		// Spotlight allows p, strong, b, em, i, a, br tags.
		$this->assertStringContainsString( '<p>', $result[0]['description'] );
		$this->assertStringContainsString( '<strong>', $result[0]['description'] );
		$this->assertStringContainsString( '<em>', $result[0]['description'] );
		$this->assertStringContainsString( '<a href="https://example.com">', $result[0]['description'] );
		$this->assertStringContainsString( '<br', $result[0]['description'] );
	}

	public function test_normalize_promotions_spotlight_description_strips_disallowed_html() {
		$promotions = [
			$this->create_valid_promotion(
				[
					'type'        => 'spotlight',
					'description' => '<script>alert("xss")</script><div>Content</div><span>Text</span>',
				]
			),
		];

		$result = $this->invoke_private_method( 'normalize_promotions', [ $promotions ] );

		// Script, div, span should be stripped.
		$this->assertStringNotContainsString( '<script>', $result[0]['description'] );
		$this->assertStringNotContainsString( '<div>', $result[0]['description'] );
		$this->assertStringNotContainsString( '<span>', $result[0]['description'] );
		$this->assertStringContainsString( 'Content', $result[0]['description'] );
		$this->assertStringContainsString( 'Text', $result[0]['description'] );
	}

	public function test_normalize_promotions_badge_description_only_allows_links() {
		$promotions = [
			$this->create_valid_promotion(
				[
					'type'        => 'badge',
					'description' => '<p>Paragraph</p> <strong>bold</strong> <a href="https://example.com">link</a>',
				]
			),
		];

		$result = $this->invoke_private_method( 'normalize_promotions', [ $promotions ] );

		// Badge only allows a tags.
		$this->assertStringNotContainsString( '<p>', $result[0]['description'] );
		$this->assertStringNotContainsString( '<strong>', $result[0]['description'] );
		$this->assertStringContainsString( '<a href="https://example.com">', $result[0]['description'] );
		$this->assertStringContainsString( 'Paragraph', $result[0]['description'] );
		$this->assertStringContainsString( 'bold', $result[0]['description'] );
	}

	public function test_normalize_promotions_sanitizes_url_fields() {
		$promotions = [
			$this->create_valid_promotion(
				[
					'tc_url' => 'https://example.com/terms?param=value',
					'image'  => 'https://example.com/image.png',
				]
			),
		];

		$result = $this->invoke_private_method( 'normalize_promotions', [ $promotions ] );

		$this->assertSame( 'https://example.com/terms?param=value', $result[0]['tc_url'] );
		$this->assertSame( 'https://example.com/image.png', $result[0]['image'] );
	}

	public function test_normalize_promotions_footnote_allows_light_html() {
		$promotions = [
			$this->create_valid_promotion(
				[
					'footnote' => 'Terms apply. <a href="https://example.com">See details</a>.',
				]
			),
		];

		$result = $this->invoke_private_method( 'normalize_promotions', [ $promotions ] );

		// Footnote should allow light HTML like spotlight description.
		$this->assertStringContainsString( '<a href="https://example.com">', $result[0]['footnote'] );
	}

	/*
	 * =========================================================================
	 * ACTION TESTS - DISMISS
	 * =========================================================================
	 */

	public function test_dismiss_promotion_stores_dismissal_with_id() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		$id = 'test-promo__spotlight';

		// Set up cache with a promotion that has this ID.
		$this->set_promotions_cache( [ $this->create_valid_promotion( [ 'id' => $id ] ) ] );

		$this->service->dismiss_promotion( $id );

		$dismissals = $this->service->get_promotion_dismissals();
		$this->assertArrayHasKey( $id, $dismissals );
		$this->assertIsInt( $dismissals[ $id ] );
	}

	public function test_dismiss_promotion_returns_true_on_success() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		$id = 'test-promo__spotlight';

		// Set up cache with a promotion that has this ID.
		$this->set_promotions_cache( [ $this->create_valid_promotion( [ 'id' => $id ] ) ] );

		$result = $this->service->dismiss_promotion( $id );

		$this->assertTrue( $result );
	}

	public function test_dismiss_promotion_returns_false_when_already_dismissed() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		$id = 'test-promo__spotlight';

		// Set up cache with a promotion that has this ID.
		$this->set_promotions_cache( [ $this->create_valid_promotion( [ 'id' => $id ] ) ] );

		// First dismissal should succeed.
		$first_result = $this->service->dismiss_promotion( $id );
		$this->assertTrue( $first_result );

		// Reset memo and re-set cache with updated dismissals context.
		$this->service->reset_memo();
		$this->set_promotions_cache( [ $this->create_valid_promotion( [ 'id' => $id ] ) ] );

		// Second dismissal should return false (already dismissed).
		$second_result = $this->service->dismiss_promotion( $id );
		$this->assertFalse( $second_result );
	}

	public function test_dismiss_promotion_excludes_dismissed_from_visible_promotions() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		$dismissed_id = 'test-promo__spotlight';
		$other_id     = 'other-promo__badge';

		$promotions = [
			$this->create_valid_promotion( [ 'id' => $dismissed_id ] ),
			$this->create_valid_promotion(
				[
					'id'       => $other_id,
					'promo_id' => 'other-promo',
					'type'     => 'badge',
				]
			),
		];

		// Set up cache with multiple promotions.
		$this->set_promotions_cache( $promotions );

		// First call - warm the cache.
		$promotions_before = $this->service->get_visible_promotions();
		$this->service->reset_memo();

		// Verify the promotion is present before dismissal.
		$ids_before = array_column( $promotions_before, 'id' );
		$this->assertContains( $dismissed_id, $ids_before, 'Promotion should be visible before dismissal' );

		// Dismiss the promotion.
		$this->service->dismiss_promotion( $dismissed_id );
		$this->service->reset_memo();

		// Re-set cache with updated context hash (includes dismissal).
		$this->set_promotions_cache( $promotions );

		// Fetch promotions again.
		$promotions_after = $this->service->get_visible_promotions();

		// Assert the dismissed promotion is no longer in the results.
		$ids_after = array_column( $promotions_after, 'id' );
		$this->assertNotContains( $dismissed_id, $ids_after, 'Dismissed promotion should not be visible' );
		$this->assertContains( $other_id, $ids_after, 'Non-dismissed promotion should still be visible' );
	}

	public function test_dismiss_promotion_allows_multiple_dismissals() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		// Set up cache with multiple promotions.
		$promotions = [
			$this->create_valid_promotion(
				[
					'id'       => 'promo1__spotlight',
					'promo_id' => 'promo1',
					'type'     => 'spotlight',
				]
			),
			$this->create_valid_promotion(
				[
					'id'       => 'promo1__badge',
					'promo_id' => 'promo1',
					'type'     => 'badge',
				]
			),
		];
		$this->set_promotions_cache( $promotions );

		$this->service->dismiss_promotion( 'promo1__spotlight' );

		// Reset memo and re-set cache with updated dismissals context.
		$this->service->reset_memo();
		$this->set_promotions_cache( $promotions );

		$this->service->dismiss_promotion( 'promo1__badge' );

		$dismissals = $this->service->get_promotion_dismissals();

		$this->assertCount( 2, $dismissals );
		$this->assertArrayHasKey( 'promo1__spotlight', $dismissals );
		$this->assertArrayHasKey( 'promo1__badge', $dismissals );
	}

	/*
	 * =========================================================================
	 * ACTION TESTS - ACTIVATE
	 * =========================================================================
	 */

	public function test_activate_promotion_returns_false_for_invalid_id() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		// Set up cache with a valid promotion.
		$this->set_promotions_cache( [ $this->create_valid_promotion() ] );

		$result = $this->service->activate_promotion( 'non-existent-promo' );

		$this->assertFalse( $result );
	}

	public function test_activate_promotion_returns_false_when_promotion_not_visible() {
		// When the PM is enabled, the promotion is not visible.
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [ Payment_Method::KLARNA ] );

		// Set up cache with a klarna promotion.
		$this->set_promotions_cache( [ $this->create_valid_promotion() ] );

		$result = $this->service->activate_promotion( 'test-promo__spotlight' );

		$this->assertFalse( $result );
	}

	public function test_activate_promotion_returns_true_for_valid_promotion() {
		// Promotion is visible (PM not enabled) and gateway is available.
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		// Set up cache with a valid promotion.
		$this->set_promotions_cache( [ $this->create_valid_promotion() ] );

		// Mock the API request to return success.
		$this->mock_wcpay_request( Activate_PM_Promotion::class, 1, 'test-promo__spotlight', [] );

		// Set up the payment gateway in WC_Payments so enable_payment_method_gateway can find it.
		$this->set_payment_gateway_for_testing( Payment_Method::KLARNA );

		$result = $this->service->activate_promotion( 'test-promo__spotlight' );

		// Clean up the gateway.
		$this->clear_payment_gateway_for_testing( Payment_Method::KLARNA );

		$this->assertTrue( $result );
	}

	public function test_activate_promotion_clears_cache() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		// Set up cache with a valid promotion.
		$this->set_promotions_cache( [ $this->create_valid_promotion() ] );

		// Mock the API request to return success.
		$this->mock_wcpay_request( Activate_PM_Promotion::class, 1, 'test-promo__spotlight', [] );

		// Set up the payment gateway in WC_Payments so enable_payment_method_gateway can find it.
		$this->set_payment_gateway_for_testing( Payment_Method::KLARNA );

		$this->service->activate_promotion( 'test-promo__spotlight' );

		// Clean up the gateway.
		$this->clear_payment_gateway_for_testing( Payment_Method::KLARNA );

		$this->assertFalse( get_transient( WC_Payments_PM_Promotions_Service::PROMOTIONS_CACHE_KEY ) );
	}

	public function test_activate_promotion_marks_promotion_as_dismissed() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		// Set up cache with a valid promotion.
		$this->set_promotions_cache( [ $this->create_valid_promotion() ] );

		// Mock the API request to return success.
		$this->mock_wcpay_request( Activate_PM_Promotion::class, 1, 'test-promo__spotlight', [] );

		// Set up the payment gateway in WC_Payments so enable_payment_method_gateway can find it.
		$this->set_payment_gateway_for_testing( Payment_Method::KLARNA );

		$this->service->activate_promotion( 'test-promo__spotlight' );

		// Clean up the gateway.
		$this->clear_payment_gateway_for_testing( Payment_Method::KLARNA );

		// Verify the promotion is marked as dismissed.
		$this->assertTrue( $this->service->is_promotion_dismissed( 'test-promo__spotlight' ) );
	}

	public function test_activate_promotion_dismisses_even_when_gateway_enablement_fails() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		// Set up cache with a valid promotion.
		$this->set_promotions_cache( [ $this->create_valid_promotion() ] );

		// Mock the API request to return success.
		$this->mock_wcpay_request( Activate_PM_Promotion::class, 1, 'test-promo__spotlight', [] );

		// Set up a gateway that fails to enable (get_option returns 'no' even after enable()).
		$gateway_mock = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$gateway_mock->method( 'enable' )->willReturn( true );
		$gateway_mock->method( 'get_option' )
			->willReturnCallback(
				function ( $key ) {
					// Always return 'no' for enabled, simulating gateway enablement failure.
					return 'enabled' === $key ? 'no' : '';
				}
			);
		$gateway_mock->method( 'get_option_key' )->willReturn( 'woocommerce_woocommerce_payments_klarna_settings' );
		$gateway_mock->method( 'get_payment_method_capability_key_map' )->willReturn( [] );
		$gateway_mock->method( 'get_upe_enabled_payment_method_ids' )->willReturn( [] );

		$this->set_payment_gateway_for_testing( Payment_Method::KLARNA, $gateway_mock );

		$result = $this->service->activate_promotion( 'test-promo__spotlight' );

		// Clean up the gateway.
		$this->clear_payment_gateway_for_testing( Payment_Method::KLARNA );

		// Activation should return false due to gateway enablement failure.
		$this->assertFalse( $result );

		// But the promotion should still be marked as dismissed.
		$this->assertTrue( $this->service->is_promotion_dismissed( 'test-promo__spotlight' ) );
	}

	// Note: Testing API error handling for activate_promotion is not straightforward
	// because the Request class uses final methods (send, handle_rest_request) that
	// cannot be mocked. The code is designed to NOT dismiss the promotion when server
	// activation fails (see handle_promotion_activation_failure), but this cannot be
	// directly unit tested. The dismissal happening BEFORE gateway enablement ensures
	// that dismissal occurs even if gateway enablement fails (tested above), while
	// server failures prevent dismissal entirely by returning early.

	/*
	 * =========================================================================
	 * ACTION TESTS - MAYBE_ACTIVATE_PROMOTION_FOR_PAYMENT_METHOD
	 * =========================================================================
	 */

	public function test_maybe_activate_promotion_returns_false_when_no_promotion_for_pm() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		// Set up cache with a promotion for klarna.
		$this->set_promotions_cache( [ $this->create_valid_promotion( [ 'payment_method' => Payment_Method::KLARNA ] ) ] );

		// Try to activate for affirm (no promotion exists for this PM).
		$result = $this->service->maybe_activate_promotion_for_payment_method( Payment_Method::AFFIRM );

		$this->assertFalse( $result );
	}

	public function test_maybe_activate_promotion_returns_true_on_success() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		// Set up cache with a valid promotion.
		$this->set_promotions_cache( [ $this->create_valid_promotion() ] );

		// Mock the API request to return success.
		$this->mock_wcpay_request( Activate_PM_Promotion::class, 1, 'test-promo__spotlight', [] );

		$result = $this->service->maybe_activate_promotion_for_payment_method( Payment_Method::KLARNA );

		$this->assertTrue( $result );
	}

	// Note: Testing API error handling for maybe_activate_promotion_for_payment_method is not
	// straightforward because the Request class uses final methods (send, handle_rest_request)
	// that cannot be mocked directly. Mocking format_response() doesn't work because send()
	// calls api_client->send_request() first, which fails with null api_client when using
	// disableOriginalConstructor(). The error handling behavior is implicitly covered by
	// testing the "promotion not found" path which returns false.

	public function test_maybe_activate_promotion_enables_gateway_when_should_enable_is_true() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		// Set up cache with a valid promotion.
		$this->set_promotions_cache( [ $this->create_valid_promotion() ] );

		// Mock the API request to return success.
		$this->mock_wcpay_request( Activate_PM_Promotion::class, 1, 'test-promo__spotlight', [] );

		// Track whether enable() has been called to simulate state change.
		$enabled = false;

		// Set up the payment gateway with expectation that enable will be called.
		$gateway_mock = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$gateway_mock->expects( $this->once() )->method( 'enable' )->willReturnCallback(
			function () use ( &$enabled ) {
				$enabled = true;
				return true;
			}
		);
		$gateway_mock->method( 'get_option' )->willReturnCallback(
			function ( $key ) use ( &$enabled ) {
				// After enable() is called, return 'yes' for enabled check.
				return 'enabled' === $key ? ( $enabled ? 'yes' : 'no' ) : '';
			}
		);
		$gateway_mock->method( 'get_option_key' )->willReturn( 'woocommerce_woocommerce_payments_klarna_settings' );
		$gateway_mock->method( 'get_payment_method_capability_key_map' )->willReturn( [] );
		$gateway_mock->method( 'get_upe_enabled_payment_method_ids' )->willReturn( [] );
		$gateway_mock->method( 'update_option' )->willReturn( true );

		$this->set_payment_gateway_for_testing( Payment_Method::KLARNA, $gateway_mock );

		$result = $this->service->maybe_activate_promotion_for_payment_method( Payment_Method::KLARNA, true );

		// Clean up the gateway.
		$this->clear_payment_gateway_for_testing( Payment_Method::KLARNA );

		$this->assertTrue( $result );
	}

	public function test_maybe_activate_promotion_does_not_enable_gateway_when_should_enable_is_false() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		// Set up cache with a valid promotion.
		$this->set_promotions_cache( [ $this->create_valid_promotion() ] );

		// Mock the API request to return success.
		$this->mock_wcpay_request( Activate_PM_Promotion::class, 1, 'test-promo__spotlight', [] );

		// Set up the payment gateway with expectation that enable will NOT be called.
		$gateway_mock = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$gateway_mock->expects( $this->never() )->method( 'enable' );
		$gateway_mock->method( 'get_option' )->willReturnCallback(
			function ( $key ) {
				return 'enabled' === $key ? 'no' : '';
			}
		);
		$gateway_mock->method( 'get_option_key' )->willReturn( 'woocommerce_woocommerce_payments_klarna_settings' );
		$gateway_mock->method( 'get_payment_method_capability_key_map' )->willReturn( [] );
		$gateway_mock->method( 'get_upe_enabled_payment_method_ids' )->willReturn( [] );

		$this->set_payment_gateway_for_testing( Payment_Method::KLARNA, $gateway_mock );

		$result = $this->service->maybe_activate_promotion_for_payment_method( Payment_Method::KLARNA, false );

		// Clean up the gateway.
		$this->clear_payment_gateway_for_testing( Payment_Method::KLARNA );

		$this->assertTrue( $result );
	}

	/*
	 * =========================================================================
	 * INTEGRATION TESTS - get_visible_promotions()
	 * =========================================================================
	 */

	public function test_get_visible_promotions_returns_null_for_user_without_manage_woocommerce() {
		// Create a subscriber user (doesn't have manage_woocommerce capability).
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		$result = $this->service->get_visible_promotions();

		$this->assertNull( $result );
	}

	public function test_get_visible_promotions_returns_null_for_guest_user() {
		wp_set_current_user( 0 );

		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		$result = $this->service->get_visible_promotions();

		$this->assertNull( $result );
	}

	public function test_get_visible_promotions_returns_null_when_no_promotions() {
		// The mock data has promotions, but they should all be filtered out
		// when klarna and affirm are enabled.
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [ Payment_Method::KLARNA, Payment_Method::AFFIRM ] );

		$result = $this->service->get_visible_promotions();

		$this->assertNull( $result );
	}

	public function test_get_visible_promotions_filters_and_normalizes() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] ); // No PMs enabled, promotions should show.

		// Set up cache with valid promotions.
		$this->set_promotions_cache(
			[
				$this->create_valid_promotion(),
				$this->create_valid_promotion(
					[
						'id'             => 'affirm-promo__badge',
						'promo_id'       => 'affirm-promo',
						'payment_method' => Payment_Method::AFFIRM,
						'type'           => 'badge',
					]
				),
			]
		);

		$result = $this->service->get_visible_promotions();

		// Should have promotions from cache.
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

		// Set up cache with valid promotions.
		$this->set_promotions_cache(
			[
				$this->create_valid_promotion(),
				$this->create_valid_promotion(
					[
						'id'             => 'affirm-promo__badge',
						'promo_id'       => 'affirm-promo',
						'payment_method' => Payment_Method::AFFIRM,
						'type'           => 'badge',
					]
				),
			]
		);

		$result = $this->service->get_visible_promotions();

		// Should be a sequential array, not associative.
		$this->assertSame( array_values( $result ), $result );
	}

	public function test_get_visible_promotions_excludes_dismissed_promotions() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		// Set up cache with multiple promotions.
		$promotions = [
			$this->create_valid_promotion(
				[
					'id'       => 'promo1__spotlight',
					'promo_id' => 'promo1',
				]
			),
			$this->create_valid_promotion(
				[
					'id'             => 'promo2__spotlight',
					'promo_id'       => 'promo2',
					'payment_method' => Payment_Method::AFFIRM,
				]
			),
		];
		$this->set_promotions_cache( $promotions );

		// Dismiss the first promotion.
		$this->service->dismiss_promotion( 'promo1__spotlight' );

		// Need to reset memo and re-set cache with updated dismissals context.
		$this->service->reset_memo();
		$this->set_promotions_cache( $promotions );

		$result = $this->service->get_visible_promotions();

		// The dismissed promotion should not be in the results.
		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertSame( 'promo2__spotlight', $result[0]['id'] );
	}

	public function test_get_visible_promotions_returns_null_when_all_dismissed() {
		$this->mock_gateway->method( 'get_upe_enabled_payment_method_ids' )
			->willReturn( [] );

		// Set up cache with promotions.
		$promotions = [
			$this->create_valid_promotion(
				[
					'id'       => 'promo1__spotlight',
					'promo_id' => 'promo1',
				]
			),
		];
		$this->set_promotions_cache( $promotions );

		// Dismiss all promotions.
		$this->service->dismiss_promotion( 'promo1__spotlight' );

		// Need to reset memo and re-set cache with updated dismissals context.
		$this->service->reset_memo();
		$this->set_promotions_cache( $promotions );

		$result = $this->service->get_visible_promotions();

		$this->assertNull( $result );
	}

	/*
	 * =========================================================================
	 * DISMISSAL HELPER TESTS
	 * =========================================================================
	 */

	public function test_get_promotion_dismissals_returns_stored_dismissals() {
		// New flat structure: [id => timestamp].
		$dismissals = [
			'promo1__spotlight' => 1234567890,
			'promo2__spotlight' => 1234567900,
		];
		update_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION, $dismissals );

		$result = $this->service->get_promotion_dismissals();

		$this->assertSame( $dismissals, $result );
	}

	public function test_is_promotion_dismissed_returns_true_for_past_timestamp() {
		$dismissals = [
			'promo1__spotlight' => time() - 3600, // 1 hour ago.
			'promo1__badge'     => time() - 1,    // 1 second ago.
		];
		update_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION, $dismissals );

		$this->assertTrue( $this->service->is_promotion_dismissed( 'promo1__spotlight' ) );
		$this->assertTrue( $this->service->is_promotion_dismissed( 'promo1__badge' ) );
	}

	public function test_is_promotion_dismissed_returns_false_for_non_existent() {
		$dismissals = [
			'promo1__spotlight' => time() - 3600,
		];
		update_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION, $dismissals );

		$this->assertFalse( $this->service->is_promotion_dismissed( 'promo2__spotlight' ) );
	}

	public function test_is_promotion_dismissed_returns_false_for_future_timestamp() {
		$dismissals = [
			'promo1__spotlight' => time() + 3600, // 1 hour from now.
		];
		update_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION, $dismissals );

		$this->assertFalse( $this->service->is_promotion_dismissed( 'promo1__spotlight' ) );
	}

	public function test_is_promotion_dismissed_returns_false_for_invalid_values() {
		$dismissals = [
			'promo1__spotlight' => 'invalid',
			'promo2__spotlight' => 0,
			'promo3__spotlight' => -1,
			'promo4__spotlight' => null,
		];
		update_option( WC_Payments_PM_Promotions_Service::PROMOTION_DISMISSALS_OPTION, $dismissals );

		$this->assertFalse( $this->service->is_promotion_dismissed( 'promo1__spotlight' ) );
		$this->assertFalse( $this->service->is_promotion_dismissed( 'promo2__spotlight' ) );
		$this->assertFalse( $this->service->is_promotion_dismissed( 'promo3__spotlight' ) );
		$this->assertFalse( $this->service->is_promotion_dismissed( 'promo4__spotlight' ) );
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
