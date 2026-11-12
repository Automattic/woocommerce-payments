<?php
/**
 * Defends against currency mismatches between the Stripe Express Checkout
 * Element's boot currency and the cart's resolved currency at order
 * placement, which can happen when a multi-currency plugin flips the cart
 * based on the shipping address chosen inside the wallet sheet.
 *
 * Also defends pay-for-order wallet payments against an order total that
 * changes once the wallet's address is set on the order.
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
	 * Totals of the pay-for-order orders in this request, before the request's address was set, by order ID.
	 *
	 * @var array<int, string>
	 */
	private $order_totals_before_update = [];

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
		add_filter( 'rest_pre_dispatch', [ $guard, 'remember_order_total' ], 10, 3 );
		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			[ $guard, 'assert_order_total_unchanged' ]
		);
	}

	/**
	 * Remembers the total of the order a pay-for-order wallet payment is for, before the Store API sets the
	 * request's address on it. The wallet sheet showed this total.
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
		if ( $order instanceof WC_Order ) {
			$this->order_totals_before_update[ $order->get_id() ] = (string) $order->get_total();
		}

		return $response;
	}

	/**
	 * Rejects a pay-for-order wallet payment whose order total changed once the Store API set the wallet's address,
	 * e.g. through tax for that address: the shopper authorized the total the wallet sheet showed. WooCommerce has
	 * saved the address and the new total by now, so the next attempt shows and charges the new total.
	 *
	 * @param mixed $order The order being paid.
	 *
	 * @return void
	 *
	 * @throws RouteException When the order total changed.
	 */
	public function assert_order_total_unchanged( $order ) {
		// Only set for express checkout pay-for-order requests.
		if ( ! $order instanceof WC_Order || ! isset( $this->order_totals_before_update[ $order->get_id() ] ) ) {
			return;
		}

		$decimals       = wc_get_price_decimals();
		$total_before   = wc_format_decimal( $this->order_totals_before_update[ $order->get_id() ], $decimals );
		$total_after    = wc_format_decimal( $order->get_total(), $decimals );
		$order_currency = $order->get_currency();
		unset( $this->order_totals_before_update[ $order->get_id() ] );

		if ( $total_before === $total_after ) {
			return;
		}

		Logger::info(
			'Express checkout order total changed when the wallet address was set.',
			[
				'order_id'     => $order->get_id(),
				'total_before' => $total_before,
				'total_after'  => $total_after,
			]
		);

		throw new RouteException(
			self::ORDER_TOTAL_CHANGED_ERROR_CODE,
			sprintf(
				/* translators: %s: the new order total, e.g. $10.80 */
				__(
					'The order total changed to %s for your address, for example because of tax. You have not been charged — please check the new total and pay again.',
					'woocommerce-payments'
				),
				html_entity_decode( wp_strip_all_tags( wc_price( (float) $total_after, [ 'currency' => $order_currency ] ) ), ENT_QUOTES )
			),
			400
		);
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
