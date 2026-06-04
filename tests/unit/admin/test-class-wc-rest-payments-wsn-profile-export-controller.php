<?php
/**
 * Class WC_REST_Payments_WSN_Profile_Export_Controller_Test
 *
 * @package WooCommerce\Payments\Admin
 */

/**
 * Unit tests for the Profile-export REST controller (RSM-3945's read endpoint).
 *
 * Coverage focus:
 *
 *   - **Permission gate is Jetpack signature** — `manage_woocommerce` users
 *     who aren't signed by the blog token get rejected. WooPay-side
 *     callers signing with the blog token get through.
 *   - **Response shape matches the composer** — the endpoint and the
 *     POST emitter return the same canonical payload. Storefront-side
 *     code can write its projection once and serve both surfaces.
 *   - **Privacy invariant holds at this surface too** — the address
 *     canary test from the composer test must pass here also (the
 *     endpoint is just a thin wrapper, but adding the assertion here
 *     locks the wrapping in).
 */
class WC_REST_Payments_WSN_Profile_Export_Controller_Test extends WCPAY_UnitTestCase {

	const FIXTURE_STREET = 'EXPORT-SECRET-STREET-CANARY-9999';

	public function set_up() {
		parent::set_up();

		add_action( 'rest_api_init', [ $this, 'register_routes_for_test' ] );
		do_action( 'rest_api_init' );

		update_option( 'woocommerce_store_address', self::FIXTURE_STREET );
	}

	public function tear_down() {
		remove_action( 'rest_api_init', [ $this, 'register_routes_for_test' ] );
		remove_all_filters( 'wcpay_wsn_profile_export_is_signed_with_blog_token' );
		delete_option( 'woocommerce_store_address' );

		parent::tear_down();
	}

	public function register_routes_for_test() {
		$mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		( new WC_REST_Payments_WSN_Profile_Export_Controller( $mock_api_client ) )->register_routes();
	}

	public function test_get_rejects_request_without_blog_token_signature() {
		// Default Rest_Authentication::is_signed_with_blog_token() returns
		// false in unit tests (no signature on the synthetic REST request).
		// We do NOT override the filter — so the default-false path runs.
		$request  = new WP_REST_Request( 'GET', '/wc/v3/payments/wsn/profile-export' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertContains(
			$response->get_status(),
			[ 401, 403 ],
			'Unsigned request must be rejected at the permission_callback layer.'
		);
	}

	public function test_get_returns_200_when_blog_token_signature_is_valid() {
		add_filter( 'wcpay_wsn_profile_export_is_signed_with_blog_token', '__return_true' );

		$request  = new WP_REST_Request( 'GET', '/wc/v3/payments/wsn/profile-export' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_get_returns_canonical_payload_shape_when_authorized() {
		add_filter( 'wcpay_wsn_profile_export_is_signed_with_blog_token', '__return_true' );

		$request  = new WP_REST_Request( 'GET', '/wc/v3/payments/wsn/profile-export' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		// Top-level keys that the composer always emits — assertion against
		// canonical shape, not storefront shape.
		$this->assertArrayHasKey( 'schema_version', $data );
		$this->assertArrayHasKey( 'payload_version', $data );
		$this->assertArrayHasKey( 'client_updated_at', $data );
		$this->assertArrayHasKey( 'settings', $data );
		$this->assertArrayHasKey( 'derivations', $data );
		$this->assertArrayHasKey( 'location', $data );

		// Canonical names live in derivations.
		$this->assertArrayHasKey( 'tagline', $data['derivations'] );
		$this->assertArrayHasKey( 'refund_page_url', $data['derivations'] );
		$this->assertArrayHasKey( 'refund_page_label', $data['derivations'] );
		$this->assertArrayHasKey( 'free_shipping', $data['derivations'] );

		// Storefront-shape names must NOT appear (those are produced by
		// the WooPay-side handler at merge time, not by this endpoint).
		$this->assertArrayNotHasKey( 'description', $data['derivations'] );
		$this->assertArrayNotHasKey( 'shipping_promise', $data['derivations'] );
		$this->assertArrayNotHasKey( 'return_policy', $data['derivations'] );
	}

	public function test_get_payload_does_not_leak_stored_address_strings() {
		add_filter( 'wcpay_wsn_profile_export_is_signed_with_blog_token', '__return_true' );

		$request  = new WP_REST_Request( 'GET', '/wc/v3/payments/wsn/profile-export' );
		$response = rest_get_server()->dispatch( $request );
		$body     = wp_json_encode( $response->get_data() );

		$this->assertIsString( $body );
		$this->assertStringNotContainsString(
			self::FIXTURE_STREET,
			$body,
			'Serialized export response must not contain the stored street address. The composer enforces this via allowlist; the endpoint test locks the wrapper in.'
		);
	}

	public function test_check_permission_filter_is_namespaced_to_this_endpoint() {
		// The filter name is specific to this controller, not shared with
		// the WooPay session controller — this guards against a refactor
		// that consolidates the two filters and changes auth behavior on
		// one endpoint while only intending to touch the other.
		add_filter(
			'wcpay_wsn_profile_export_is_signed_with_blog_token',
			'__return_true'
		);
		add_filter(
			'wcpay_woopay_is_signed_with_blog_token',
			'__return_false'
		);

		$request  = new WP_REST_Request( 'GET', '/wc/v3/payments/wsn/profile-export' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame(
			200,
			$response->get_status(),
			'Our endpoint should auth on its own filter, not WooPay session controller filter.'
		);

		remove_filter( 'wcpay_woopay_is_signed_with_blog_token', '__return_false' );
	}
}
