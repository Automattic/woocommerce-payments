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
		WC_Payments_Express_Checkout_Currency_Guard::register();

		$this->assertNotFalse(
			has_action( 'woocommerce_store_api_checkout_update_order_from_request' ),
			'Expected register() to hook woocommerce_store_api_checkout_update_order_from_request.'
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
}
