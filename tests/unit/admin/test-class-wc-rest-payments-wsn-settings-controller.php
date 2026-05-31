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
	 * @var int Count of `wcpay_wsn_profile_changed` fires during the current test.
	 */
	private $profile_changed_fire_count = 0;

	public function set_up() {
		parent::set_up();

		// Routes MUST be registered on the `rest_api_init` action (WP enforces this
		// via _doing_it_wrong since 5.1). Hook the registration onto that action and
		// trigger it; rest_get_server() also triggers `rest_api_init` internally on
		// first call, so this both registers the route AND warms the server.
		// Inject a mock API client. The settings controller extends
		// WC_Payments_REST_Controller (whose constructor requires the client)
		// for shared base-class behavior but never actually calls the client —
		// the controller's whole flow is local wp_options reads/writes.
		$mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		add_action(
			'rest_api_init',
			function () use ( $mock_api_client ) {
				( new WC_REST_Payments_WSN_Settings_Controller( $mock_api_client ) )->register_routes();
			}
		);
		do_action( 'rest_api_init' );

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

	public function test_put_schema_enum_rejection_blocks_entire_request_with_400() {
		$this->authenticate_as_shop_manager();

		// Validation in this controller is two-tier: WP REST's `args` schema runs
		// `rest_validate_request_arg` BEFORE our callback. Fields with `enum` (like
		// visibility_mode) and `format` (like contact_email) are rejected at the
		// schema layer with a 400 — the callback never executes, so sibling fields
		// in the same request don't get written. The 422 partial-write path
		// (defined in update_settings()) is only reachable for setter-level
		// rejections; see test_put_setter_rejection_returns_422_with_partial_writes
		// below for that contract.
		$request = new WP_REST_Request( 'PUT', self::ROUTE );
		$request->set_param( 'enabled', true );
		$request->set_param( 'visibility_mode', 'rubbish' ); // Invalid enum value.
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		// `enabled` was NOT written because the whole request rejected at schema time.
		$this->assertFalse( WSN_Settings::is_enabled() );
	}

	public function test_put_setter_rejection_returns_422_with_partial_writes() {
		$this->authenticate_as_shop_manager();

		// visibility_product_ids has no schema-level cap; the cap is enforced inside
		// the setter (WSN_Settings::MAX_SPECIFIC_PRODUCT_IDS). When the setter
		// rejects, the callback returns 422 — sibling fields that succeeded ARE
		// persisted. This is the partial-write contract the controller docblock
		// promises and that the 422 branch in update_settings() implements.
		$over_cap = range( 1, WSN_Settings::MAX_SPECIFIC_PRODUCT_IDS + 1 );

		$request = new WP_REST_Request( 'PUT', self::ROUTE );
		$request->set_param( 'enabled', true );
		$request->set_param( 'visibility_product_ids', $over_cap );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 422, $response->get_status() );

		// The sibling field DID persist (partial-write contract).
		$this->assertTrue( WSN_Settings::is_enabled() );
		// The rejected field did NOT persist.
		$this->assertSame( [], WSN_Settings::get_visibility_product_ids() );
		// The handler returned a WP_Error which the REST server formats into a
		// WP_REST_Response body containing the standard {code, message, data}
		// shape — our 422-with-errors map lives at $data['data']['body']['errors'].
		$data = $response->get_data();
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'body', $data['data'] );
		$this->assertArrayHasKey( 'errors', $data['data']['body'] );
		$this->assertArrayHasKey( 'visibility_product_ids', $data['data']['body']['errors'] );
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

		// `contact_email` is intentionally a 3-state field (null / "" / email), so
		// the schema cannot declare `format=email` (it would reject ""). Validation
		// runs in the setter (WSN_Settings::set_contact_email → sanitize_email),
		// and a falsy setter return becomes a 422 with a per-field error map — the
		// same partial-write contract as test_put_setter_rejection_returns_422_*.
		$this->assertSame( 422, $response->get_status() );
		$this->assertNull( WSN_Settings::get_contact_email() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'body', $data['data'] );
		$this->assertArrayHasKey( 'errors', $data['data']['body'] );
		$this->assertArrayHasKey( 'contact_email', $data['data']['body']['errors'] );
	}
}
