<?php
/**
 * Defends against currency mismatches between the Stripe Express Checkout
 * Element's boot currency and the cart's resolved currency at order
 * placement, which can happen when a multi-currency plugin flips the cart
 * based on the shipping address chosen inside the wallet sheet.
 *
 * Also defends pay-for-order wallet payments against an order total that
 * changed since the wallet sheet showed it, e.g. once the wallet's address
 * is set on the order.
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use WCPay\Logger;

/**
 * Asserts that the order's currency matches the currency that the Stripe
 * Express Checkout Element was created with. Throws a RouteException on
 * mismatch so order placement fails cleanly with a clear message.
 */
class WC_Payments_Express_Checkout_Currency_Guard {

	const MISMATCH_ERROR_CODE = 'wcpay_express_checkout_currency_mismatch';

	const ORDER_TOTAL_CHANGED_ERROR_CODE = 'wcpay_express_checkout_order_total_changed';

	/**
	 * Carries the order's amount: in the order fetch's response, and back in the payment request.
	 */
	const ORDER_TOTAL_HEADER = 'X-WooPayments-Order-Total';

	/**
	 * Amounts the pay-for-order wallet payments in this request expect to be charged, by order ID.
	 *
	 * @var array<int, int>
	 */
	private $expected_order_amounts = [];

	/**
	 * Boots the guard. Wires itself onto Store API checkout order builds so
	 * the surrounding bootstrap doesn't need to know how the assertion is
	 * dispatched.
	 *
	 * @return void
	 */
	public static function register() {
		$guard = new self();
		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			[ $guard, 'assert_currency_matches_element' ],
			10,
			2
		);
		add_filter( 'rest_post_dispatch', [ $guard, 'add_order_total_header' ], 10, 3 );
		add_filter( 'rest_pre_dispatch', [ $guard, 'remember_order_total' ], 10, 3 );
		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			[ $guard, 'assert_order_total_unchanged' ]
		);
	}

	/**
	 * Adds the order's amount to a Store API order response. The pay-for-order wallet sheet shows the total from
	 * that response, and the payment sends the amount back, so a total that changed since (e.g. in another tab)
	 * is detected.
	 *
	 * @param mixed          $response The response.
	 * @param WP_REST_Server $server   Server instance.
	 * @param mixed          $request  The REST request.
	 *
	 * @return mixed
	 */
	public function add_order_total_header( $response, $server, $request ) {
		if ( ! $response instanceof WP_REST_Response || 200 !== $response->get_status() ) {
			return $response;
		}

		if ( ! $request instanceof WP_REST_Request || 'GET' !== $request->get_method() ) {
			return $response;
		}

		if ( ! preg_match( '#^/wc/store/v1/order/(\d+)$#', $request->get_route(), $matches ) ) {
			return $response;
		}

		$order = wc_get_order( (int) $matches[1] );
		if ( $order instanceof WC_Order ) {
			$response->header( self::ORDER_TOTAL_HEADER, (string) $this->get_order_amount( $order ) );
		}

		return $response;
	}

	/**
	 * Remembers the amount a pay-for-order wallet payment expects to be charged, before the Store API sets the
	 * request's address on the order: the amount from the order fetch the wallet sheet showed, or the order's
	 * amount when the request arrives if the client didn't send one (e.g. a cached older script).
	 *
	 * @param mixed          $response The response to short-circuit the request with, passed through unchanged.
	 * @param WP_REST_Server $server   Server instance.
	 * @param mixed          $request  The REST request.
	 *
	 * @return mixed
	 */
	public function remember_order_total( $response, $server, $request ) {
		if ( ! $request instanceof WP_REST_Request || 'POST' !== $request->get_method() ) {
			return $response;
		}

		if ( ! preg_match( '#^/wc/store/v1/checkout/(\d+)$#', $request->get_route(), $matches ) ) {
			return $response;
		}

		if ( ! $this->is_express_checkout_request( $request ) ) {
			return $response;
		}

		$order = wc_get_order( (int) $matches[1] );
		if ( ! $order instanceof WC_Order ) {
			return $response;
		}

		$fetched_amount = (string) $request->get_header( self::ORDER_TOTAL_HEADER );

		$this->expected_order_amounts[ $order->get_id() ] = ctype_digit( $fetched_amount )
			? (int) $fetched_amount
			: $this->get_order_amount( $order );

		return $response;
	}

	/**
	 * Rejects a pay-for-order wallet payment whose order amount differs from the one it expects: the shopper
	 * authorized the total the wallet sheet showed. It changes e.g. through tax for the wallet's address, or in
	 * another tab. WooCommerce has saved the address and the new total by now, so the next attempt shows and
	 * charges the new total.
	 *
	 * @param mixed $order The order being paid.
	 *
	 * @return void
	 *
	 * @throws RouteException When the order total changed.
	 */
	public function assert_order_total_unchanged( $order ) {
		// Only set for express checkout pay-for-order requests.
		if ( ! $order instanceof WC_Order || ! isset( $this->expected_order_amounts[ $order->get_id() ] ) ) {
			return;
		}

		$expected_amount = $this->expected_order_amounts[ $order->get_id() ];
		$order_amount    = $this->get_order_amount( $order );
		unset( $this->expected_order_amounts[ $order->get_id() ] );

		if ( $expected_amount === $order_amount ) {
			return;
		}

		Logger::info(
			'Express checkout order total changed before the wallet payment.',
			[
				'order_id'        => $order->get_id(),
				'currency'        => $order->get_currency(),
				'expected_amount' => $expected_amount,
				'order_amount'    => $order_amount,
			]
		);

		throw new RouteException(
			self::ORDER_TOTAL_CHANGED_ERROR_CODE,
			sprintf(
				/* translators: %s: the new order total, e.g. $10.80 */
				__(
					'The order total changed to %s, for example because of tax for your address. You have not been charged — please check the new total and pay again.',
					'woocommerce-payments'
				),
				WC_Payments_Utils::format_currency( (float) $order->get_total(), $order->get_currency() )
			),
			400
		);
	}

	/**
	 * Returns the amount WooPayments charges for the order, in the order currency's smallest unit. It doesn't
	 * depend on the shopper's selected Multi-Currency currency.
	 *
	 * @param WC_Order $order The order.
	 *
	 * @return int
	 */
	private function get_order_amount( WC_Order $order ): int {
		return WC_Payments_Utils::prepare_amount( $order->get_total(), $order->get_currency() );
	}

	/**
	 * Compare the boot currency carried on the request to the order's
	 * resolved currency. Fail-open when no header was sent (older client,
	 * non-ECE caller).
	 *
	 * @param WC_Order        $order   The order being created.
	 * @param WP_REST_Request $request The Store API request.
	 *
	 * @return void
	 *
	 * @throws RouteException When the currencies disagree.
	 */
	public function assert_currency_matches_element( $order, $request ) {
		if ( ! $this->is_express_checkout_request( $request ) ) {
			return;
		}

		$expected = strtolower( (string) $request->get_header( 'X-WooPayments-Payment-Currency' ) );
		if ( '' === $expected ) {
			return;
		}

		$actual = strtolower( $order->get_currency() );
		if ( $expected === $actual ) {
			return;
		}

		Logger::error(
			'Express checkout currency mismatch at order placement.',
			[
				'order_id'         => $order->get_id(),
				'element_currency' => $expected,
				'order_currency'   => $actual,
			]
		);

		throw new RouteException(
			self::MISMATCH_ERROR_CODE,
			sprintf(
				/* translators: 1: expected currency code, 2: actual currency code */
				__(
					'The shipping address you selected requires a different currency (%2$s) than the one this payment was started with (%1$s). You have not been charged — please reload the page and try again.',
					'woocommerce-payments'
				),
				strtoupper( $expected ),
				strtoupper( $actual )
			),
			400
		);
	}

	/**
	 * Mirrors the check used elsewhere in the express checkout layer to
	 * scope behavior to ECE-originated Store API requests: the tokenized
	 * cart header must be set and its nonce must verify.
	 *
	 * @param WP_REST_Request $request The Store API request.
	 *
	 * @return bool
	 */
	private function is_express_checkout_request( $request ) {
		if ( 'true' !== $request->get_header( 'X-WooPayments-Tokenized-Cart' ) ) {
			return false;
		}

		$nonce = (string) $request->get_header( 'X-WooPayments-Tokenized-Cart-Nonce' );
		return (bool) wp_verify_nonce( $nonce, WC_Payments_Express_Checkout_Button_Helper::TOKENIZED_CART_NONCE_ACTION );
	}
}
