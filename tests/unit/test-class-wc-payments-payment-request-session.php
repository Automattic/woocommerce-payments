<?php
/**
 * Class WC_Payments_Payment_Request_Session_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Payment_Request_Session unit tests.
 */
class WC_Payments_Payment_Request_Session_Test extends WCPAY_UnitTestCase {

	/**
	 * Previous user ID.
	 * @var int
	 */
	private $previous_user_id;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		// Setting the admin as the current user.
		$this->previous_user_id = get_current_user_id();
		wp_set_current_user( 1 );
		WC()->cart->empty_cart();
	}

	public function tear_down() {
		wp_set_current_user( $this->previous_user_id );
		unset( $_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_SESSION_NONCE'] );
		unset( $_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_SESSION'] );
		unset( $_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_IS_EPHEMERAL_CART'] );
		unset( $_SERVER['REQUEST_URI'] );
		unset( $_REQUEST['rest_route'] );
		// we need to manually unset the session, otherwise it'll linger between test cases.
		WC()->session = null;
		WC()->initialize_session();

		parent::tear_down();
	}

	public function test_adds_tokenized_session_headers() {
		$_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_SESSION_NONCE'] = wp_create_nonce( 'woopayments_tokenized_cart_session_nonce' );
		$_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_SESSION']       = '';
		$_SERVER['REQUEST_URI']                                     = '/index.php';
		$_REQUEST['rest_route']                                     = '/wc/store/v1/cart';
		$request = new WP_REST_Request( 'GET', '/wc/store/v1/cart' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Session-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_session_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );

		$session = new WC_Payments_Payment_Request_Session();
		$session->init();

		// need to manually call this method, because otherwise WooCommerce hasn't instantiated the session when the request is made.
		WC()->initialize_session();

		$response = rest_do_request( $request );
		// manually calling 'rest_post_dispatch' because it is not called within the context of unit tests.
		$response = apply_filters( 'rest_post_dispatch', $response, rest_get_server(), $request ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.HookCommentWrongStyle

		$this->assertSame( WC_Payments_Payment_Request_Session_Handler::class, apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' ) ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
		$this->assertIsString( $response->get_headers()['X-WooPayments-Tokenized-Cart-Session'] );
	}

	public function test_does_not_add_tokenized_session_headers_on_invalid_nonce() {
		$_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_SESSION_NONCE'] = 'invalid-nonce';
		$_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_SESSION']       = '';
		$_SERVER['REQUEST_URI']                                     = '/index.php';
		$_REQUEST['rest_route']                                     = '/wc/store/v1/cart';
		$request = new WP_REST_Request( 'GET', '/wc/store/v1/cart' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Session-Nonce', 'invalid-nonce' );
		$request->set_header( 'Content-Type', 'application/json' );

		$session = new WC_Payments_Payment_Request_Session();
		$session->init();

		// need to manually call this method, because otherwise WooCommerce hasn't instantiated the session when the request is made.
		WC()->initialize_session();

		$response = rest_do_request( $request );
		// manually calling 'rest_post_dispatch' because it is not called within the context of unit tests.
		$response = apply_filters( 'rest_post_dispatch', $response, rest_get_server(), $request ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.HookCommentWrongStyle

		$this->assertNotSame( WC_Payments_Payment_Request_Session_Handler::class, apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' ) ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
		$this->assertNotContains( 'X-WooPayments-Tokenized-Cart-Session', array_keys( $response->get_headers() ) );
	}

	public function test_does_not_use_custom_session_handler_on_invalid_nonce() {
		$_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_SESSION_NONCE'] = 'invalid-nonce';
		$_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_SESSION']       = '';
		$_SERVER['REQUEST_URI']                                     = '/index.php';
		$_REQUEST['rest_route']                                     = '/wc/store/v1/cart';
		$request = new WP_REST_Request( 'GET', '/wc/store/v1/cart' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Session-Nonce', 'invalid-nonce' );
		$request->set_header( 'Content-Type', 'application/json' );

		$session = new WC_Payments_Payment_Request_Session();
		$session->init();

		$this->assertNotSame( WC_Payments_Payment_Request_Session_Handler::class, apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' ) ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
	}

	/**
	 * A page cache can serve the product page after its nonce expired.
	 * The first price lookup then reaches Multi-Currency's `?currency=` switch before the Store API rejects it.
	 */
	public function test_does_not_send_session_cookie_for_ephemeral_cart_on_invalid_nonce() {
		$_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_IS_EPHEMERAL_CART'] = '1';

		$this->assertNotContains( 'wp_woocommerce_session_' . COOKIEHASH, $this->get_cookies_sent_on_invalid_nonce() );
	}

	public function test_sends_session_cookie_on_invalid_nonce_when_cart_is_not_ephemeral() {
		$this->assertContains( 'wp_woocommerce_session_' . COOKIEHASH, $this->get_cookies_sent_on_invalid_nonce() );
	}

	/**
	 * Starts a guest's Store API session with an invalid nonce, then asks for the session cookie like Multi-Currency does.
	 *
	 * @return string[] Names of the cookies that would have been sent.
	 */
	private function get_cookies_sent_on_invalid_nonce() {
		wp_set_current_user( 0 );
		$_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_SESSION_NONCE'] = 'invalid-nonce';
		$_SERVER['REQUEST_URI']                                     = '/index.php';
		$_REQUEST['rest_route']                                     = '/wc/store/v1/cart/add-item';
		$sent_cookies = [];
		add_filter(
			'woocommerce_set_cookie_enabled',
			function ( $enabled, $name ) use ( &$sent_cookies ) {
				if ( $enabled ) {
					$sent_cookies[] = $name;
				}

				return false;
			},
			PHP_INT_MAX,
			2
		);

		$session = new WC_Payments_Payment_Request_Session();
		$session->init();
		WC()->session = null;
		WC()->initialize_session();
		WC()->session->set_customer_session_cookie( true );

		return $sent_cookies;
	}

	public function test_uses_custom_session_handler() {
		$_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_SESSION_NONCE'] = wp_create_nonce( 'woopayments_tokenized_cart_session_nonce' );
		$_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_SESSION']       = '';
		$_SERVER['REQUEST_URI']                                     = '/index.php';
		$_REQUEST['rest_route']                                     = '/wc/store/v1/cart';
		$request = new WP_REST_Request( 'GET', '/wc/store/v1/cart' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Session-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_session_nonce' ) );
		$request->set_header( 'Content-Type', 'application/json' );

		$session = new WC_Payments_Payment_Request_Session();
		$session->init();

		// need to manually call this method, because otherwise WooCommerce hasn't instantiated the session when the request is made.
		WC()->initialize_session();

		WC()->cart->add_to_cart( WC_Helper_Product::create_simple_product()->get_id(), 1 );
		WC()->cart->calculate_totals();

		$this->assertCount( 1, WC()->cart->cart_contents );

		rest_do_request( $request );

		$this->assertSame( WC_Payments_Payment_Request_Session_Handler::class, apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' ) ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment

		$this->assertInstanceOf( WC_Payments_Payment_Request_Session_Handler::class, WC()->session );
		// cart contents are not cleared.
		$this->assertCount( 1, WC()->cart->cart_contents );
	}

	public function test_clears_cart_after_response_when_header_is_provided() {
		$_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_SESSION_NONCE'] = wp_create_nonce( 'woopayments_tokenized_cart_session_nonce' );
		$_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_SESSION']       = '';
		$_SERVER['REQUEST_URI']                                     = '/index.php';
		$_REQUEST['rest_route']                                     = '/wc/store/v1/cart';
		$request = new WP_REST_Request( 'GET', '/wc/store/v1/cart' );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Session-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_session_nonce' ) );
		$request->set_header( 'X-WooPayments-Tokenized-Cart-Is-Ephemeral-Cart', '1' );
		$request->set_header( 'Content-Type', 'application/json' );

		$session = new WC_Payments_Payment_Request_Session();
		$session->init();

		// need to manually call this method, because otherwise WooCommerce hasn't instantiated the session when the request is made.
		WC()->initialize_session();

		WC()->cart->add_to_cart( WC_Helper_Product::create_simple_product()->get_id(), 1 );
		WC()->cart->calculate_totals();

		$this->assertCount( 1, WC()->cart->cart_contents );

		$response = rest_do_request( $request );
		// manually calling 'rest_post_dispatch' because it is not called within the context of unit tests.
		apply_filters( 'rest_post_dispatch', $response, rest_get_server(), $request ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.HookCommentWrongStyle

		// cart contents are cleared, because the `X-WooPayments-Tokenized-Cart-Is-Ephemeral-Cart` header has been provided.
		$this->assertCount( 0, WC()->cart->cart_contents );
	}

	public function test_restores_cart_data_on_order_received_page() {
		$_SERVER['REQUEST_URI'] = '/checkout/order-received/1/?key=wc_order_FFFFFFFFFFFFF&woopayments-custom-session=1';

		$session = new WC_Payments_Payment_Request_Session();
		$session->init();

		WC()->session->init();
		WC()->cart->add_to_cart( WC_Helper_Product::create_simple_product()->get_id(), 1 );
		WC()->cart->calculate_totals();

		$this->assertCount( 1, WC()->cart->cart_contents );

		WC()->cart->empty_cart();

		// cart is not cleared.
		$this->assertCount( 1, WC()->cart->cart_contents );
	}

	public function test_does_not_restore_cart_data_when_missing_query_parameter() {
		$_SERVER['REQUEST_URI'] = '/checkout/order-received/1/?key=wc_order_FFFFFFFFFFFFF';

		$session = new WC_Payments_Payment_Request_Session();
		$session->init();

		WC()->session->init();
		WC()->cart->add_to_cart( WC_Helper_Product::create_simple_product()->get_id(), 1 );
		WC()->cart->calculate_totals();

		$this->assertCount( 1, WC()->cart->cart_contents );

		WC()->cart->empty_cart();

		// cart is cleared.
		$this->assertCount( 0, WC()->cart->cart_contents );
	}
}
