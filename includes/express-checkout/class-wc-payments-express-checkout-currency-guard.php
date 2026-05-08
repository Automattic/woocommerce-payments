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

	const PAYMENT_CURRENCY_HEADER = 'HTTP_X_WOOPAYMENTS_PAYMENT_CURRENCY';
	const TOKENIZED_CART_HEADER   = 'HTTP_X_WOOPAYMENTS_TOKENIZED_CART';
	const TOKENIZED_CART_NONCE    = 'HTTP_X_WOOPAYMENTS_TOKENIZED_CART_NONCE';
	const MISMATCH_ERROR_CODE     = 'wcpay_express_checkout_currency_mismatch';

	/**
	 * Register the assertion on Store API checkout order builds.
	 *
	 * @return void
	 */
	public function init() {
		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			[ $this, 'assert_currency_matches_element' ],
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
	 * @param WP_REST_Request $request The Store API request (unused).
	 *
	 * @return void
	 *
	 * @throws RouteException When the currencies disagree.
	 */
	public function assert_currency_matches_element( $order, $request ) {
		if ( ! $this->is_express_checkout_request() ) {
			return;
		}

		$expected = strtolower(
			sanitize_text_field(
				wp_unslash( $_SERVER[ self::PAYMENT_CURRENCY_HEADER ] ?? '' )
			)
		);
		if ( '' === $expected ) {
			return;
		}

		$actual = strtolower( $order->get_currency() );
		if ( $expected === $actual ) {
			return;
		}

		Logger::error(
			sprintf(
				'Express checkout currency mismatch at order placement. order_id=%d element_currency=%s order_currency=%s',
				$order->get_id(),
				$expected,
				$actual
			)
		);

		throw new RouteException(
			self::MISMATCH_ERROR_CODE,
			sprintf(
				/* translators: 1: expected currency code, 2: actual currency code */
				__(
					'The selected shipping address requires a different currency (%2$s) than the one used to start this payment (%1$s). Please reload the page.',
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
	 * @return bool
	 */
	private function is_express_checkout_request() {
		$is_tokenized_cart = 'true' === sanitize_text_field(
			wp_unslash( $_SERVER[ self::TOKENIZED_CART_HEADER ] ?? '' )
		);
		if ( ! $is_tokenized_cart ) {
			return false;
		}

		$nonce = sanitize_text_field(
			wp_unslash( $_SERVER[ self::TOKENIZED_CART_NONCE ] ?? '' )
		);
		return (bool) wp_verify_nonce( $nonce, 'woopayments_tokenized_cart_nonce' );
	}
}
