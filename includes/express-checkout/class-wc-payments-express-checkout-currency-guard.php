<?php
/**
 * Defends against currency mismatches between the Stripe Express Checkout
 * Element's boot currency and the cart's resolved currency at order
 * placement, which can happen when a multi-currency plugin flips the cart
 * based on the shipping address chosen inside the wallet sheet.
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
