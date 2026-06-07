<?php
/**
 * Class WC_REST_WooPay_WSN_Checkout_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Unit tests for WC_REST_WooPay_WSN_Checkout_Controller.
 *
 * Focused on the stamp_express_attribution() behaviour: the internal
 * Store-API preflight POST that carries extensions.woopay_wsn.channel
 * must fire with the correct body and must not leave the nonce-bypass
 * filter active after the call.
 */
class WC_REST_WooPay_WSN_Checkout_Controller_Test extends WCPAY_UnitTestCase {

	/**
	 * @var WC_REST_WooPay_WSN_Checkout_Controller
	 */
	private $controller;

	/**
	 * Request body captured by the rest_pre_dispatch hook.
	 *
	 * @var array|null
	 */
	private $captured_body = null;

	public function set_up() {
		parent::set_up();
		$this->controller    = new WC_REST_WooPay_WSN_Checkout_Controller();
		$this->captured_body = null;
	}

	public function tear_down() {
		parent::tear_down();
		// Ensure the nonce-bypass filter is never left active between tests.
		remove_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );
	}

	// ---- stamp_express_attribution: request body ----

	public function test_stamp_express_attribution_sends_wsn_express_channel() {
		$this->intercept_store_api_checkout_dispatch();

		$this->invoke_stamp_express_attribution();

		$this->assertNotNull( $this->captured_body, 'rest_do_request was not called for /wc/store/v1/checkout' );
		$this->assertSame(
			'wsn-express',
			$this->captured_body['extensions']['woopay_wsn']['channel'] ?? null,
			'extensions.woopay_wsn.channel must equal wsn-express'
		);
	}

	public function test_stamp_express_attribution_sends_preflight_flag() {
		$this->intercept_store_api_checkout_dispatch();

		$this->invoke_stamp_express_attribution();

		$payment_data = $this->captured_body['payment_data'] ?? [];
		$preflight    = array_filter(
			$payment_data,
			fn( $entry ) => ( $entry['key'] ?? '' ) === 'is-woopay-preflight-check'
		);

		$this->assertNotEmpty( $preflight, 'payment_data must include is-woopay-preflight-check entry' );
	}

	public function test_stamp_express_attribution_sends_woocommerce_payments_method() {
		$this->intercept_store_api_checkout_dispatch();

		$this->invoke_stamp_express_attribution();

		$this->assertSame(
			'woocommerce_payments',
			$this->captured_body['payment_method'] ?? null
		);
	}

	// ---- stamp_express_attribution: nonce bypass lifecycle ----

	public function test_stamp_express_attribution_removes_nonce_bypass_filter_after_call() {
		$this->intercept_store_api_checkout_dispatch();

		$this->invoke_stamp_express_attribution();

		$this->assertFalse(
			has_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' ),
			'woocommerce_store_api_disable_nonce_check must be removed after stamp_express_attribution()'
		);
	}

	public function test_stamp_express_attribution_nonce_bypass_active_during_dispatch() {
		$nonce_bypass_was_active = false;

		add_filter(
			'rest_pre_dispatch',
			function ( $result, $_server, $request ) use ( &$nonce_bypass_was_active ) {
				if ( false !== strpos( $request->get_route(), '/wc/store/v1/checkout' ) ) {
					$nonce_bypass_was_active = (bool) has_filter(
						'woocommerce_store_api_disable_nonce_check',
						'__return_true'
					);
					return new WP_REST_Response( [], 200 );
				}
				return $result;
			},
			10,
			3
		);

		$this->invoke_stamp_express_attribution();

		$this->assertTrue(
			$nonce_bypass_was_active,
			'woocommerce_store_api_disable_nonce_check must be active while rest_do_request runs'
		);
	}

	// ---- check_permission ----

	public function test_check_permission_returns_true_in_dev_mode() {
		WC_Payments::mode()->dev();
		$this->assertTrue( $this->controller->check_permission() );
		WC_Payments::mode()->live();
	}

	public function test_check_permission_returns_false_in_live_mode() {
		WC_Payments::mode()->live();
		$this->assertFalse( $this->controller->check_permission() );
	}

	// ---- handle_checkout: error paths ----

	public function test_handle_checkout_returns_400_for_empty_items() {
		$request = new WP_REST_Request( 'POST', '/wcpay/v1/woopay/wsn-checkout' );
		$request->set_param( 'items', [] );

		$result = $this->controller->handle_checkout( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wsn_checkout_no_items', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	// ---- stamp_express_attribution: exception safety ----

	public function test_stamp_express_attribution_removes_nonce_bypass_even_on_exception() {
		add_filter(
			'rest_pre_dispatch',
			function ( $result, $_server, WP_REST_Request $request ) {
				if ( false !== strpos( $request->get_route(), '/wc/store/v1/checkout' ) ) {
					throw new \Exception( 'Simulated dispatch failure' );
				}
				return $result;
			},
			10,
			3
		);

		try {
			$this->invoke_stamp_express_attribution();
		} catch ( \Exception $e ) {
			unset( $e ); // Expected — we only need the finally block to have run.
		}

		$this->assertFalse(
			has_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' ),
			'woocommerce_store_api_disable_nonce_check must be removed even when rest_do_request throws'
		);
	}

	// ---- helpers ----

	/**
	 * Register a rest_pre_dispatch hook that short-circuits
	 * /wc/store/v1/checkout dispatches (so no real WC checkout runs)
	 * and captures the request body params into $this->captured_body.
	 */
	private function intercept_store_api_checkout_dispatch(): void {
		add_filter(
			'rest_pre_dispatch',
			function ( $result, $_server, WP_REST_Request $request ) {
				if ( false !== strpos( $request->get_route(), '/wc/store/v1/checkout' ) ) {
					$this->captured_body = $request->get_body_params();
					return new WP_REST_Response( [], 200 );
				}
				return $result;
			},
			10,
			3
		);
	}

	/**
	 * Call the private stamp_express_attribution() method via reflection.
	 */
	private function invoke_stamp_express_attribution(): void {
		$method = new ReflectionMethod( WC_REST_WooPay_WSN_Checkout_Controller::class, 'stamp_express_attribution' );
		$method->invoke( $this->controller );
	}
}
