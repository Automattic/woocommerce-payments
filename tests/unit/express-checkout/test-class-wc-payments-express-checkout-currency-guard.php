<?php
/**
 * Class WC_Payments_Express_Checkout_Currency_Guard_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for the ECE currency guard that rejects order placement when
 * the cart currency has drifted away from the one the Stripe Element booted
 * with.
 */
class WC_Payments_Express_Checkout_Currency_Guard_Test extends WCPAY_UnitTestCase {

	const ORDER_TOTAL = '10.00';

	/**
	 * @var WC_Payments_Express_Checkout_Currency_Guard
	 */
	private $guard;

	public function set_up() {
		parent::set_up();
		$this->guard = new WC_Payments_Express_Checkout_Currency_Guard();
	}

	/**
	 * @return WC_Order&MockObject
	 */
	private function build_order( string $currency ) {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_currency' )->willReturn( $currency );

		return $order;
	}

	/**
	 * @param array<string, string> $headers Header overrides to layer on top of the ECE-context defaults.
	 *
	 * @return WP_REST_Request&MockObject
	 */
	private function build_request( array $headers = [] ) {
		$ece_headers = array_merge(
			[
				'X-WooPayments-Tokenized-Cart'       => 'true',
				'X-WooPayments-Tokenized-Cart-Nonce' => wp_create_nonce( 'woopayments_tokenized_cart_nonce' ),
			],
			$headers
		);

		$request = $this->createMock( WP_REST_Request::class );
		$request
			->method( 'get_header' )
			->willReturnCallback(
				function ( $name ) use ( $ece_headers ) {
					return $ece_headers[ $name ] ?? null;
				}
			);

		return $request;
	}

	public function test_does_not_throw_when_request_is_not_an_ece_request() {
		// No tokenized-cart header — fail-open.
		$req = $this->build_request(
			[
				'X-WooPayments-Tokenized-Cart'   => null,
				'X-WooPayments-Payment-Currency' => 'usd',
			]
		);

		$this->guard->assert_currency_matches_element( $this->build_order( 'EUR' ), $req );

		$this->assertTrue( true );
	}

	public function test_does_not_throw_when_tokenized_cart_nonce_is_invalid() {
		$req = $this->build_request(
			[
				'X-WooPayments-Tokenized-Cart-Nonce' => 'not-a-valid-nonce',
				'X-WooPayments-Payment-Currency'     => 'usd',
			]
		);

		$this->guard->assert_currency_matches_element( $this->build_order( 'EUR' ), $req );

		$this->assertTrue( true );
	}

	public function test_does_not_throw_when_currency_header_is_missing() {
		$req = $this->build_request();

		$this->guard->assert_currency_matches_element( $this->build_order( 'EUR' ), $req );

		$this->assertTrue( true );
	}

	public function test_does_not_throw_when_currency_header_is_empty() {
		$req = $this->build_request( [ 'X-WooPayments-Payment-Currency' => '' ] );

		$this->guard->assert_currency_matches_element( $this->build_order( 'EUR' ), $req );

		$this->assertTrue( true );
	}

	public function test_does_not_throw_when_currencies_match_case_insensitively() {
		$req = $this->build_request( [ 'X-WooPayments-Payment-Currency' => 'USD' ] );

		$this->guard->assert_currency_matches_element( $this->build_order( 'usd' ), $req );

		$this->assertTrue( true );
	}

	public function test_register_hooks_the_assertion_on_store_api_order_build() {
		$count_guard_callbacks = function () {
			global $wp_filter;
			$count = 0;
			$hook  = $wp_filter['woocommerce_store_api_checkout_update_order_from_request'] ?? null;
			foreach ( $hook->callbacks[10] ?? [] as $callback ) {
				if ( is_array( $callback['function'] )
					&& $callback['function'][0] instanceof WC_Payments_Express_Checkout_Currency_Guard
					&& 'assert_currency_matches_element' === $callback['function'][1]
					&& 2 === $callback['accepted_args'] ) {
					++$count;
				}
			}
			return $count;
		};

		$before = $count_guard_callbacks();

		WC_Payments_Express_Checkout_Currency_Guard::register();

		$this->assertSame(
			$before + 1,
			$count_guard_callbacks(),
			'Expected register() to hook assert_currency_matches_element() on woocommerce_store_api_checkout_update_order_from_request.'
		);
	}

	public function test_throws_route_exception_on_currency_mismatch() {
		$req = $this->build_request( [ 'X-WooPayments-Payment-Currency' => 'USD' ] );

		$order = $this->build_order( 'EUR' );
		$order->method( 'get_id' )->willReturn( 123 );

		try {
			$this->guard->assert_currency_matches_element( $order, $req );
			$this->fail( 'Expected RouteException, none thrown.' );
		} catch ( RouteException $e ) {
			$this->assertSame(
				'wcpay_express_checkout_currency_mismatch',
				$e->getErrorCode()
			);
			$this->assertSame( 400, $e->getCode() );
			$this->assertStringContainsString( 'USD', $e->getMessage() );
			$this->assertStringContainsString( 'EUR', $e->getMessage() );
		}
	}

	/**
	 * Builds a Store API request the way the pay-for-order wallet flow sends it.
	 *
	 * @param string      $route        The REST route.
	 * @param bool        $use_ece      Whether to send the express checkout headers.
	 * @param string|null $order_amount The order amount from the order fetch, if the client sends one.
	 *
	 * @return WP_REST_Request
	 */
	private function build_pay_for_order_request( string $route, bool $use_ece = true, ?string $order_amount = null ) {
		$request = new WP_REST_Request( 'POST', $route );
		if ( $use_ece ) {
			$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
			$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		}
		if ( null !== $order_amount ) {
			$request->set_header( 'X-WooPayments-Order-Total', $order_amount );
		}

		return $request;
	}

	/**
	 * @param string $total The order total.
	 *
	 * @return WC_Order
	 */
	private function create_order_with_total( string $total ): WC_Order {
		$order = WC_Helper_Order::create_order();
		$order->set_currency( 'USD' );
		$order->set_total( $total );
		$order->save();

		return $order;
	}

	/**
	 * Remembers the expected amount the way the request arrives, then re-prices the order the way the Store API
	 * does once the wallet's address is set.
	 *
	 * @param WC_Order        $order     The order being paid.
	 * @param WP_REST_Request $request   The Store API request.
	 * @param string          $new_total The order total after the address is set.
	 *
	 * @return void
	 */
	private function place_order_with_new_total( WC_Order $order, WP_REST_Request $request, string $new_total ) {
		$this->guard->remember_order_total( null, rest_get_server(), $request );
		$order->set_total( $new_total );
		$order->save();

		$this->guard->assert_order_total_unchanged( wc_get_order( $order->get_id() ) );
	}

	/**
	 * @param callable $place_order Places the order.
	 *
	 * @return RouteException
	 */
	private function get_order_total_changed_exception( callable $place_order ): RouteException {
		try {
			$place_order();
		} catch ( RouteException $e ) {
			$this->assertSame( 'wcpay_express_checkout_order_total_changed', $e->getErrorCode() );
			$this->assertSame( 400, $e->getCode() );

			return $e;
		}

		$this->fail( 'Expected RouteException, none thrown.' );
	}

	public function test_does_not_throw_when_the_order_total_is_unchanged() {
		$order   = $this->create_order_with_total( self::ORDER_TOTAL );
		$request = $this->build_pay_for_order_request( '/wc/store/v1/checkout/' . $order->get_id() );

		$this->place_order_with_new_total( $order, $request, self::ORDER_TOTAL );

		$this->assertTrue( true );
	}

	/**
	 * @dataProvider provider_changed_order_totals
	 */
	public function test_throws_when_the_order_total_changed( string $new_total, string $expected_total_in_message ) {
		$order   = $this->create_order_with_total( self::ORDER_TOTAL );
		$request = $this->build_pay_for_order_request( '/wc/store/v1/checkout/' . $order->get_id() );

		$e = $this->get_order_total_changed_exception(
			function () use ( $order, $request, $new_total ) {
				$this->place_order_with_new_total( $order, $request, $new_total );
			}
		);

		$this->assertStringContainsString( $expected_total_in_message, $e->getMessage() );
	}

	public function provider_changed_order_totals(): array {
		return [
			'tax added'   => [ '10.80', '$10.80' ],
			'tax removed' => [ '9.50', '$9.50' ],
		];
	}

	public function test_throws_when_the_order_total_changed_since_the_order_fetch() {
		// The wallet sheet showed 10.00; another tab then saved an address that re-priced the order.
		$order   = $this->create_order_with_total( '10.80' );
		$request = $this->build_pay_for_order_request( '/wc/store/v1/checkout/' . $order->get_id(), true, '1000' );

		$e = $this->get_order_total_changed_exception(
			function () use ( $order, $request ) {
				$this->place_order_with_new_total( $order, $request, '10.80' );
			}
		);

		$this->assertStringContainsString( '$10.80', $e->getMessage() );
	}

	public function test_does_not_throw_when_the_order_total_matches_the_order_fetch() {
		// A retry after the wallet sheet was refreshed with the new total.
		$order   = $this->create_order_with_total( '10.80' );
		$request = $this->build_pay_for_order_request( '/wc/store/v1/checkout/' . $order->get_id(), true, '1080' );

		$this->place_order_with_new_total( $order, $request, '10.80' );

		$this->assertTrue( true );
	}

	public function test_uses_the_order_total_on_arrival_when_the_sent_amount_is_invalid() {
		$order   = $this->create_order_with_total( self::ORDER_TOTAL );
		$request = $this->build_pay_for_order_request( '/wc/store/v1/checkout/' . $order->get_id(), true, '-1' );

		$this->place_order_with_new_total( $order, $request, self::ORDER_TOTAL );

		$this->assertTrue( true );
	}

	public function test_compares_and_formats_in_the_order_currency_when_another_currency_is_selected() {
		// Outside the order's own totals calculation, Multi-Currency gives the selected currency's decimals,
		// e.g. JPY's 0.
		$zero_decimals = function () {
			return 0;
		};
		$order         = $this->create_order_with_total( self::ORDER_TOTAL );
		$request       = $this->build_pay_for_order_request( '/wc/store/v1/checkout/' . $order->get_id() );

		$e = $this->get_order_total_changed_exception(
			function () use ( $order, $request, $zero_decimals ) {
				add_filter( 'wc_get_price_decimals', $zero_decimals, 1000 );
				$this->guard->remember_order_total( null, rest_get_server(), $request );
				remove_filter( 'wc_get_price_decimals', $zero_decimals, 1000 );

				$order->set_total( '10.20' );
				$order->save();
				// Multi-Currency keeps the decimals while an order loads.
				$saved_order = wc_get_order( $order->get_id() );

				add_filter( 'wc_get_price_decimals', $zero_decimals, 1000 );
				try {
					$this->guard->assert_order_total_unchanged( $saved_order );
				} finally {
					remove_filter( 'wc_get_price_decimals', $zero_decimals, 1000 );
				}
			}
		);

		$this->assertStringContainsString( '$10.20', $e->getMessage() );
	}

	public function test_does_not_throw_for_a_request_without_express_checkout_headers() {
		$order   = $this->create_order_with_total( self::ORDER_TOTAL );
		$request = $this->build_pay_for_order_request( '/wc/store/v1/checkout/' . $order->get_id(), false );

		$this->place_order_with_new_total( $order, $request, '10.80' );

		$this->assertTrue( true );
	}

	public function test_does_not_throw_for_cart_checkout() {
		$order   = $this->create_order_with_total( self::ORDER_TOTAL );
		$request = $this->build_pay_for_order_request( '/wc/store/v1/checkout' );

		$this->place_order_with_new_total( $order, $request, '10.80' );

		$this->assertTrue( true );
	}

	public function test_adds_the_order_amount_to_the_order_response() {
		$order = $this->create_order_with_total( '10.80' );

		$response = $this->guard->add_order_total_header(
			new WP_REST_Response( [], 200 ),
			rest_get_server(),
			new WP_REST_Request( 'GET', '/wc/store/v1/order/' . $order->get_id() )
		);

		$this->assertSame( '1080', $response->get_headers()['X-WooPayments-Order-Total'] ?? null );
	}

	/**
	 * @dataProvider provider_responses_without_the_order_amount
	 */
	public function test_does_not_add_the_order_amount_to_other_responses( string $method, string $route_prefix, int $status ) {
		$order = $this->create_order_with_total( self::ORDER_TOTAL );

		$response = $this->guard->add_order_total_header(
			new WP_REST_Response( [], $status ),
			rest_get_server(),
			new WP_REST_Request( $method, $route_prefix . $order->get_id() )
		);

		$this->assertArrayNotHasKey( 'X-WooPayments-Order-Total', $response->get_headers() );
	}

	public function provider_responses_without_the_order_amount(): array {
		return [
			'failed order fetch' => [ 'GET', '/wc/store/v1/order/', 401 ],
			'payment request'    => [ 'POST', '/wc/store/v1/checkout/', 200 ],
			'other route'        => [ 'GET', '/wc/store/v1/cart/items/', 200 ],
		];
	}

	public function test_register_hooks_the_order_total_check() {
		WC_Payments_Express_Checkout_Currency_Guard::register();

		$this->assertTrue( $this->has_guard_callback( 'rest_post_dispatch', 'add_order_total_header', 3 ) );
		$this->assertTrue( $this->has_guard_callback( 'rest_pre_dispatch', 'remember_order_total', 3 ) );
		$this->assertTrue( $this->has_guard_callback( 'woocommerce_store_api_checkout_update_order_from_request', 'assert_order_total_unchanged', 1 ) );
	}

	/**
	 * @param string $hook          The hook name.
	 * @param string $method        The guard method.
	 * @param int    $accepted_args The number of arguments it's hooked with.
	 *
	 * @return bool
	 */
	private function has_guard_callback( string $hook, string $method, int $accepted_args ): bool {
		global $wp_filter;
		foreach ( $wp_filter[ $hook ]->callbacks[10] ?? [] as $callback ) {
			if ( is_array( $callback['function'] )
				&& $callback['function'][0] instanceof WC_Payments_Express_Checkout_Currency_Guard
				&& $method === $callback['function'][1]
				&& $accepted_args === $callback['accepted_args'] ) {
				return true;
			}
		}

		return false;
	}
}
