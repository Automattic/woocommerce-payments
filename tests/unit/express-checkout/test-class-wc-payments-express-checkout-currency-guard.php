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

	private const CURRENCY_HEADER       = 'HTTP_X_WOOPAYMENTS_PAYMENT_CURRENCY';
	private const TOKENIZED_CART_HEADER = 'HTTP_X_WOOPAYMENTS_TOKENIZED_CART';
	private const TOKENIZED_NONCE       = 'HTTP_X_WOOPAYMENTS_TOKENIZED_CART_NONCE';

	/**
	 * @var WC_Payments_Express_Checkout_Currency_Guard
	 */
	private $guard;

	public function set_up() {
		parent::set_up();
		$this->guard = new WC_Payments_Express_Checkout_Currency_Guard();
	}

	public function tear_down() {
		unset(
			$_SERVER[ self::CURRENCY_HEADER ],
			$_SERVER[ self::TOKENIZED_CART_HEADER ],
			$_SERVER[ self::TOKENIZED_NONCE ]
		);
		parent::tear_down();
	}

	/**
	 * @return WC_Order&MockObject
	 */
	private function build_order( string $currency ) {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_currency' )->willReturn( $currency );

		return $order;
	}

	private function set_ece_request_headers() {
		$_SERVER[ self::TOKENIZED_CART_HEADER ] = 'true';
		$_SERVER[ self::TOKENIZED_NONCE ]       = wp_create_nonce( 'woopayments_tokenized_cart_nonce' );
	}

	public function test_does_not_throw_when_request_is_not_an_ece_request() {
		// No tokenized-cart header — fail-open.
		$_SERVER[ self::CURRENCY_HEADER ] = 'usd';

		$order = $this->build_order( 'EUR' );
		$req   = $this->createMock( WP_REST_Request::class );

		$this->guard->assert_currency_matches_element( $order, $req );

		$this->assertTrue( true );
	}

	public function test_does_not_throw_when_tokenized_cart_nonce_is_invalid() {
		$_SERVER[ self::TOKENIZED_CART_HEADER ] = 'true';
		$_SERVER[ self::TOKENIZED_NONCE ]       = 'not-a-valid-nonce';
		$_SERVER[ self::CURRENCY_HEADER ]       = 'usd';

		$order = $this->build_order( 'EUR' );
		$req   = $this->createMock( WP_REST_Request::class );

		$this->guard->assert_currency_matches_element( $order, $req );

		$this->assertTrue( true );
	}

	public function test_does_not_throw_when_currency_header_is_missing() {
		$this->set_ece_request_headers();

		$order = $this->build_order( 'EUR' );
		$req   = $this->createMock( WP_REST_Request::class );

		$this->guard->assert_currency_matches_element( $order, $req );

		$this->assertTrue( true );
	}

	public function test_does_not_throw_when_currency_header_is_empty() {
		$this->set_ece_request_headers();
		$_SERVER[ self::CURRENCY_HEADER ] = '';

		$order = $this->build_order( 'EUR' );
		$req   = $this->createMock( WP_REST_Request::class );

		$this->guard->assert_currency_matches_element( $order, $req );

		$this->assertTrue( true );
	}

	public function test_does_not_throw_when_currencies_match_case_insensitively() {
		$this->set_ece_request_headers();
		$_SERVER[ self::CURRENCY_HEADER ] = 'USD';

		$order = $this->build_order( 'usd' );
		$req   = $this->createMock( WP_REST_Request::class );

		$this->guard->assert_currency_matches_element( $order, $req );

		$this->assertTrue( true );
	}

	public function test_throws_route_exception_on_currency_mismatch() {
		$this->set_ece_request_headers();
		$_SERVER[ self::CURRENCY_HEADER ] = 'USD';

		$order = $this->build_order( 'EUR' );
		$order->method( 'get_id' )->willReturn( 123 );
		$req = $this->createMock( WP_REST_Request::class );

		try {
			$this->guard->assert_currency_matches_element( $order, $req );
			$this->fail( 'Expected RouteException, none thrown.' );
		} catch ( RouteException $e ) {
			$this->assertSame(
				WC_Payments_Express_Checkout_Currency_Guard::MISMATCH_ERROR_CODE,
				$e->getErrorCode()
			);
			$this->assertSame( 400, $e->getCode() );
			$this->assertStringContainsString( 'USD', $e->getMessage() );
			$this->assertStringContainsString( 'EUR', $e->getMessage() );
		}
	}
}
