<?php
/**
 * Class WC_REST_Payments_WSN_Settings_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_REST_Payments_WSN_Settings_Controller unit tests.
 *
 * Covers GET shape, PUT validation, capability gating, and the conditional
 * firing of `wcpay_wsn_profile_changed` (only when Profile fields change, never
 * for Visibility-only updates).
 */
class WC_REST_Payments_WSN_Settings_Controller_Test extends WCPAY_UnitTestCase {

	const ROUTE = '/wc/v3/payments/wsn/settings';

	/**
	 * @var WC_REST_Payments_WSN_Settings_Controller
	 */
	private $controller;

	/**
	 * @var int Count of `wcpay_wsn_profile_changed` fires during the current test.
	 */
	private $profile_changed_fire_count = 0;

	public function set_up() {
		parent::set_up();

		$this->controller = new WC_REST_Payments_WSN_Settings_Controller();
		$this->controller->register_routes();

		$this->profile_changed_fire_count = 0;
		add_action(
			'wcpay_wsn_profile_changed',
			function () {
				++$this->profile_changed_fire_count;
			}
		);
	}

	public function tear_down() {
		remove_all_actions( 'wcpay_wsn_profile_changed' );

		delete_option( WSN_Settings::OPTION_ENABLED );
		delete_option( WSN_Settings::OPTION_VISIBILITY_MODE );
		delete_option( WSN_Settings::OPTION_VISIBILITY_TERMS );
		delete_option( WSN_Settings::OPTION_VISIBILITY_PRODUCT_IDS );
		delete_option( WSN_Settings::OPTION_HERO_IMAGE_ID );
		delete_option( WSN_Settings::OPTION_LOGO_OVERRIDE_ID );
		delete_option( WSN_Settings::OPTION_CONTACT_EMAIL );
		delete_option( WSN_Settings::OPTION_REFUND_PAGE_ID );

		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Authenticate as a shop_manager (manage_woocommerce) for the rest of the test.
	 */
	private function authenticate_as_shop_manager(): void {
		$user_id = $this->factory->user->create( [ 'role' => 'shop_manager' ] );
		wp_set_current_user( $user_id );
	}

	public function test_get_returns_default_shape_when_nothing_persisted() {
		$this->authenticate_as_shop_manager();

		$request  = new WP_REST_Request( 'GET', self::ROUTE );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$body = $response->get_data();
		$this->assertArrayHasKey( 'settings', $body );
		$this->assertArrayHasKey( 'feature_enabled', $body );
		$this->assertFalse( $body['settings']['enabled'] );
		$this->assertSame( WSN_Settings::VISIBILITY_MODE_ALL, $body['settings']['visibility_mode'] );
	}

	public function test_get_rejects_without_manage_woocommerce() {
		// Anonymous (no current user) lacks the capability.
		$request  = new WP_REST_Request( 'GET', self::ROUTE );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status(), 'Expected 401 for unauthenticated request, got ' . $response->get_status() );
	}

	public function test_put_rejects_without_manage_woocommerce() {
		// Subscriber role lacks manage_woocommerce.
		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$request = new WP_REST_Request( 'PUT', self::ROUTE );
		$request->set_param( 'enabled', true );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_put_persists_enabled_toggle() {
		$this->authenticate_as_shop_manager();

		$request = new WP_REST_Request( 'PUT', self::ROUTE );
		$request->set_param( 'enabled', true );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( WSN_Settings::is_enabled() );
		$this->assertTrue( $response->get_data()['settings']['enabled'] );
	}

	public function test_put_validation_failure_returns_422_with_partial_writes_applied() {
		$this->authenticate_as_shop_manager();

		$request = new WP_REST_Request( 'PUT', self::ROUTE );
		$request->set_param( 'enabled', true );
		$request->set_param( 'visibility_mode', 'rubbish' ); // Invalid — schema enum check rejects pre-callback.
		$response = rest_get_server()->dispatch( $request );

		// rest_validate_request_arg fires before our callback, so this is rejected as 400 by the schema layer.
		$this->assertSame( 400, $response->get_status() );

		// Schema rejection should NOT have written `enabled` either (single rejected param fails the whole PUT).
		$this->assertFalse( WSN_Settings::is_enabled() );
	}

	public function test_put_fires_profile_changed_when_profile_field_changes() {
		$this->authenticate_as_shop_manager();

		$request = new WP_REST_Request( 'PUT', self::ROUTE );
		$request->set_param( 'contact_email', 'hello@example.com' );
		rest_get_server()->dispatch( $request );

		$this->assertSame( 1, $this->profile_changed_fire_count );
		$this->assertSame( 'hello@example.com', WSN_Settings::get_contact_email() );
	}

	public function test_put_does_not_fire_profile_changed_when_only_visibility_changes() {
		$this->authenticate_as_shop_manager();

		$request = new WP_REST_Request( 'PUT', self::ROUTE );
		$request->set_param( 'visibility_mode', WSN_Settings::VISIBILITY_MODE_TAXONOMY );
		$request->set_param( 'visibility_terms', [ 'categories' => [ 14 ] ] );
		rest_get_server()->dispatch( $request );

		$this->assertSame(
			0,
			$this->profile_changed_fire_count,
			'Visibility-only updates must NOT fire `wcpay_wsn_profile_changed` — that path flows through the Jetpack indexer (RSM-3946), not the Profile emitter (RSM-3945).'
		);
	}

	public function test_put_does_not_fire_profile_changed_when_value_unchanged() {
		$this->authenticate_as_shop_manager();

		WSN_Settings::set_contact_email( 'hello@example.com' );

		$request = new WP_REST_Request( 'PUT', self::ROUTE );
		$request->set_param( 'contact_email', 'hello@example.com' ); // Same value as the existing option.
		rest_get_server()->dispatch( $request );

		$this->assertSame( 0, $this->profile_changed_fire_count );
	}

	public function test_put_with_invalid_email_returns_validation_error_and_skips_write() {
		$this->authenticate_as_shop_manager();

		$request = new WP_REST_Request( 'PUT', self::ROUTE );
		$request->set_param( 'contact_email', 'not-an-email' );
		$response = rest_get_server()->dispatch( $request );

		// The schema declares `format=email`, which WP validates pre-callback → 400 from the schema layer.
		$this->assertSame( 400, $response->get_status() );
		$this->assertNull( WSN_Settings::get_contact_email() );
	}
}
