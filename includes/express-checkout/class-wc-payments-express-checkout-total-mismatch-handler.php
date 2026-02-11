<?php
/**
 * Class WC_Payments_Express_Checkout_Total_Mismatch_Handler
 *
 * Handles detection and handling of total mismatches during Express Checkout payments.
 * This can occur when tax is calculated based on billing address, which is only
 * provided after the customer confirms payment in the Express Checkout dialog.
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\StoreApi\Payments\PaymentContext;
use Automattic\WooCommerce\StoreApi\Payments\PaymentResult;

/**
 * WC_Payments_Express_Checkout_Total_Mismatch_Handler class.
 */
class WC_Payments_Express_Checkout_Total_Mismatch_Handler {

	/**
	 * Threshold for acceptable total difference (to account for rounding).
	 * Value is in decimal currency units (e.g., 0.01 = one cent).
	 */
	const MISMATCH_THRESHOLD = 0.01;

	/**
	 * Order meta key for storing mismatch data.
	 */
	const ORDER_META_KEY = '_wcpay_ece_total_mismatch';

	/**
	 * Session cart total captured at cart load time (decimal amount).
	 *
	 * This represents the cart total from the session before billing address is applied.
	 * It's the total that was shown to the user in the Express Checkout dialog.
	 *
	 * @var float|null
	 */
	private $session_cart_total = null;

	/**
	 * Initialize hooks for this handler.
	 *
	 * @return void
	 */
	public function init() {
		// Capture cart total from the session before the billing address is applied.
		// This fires early in the checkout process, before customer data is updated.
		add_action(
			'woocommerce_cart_loaded_from_session',
			[ $this, 'capture_session_cart_total' ],
			10,
			1
		);

		// Run before WC's Legacy::process_legacy_payment (which is set with priority 999).
		// Setting the result status will prevent payment processing.
		add_action(
			'woocommerce_rest_checkout_process_payment_with_context',
			[ $this, 'check_ece_total_mismatch' ],
			5,
			2
		);

		// Display a mismatch notice on the pay-for-order page.
		// Use woocommerce_pay_order_before_payment hook which fires during template output,
		// allowing us to print the notice directly.
		add_action(
			'woocommerce_pay_order_before_payment',
			[ $this, 'maybe_display_mismatch_notice' ]
		);
	}

	/**
	 * Display mismatch notice on the pay-for-order page if redirected from ECE.
	 *
	 * @return void
	 */
	public function maybe_display_mismatch_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['wcpay_ece_mismatch'] ) ) {
			return;
		}

		global $wp;
		$order_id = absint( $wp->query_vars['order-pay'] ?? 0 );

		if ( ! $order_id ) {
			return;
		}

		// Verify the user is authorized to pay for this order before accessing order data.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['pay_for_order'] ) || ! isset( $_GET['key'] ) || ! current_user_can( 'pay_for_order', $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$mismatch_data = $order->get_meta( self::ORDER_META_KEY );
		// checking validity of data as well, in case it got manipulated.
		if ( empty( $mismatch_data ) || ! isset( $mismatch_data['expected'], $mismatch_data['actual'] ) ) {
			return;
		}

		// display the notice directly (not via session) since we're in the template output phase.
		$message = $this->get_customer_message(
			$order,
			$mismatch_data['expected'],
			$mismatch_data['actual']
		);

		wc_print_notice( $message, 'notice' );
	}

	/**
	 * Capture the cart total from session.
	 *
	 * This is called when the cart is loaded from session, before the billing address
	 * from the checkout request is applied. The session total represents what the user
	 * saw in the Express Checkout dialog (calculated with shipping address only).
	 *
	 * @param WC_Cart $cart Cart instance.
	 *
	 * @return void
	 */
	public function capture_session_cart_total( $cart ) {
		// Skip if the cart is empty (e.g., pay-for-order page where cart isn't used).
		if ( $cart->is_empty() ) {
			return;
		}

		// Get the cart total from the session. At this point, totals have been
		// restored from session but not yet recalculated with billing address.
		$totals = $cart->get_totals();

		if ( ! isset( $totals['total'] ) ) {
			return;
		}

		$this->session_cart_total = (float) $totals['total'];
	}

	/**
	 * Check for Express Checkout total mismatch and handle if detected.
	 *
	 * @param PaymentContext $context Payment context.
	 * @param PaymentResult  $result  Payment result (passed by reference).
	 *
	 * @return void
	 */
	public function check_ece_total_mismatch( PaymentContext $context, PaymentResult &$result ) {
		if ( ! $this->is_ece_payment( $context ) ) {
			return;
		}

		$expected_total = $this->get_expected_total();
		if ( null === $expected_total ) {
			return;
		}

		$order        = $context->order;
		$actual_total = (float) $order->get_total();
		$difference   = abs( $actual_total - $expected_total );

		if ( $difference <= self::MISMATCH_THRESHOLD ) {
			return;
		}

		$this->record_mismatch( $order, $expected_total, $actual_total );

		$customer_message = $this->get_customer_message( $order, $expected_total, $actual_total );

		$redirect_url = add_query_arg(
			'wcpay_ece_mismatch',
			'1',
			$order->get_checkout_payment_url()
		);

		// Set a "failure" result - this prevents Legacy::process_legacy_payment (in WC Core( from processing.
		$result->set_status( 'failure' );
		$result->set_redirect_url( $redirect_url );
		$result->set_payment_details(
			[
				'errorMessage' => $customer_message,
			]
		);
	}

	/**
	 * Check if the payment context is for an Express Checkout payment.
	 *
	 * @param PaymentContext $context Payment context.
	 *
	 * @return bool
	 */
	private function is_ece_payment( PaymentContext $context ) {
		if ( WC_Payment_Gateway_WCPay::GATEWAY_ID !== $context->payment_method ) {
			return false;
		}

		$express_payment_type = $context->payment_data['express_payment_type'] ?? '';

		return ! empty( $express_payment_type );
	}

	/**
	 * Get the expected total for comparison.
	 *
	 * Returns the captured session cart total, which represents the cart total
	 * before the billing address was applied. This is what the user saw in the
	 * Express Checkout dialog.
	 *
	 * @return float|null Expected total as a decimal amount, or null if not available.
	 */
	private function get_expected_total() {
		return $this->session_cart_total;
	}

	/**
	 * Format a price for display.
	 *
	 * Returns plain text (no HTML) for compatibility with frontend error display.
	 *
	 * @param float  $amount   Amount as decimal (e.g., 12.99).
	 * @param string $currency Currency code.
	 *
	 * @return string Formatted price as plain text.
	 */
	private function format_price( $amount, $currency ) {
		$html_price  = wc_price( $amount, [ 'currency' => $currency ] );
		$plain_price = wp_strip_all_tags( $html_price );

		return html_entity_decode( $plain_price, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Record the mismatch in order meta and add an internal order note.
	 *
	 * @param WC_Order $order          Order object.
	 * @param float    $expected_total Expected total as decimal.
	 * @param float    $actual_total   Actual total as decimal.
	 *
	 * @return void
	 */
	private function record_mismatch( $order, $expected_total, $actual_total ) {
		$currency = $order->get_currency();

		$order->update_meta_data(
			self::ORDER_META_KEY,
			[
				'expected'   => $expected_total,
				'actual'     => $actual_total,
				'difference' => abs( $actual_total - $expected_total ),
				'currency'   => $currency,
				'timestamp'  => time(),
			]
		);

		$order->add_order_note(
			sprintf(
				/* translators: 1: expected total, 2: actual total */
				__( 'Express Checkout payment paused: Order total changed from %1$s to %2$s after billing address was provided (tax recalculation). Customer redirected to complete payment.', 'woocommerce-payments' ),
				$this->format_price( $expected_total, $currency ),
				$this->format_price( $actual_total, $currency )
			)
		);

		$order->save();
	}

	/**
	 * Get the customer-facing error message.
	 *
	 * @param WC_Order $order          Order object.
	 * @param float    $expected_total Expected total as decimal.
	 * @param float    $actual_total   Actual total as decimal.
	 *
	 * @return string Customer-facing message.
	 */
	private function get_customer_message( $order, $expected_total, $actual_total ) {
		$currency = $order->get_currency();

		return sprintf(
			/* translators: 1: expected total, 2: actual total */
			__( 'Your order total has been updated from %1$s to %2$s based on your billing address. Please review and complete your payment.', 'woocommerce-payments' ),
			$this->format_price( $expected_total, $currency ),
			$this->format_price( $actual_total, $currency )
		);
	}
}
