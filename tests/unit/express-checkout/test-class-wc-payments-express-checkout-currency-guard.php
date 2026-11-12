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
	 * @param string $route   The REST route.
	 * @param bool   $use_ece Whether to send the express checkout headers.
	 *
	 * @return WP_REST_Request
	 */
	private function build_pay_for_order_request( string $route, bool $use_ece = true ) {
		$request = new WP_REST_Request( 'POST', $route );
		if ( $use_ece ) {
			$request->set_header( 'X-WooPayments-Tokenized-Cart', 'true' );
			$request->set_header( 'X-WooPayments-Tokenized-Cart-Nonce', wp_create_nonce( 'woopayments_tokenized_cart_nonce' ) );
		}

		return $request;
	}

	/**
	 * Remembers the order total the way the request arrives, then re-prices the order the way the Store API does
	 * once the wallet's address is set.
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

	public function test_does_not_throw_when_the_order_total_is_unchanged() {
		$order = WC_Helper_Order::create_order();
		$order->set_total( self::ORDER_TOTAL );
		$order->save();
		$request = $this->build_pay_for_order_request( '/wc/store/v1/checkout/' . $order->get_id() );

		$this->place_order_with_new_total( $order, $request, self::ORDER_TOTAL );

		$this->assertTrue( true );
	}

	/**
	 * @dataProvider provider_changed_order_totals
	 */
	public function test_throws_when_the_order_total_changed( string $new_total, string $expected_total_in_message ) {
		$order = WC_Helper_Order::create_order();
		$order->set_total( self::ORDER_TOTAL );
		$order->save();
		$request = $this->build_pay_for_order_request( '/wc/store/v1/checkout/' . $order->get_id() );

		try {
			$this->place_order_with_new_total( $order, $request, $new_total );
			$this->fail( 'Expected RouteException, none thrown.' );
		} catch ( RouteException $e ) {
			$this->assertSame( 'wcpay_express_checkout_order_total_changed', $e->getErrorCode() );
			$this->assertSame( 400, $e->getCode() );
			$this->assertStringContainsString( $expected_total_in_message, $e->getMessage() );
		}
	}

	public function provider_changed_order_totals(): array {
		return [
			'tax added'   => [ '10.80', '10.80' ],
			'tax removed' => [ '9.50', '9.50' ],
		];
	}

	public function test_does_not_throw_for_a_request_without_express_checkout_headers() {
		$order = WC_Helper_Order::create_order();
		$order->set_total( self::ORDER_TOTAL );
		$order->save();
		$request = $this->build_pay_for_order_request( '/wc/store/v1/checkout/' . $order->get_id(), false );

		$this->place_order_with_new_total( $order, $request, '10.80' );

		$this->assertTrue( true );
	}

	public function test_does_not_throw_for_cart_checkout() {
		$order = WC_Helper_Order::create_order();
		$order->set_total( self::ORDER_TOTAL );
		$order->save();
		$request = $this->build_pay_for_order_request( '/wc/store/v1/checkout' );

		$this->place_order_with_new_total( $order, $request, '10.80' );

		$this->assertTrue( true );
	}

	public function test_register_hooks_the_order_total_check() {
		WC_Payments_Express_Checkout_Currency_Guard::register();

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
