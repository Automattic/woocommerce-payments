<?php
/**
 * Class Order_Fraud_And_Risk_Meta_Box
 *
 * @package WCPay\Fraud_Prevention
 */

namespace WCPay\Fraud_Prevention;

use WC_Payments_Features;
use WC_Payments_Utils;
use WCPay\Constants\Fraud_Outcome_Status;

/**
 * Class Order_Fraud_And_Risk_Meta_Box
 */
class Order_Fraud_And_Risk_Meta_Box {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'maybe_add_meta_box' ] );
	}

	/**
	 * Maybe add the meta box.
	 */
	public function maybe_add_meta_box() {
		// If fraud settings are off, or if we cannot get the screen ID, exit.
		if ( ! WC_Payments_Features::is_fraud_protection_settings_enabled() || ! function_exists( '\wc_get_page_screen_id' ) ) {
			return;
		}

		// Get the order edit screen to be able to add the meta box to.
		$wc_screen_id = \wc_get_page_screen_id( 'shop-order' );

		add_meta_box( 'wcpay_order_fraud_and_risk_meta_box', __( 'Fraud &amp; Risk', 'woocommerce-payments' ), [ $this, 'display_order_fraud_and_risk_meta_box_message' ], $wc_screen_id, 'side', 'default' );
	}

	/**
	 * Displays the contents of the Fraud & Risk meta box.
	 *
	 * @param \WC_Order $order The order we are working with.
	 *
	 * @return void
	 */
	public function display_order_fraud_and_risk_meta_box_message( $order ) {
		$order          = wc_get_order( $order );
		$intent_id      = $order->get_meta( '_intent_id' );
		$charge_id      = $order->get_meta( '_charge_id' );
		$outcome_status = $order->get_meta( '_wcpay_fraud_outcome_status' );

		if ( 'woocommerce_payments' !== $order->get_payment_method() ) {
			$outcome_status = 'not_wcpay';
		}

		switch ( $outcome_status ) {
			case Fraud_Outcome_Status::ALLOW:
				$status      = __( 'No action taken', 'woocommerce-payments' );
				$description = __( 'The payment for this order passed your risk filtering.', 'woocommerce-payments' );
				echo '<p class="wcpay-fraud-risk-meta-passed">' . esc_html( $status ) . '</p><p>' . esc_html( $description ) . '</p>';
				break;

			case Fraud_Outcome_Status::REVIEW:
				$status          = __( 'Held for review', 'woocommerce-payments' );
				$description     = __( 'The payment for this order was held for review by your risk filtering. You can review the details and determine whether to approve or block the payment.', 'woocommerce-payments' );
				$callout         = __( 'Review payment', 'woocommerce-payments' );
				$transaction_url = WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );
				echo '<p class="wcpay-fraud-risk-meta-held">' . esc_html( $status ) . '</p><p>' . esc_html( $description ) . '</p><a href="' . esc_url( $transaction_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $callout ) . '</a>';
				break;

			case Fraud_Outcome_Status::REVIEW_ALLOWED:
				$status          = __( 'Held for review', 'woocommerce-payments' );
				$description     = __( 'Need text to display once allowed.', 'woocommerce-payments' );
				$callout         = __( 'Review payment', 'woocommerce-payments' );
				$transaction_url = WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );
				echo '<p class="wcpay-fraud-risk-meta-held">' . esc_html( $status ) . '</p><p>' . esc_html( $description ) . '</p><a href="' . esc_url( $transaction_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $callout ) . '</a>';
				break;

			case Fraud_Outcome_Status::BLOCK:
				// TODO: Are we going to cancel the orders? The note states we are, but if we do, we do not allow them to correct mistakes and could create a lot of additional orders.
				$status          = __( 'Blocked', 'woocommerce-payments' );
				$description     = __( 'The payment for this order was blocked by your risk filtering, and the order has been cancelled.', 'woocommerce-payments' );
				$callout         = __( 'View payment details', 'woocommerce-payments' );
				$transaction_url = WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );
				// TODO: There is no transaction url returned due to there's no intent or charge ID.
				echo '<p class="wcpay-fraud-risk-meta-blocked">' . esc_html( $status ) . '</p><p>' . esc_html( $description ) . '</p><a href="' . esc_url( $transaction_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $callout ) . '</a>';
				break;

			case Fraud_Outcome_Status::REVIEW:
				$description = __( 'Risk filtering is only available for orders that are processed with WooCommerce Payments.', 'woocommerce-payments' );
				$callout     = __( 'Learn more', 'woocommerce-payments' );
				$callout_url = '';
				echo '<p>' . esc_html( $description ) . '</p><a href="' . esc_url( $callout_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $callout ) . '</a>';
				break;

			default:
				$description = __( 'Risk filtering through WooCommerce Payments was not found on this order, it may have been created while filtering was not enabled.', 'woocommerce-payments' );
				echo '<p>' . esc_html( $description ) . '</p>';
				break;
		}
	}
}
