<?php
/**
 * Class Order_Fraud_And_Risk_Meta_Box
 *
 * @package WCPay\Fraud_Prevention
 */

namespace WCPay\Fraud_Prevention;

use WC_Payments_Features;
use WC_Payments_Order_Service;
use WC_Payments_Utils;
use WCPay\Constants\Fraud_Outcome_Status;

/**
 * Class Order_Fraud_And_Risk_Meta_Box
 */
class Order_Fraud_And_Risk_Meta_Box {
	/**
	 * The Order Service.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $order_service;

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_Order_Service $order_service The order service.
	 */
	public function __construct( WC_Payments_Order_Service $order_service ) {
		add_action( 'add_meta_boxes', [ $this, 'maybe_add_meta_box' ] );

		$this->order_service = $order_service;
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
		$order = wc_get_order( $order );

		if ( ! $order ) {
			return;
		}

		$intent_id      = $this->order_service->get_intent_id_for_order( $order );
		$charge_id      = $this->order_service->get_charge_id_for_order( $order );
		$outcome_status = $this->order_service->get_fraud_outcome_status_for_order( $order );

		if ( 'woocommerce_payments' !== $order->get_payment_method() ) {
			$outcome_status = 'not_wcpay';
		}

		switch ( $outcome_status ) {
			case Fraud_Outcome_Status::ALLOW:
				$icon_url    = plugins_url( 'assets/images/icons/check-green.svg', WCPAY_PLUGIN_FILE );
				$icon_alt    = __( 'Green check mark', 'woocommerce-payments' );
				$status      = __( 'No action taken', 'woocommerce-payments' );
				$description = __( 'The payment for this order passed your risk filtering.', 'woocommerce-payments' );
				echo '<p class="wcpay-fraud-risk-meta-allow"><img src="' . esc_url( $icon_url ) . '" alt="' . esc_html( $icon_alt ) . '"> ' . esc_html( $status ) . '</p><p>' . esc_html( $description ) . '</p>';
				break;

			case Fraud_Outcome_Status::REVIEW:
				$icon_url        = plugins_url( 'assets/images/icons/shield-stroke-orange.svg', WCPAY_PLUGIN_FILE );
				$icon_alt        = __( 'Orange shield outline', 'woocommerce-payments' );
				$status          = __( 'Held for review', 'woocommerce-payments' );
				$description     = __( 'The payment for this order was held for review by your risk filtering. You can review the details and determine whether to approve or block the payment.', 'woocommerce-payments' );
				$callout         = __( 'Review payment', 'woocommerce-payments' );
				$transaction_url = WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );
				echo '<p class="wcpay-fraud-risk-meta-review"><img src="' . esc_url( $icon_url ) . '" alt="' . esc_html( $icon_alt ) . '"> ' . esc_html( $status ) . '</p><p>' . esc_html( $description ) . '</p><a href="' . esc_url( $transaction_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $callout ) . '</a>';
				break;

			case Fraud_Outcome_Status::REVIEW_ALLOWED:
				$icon_url        = plugins_url( 'assets/images/icons/check-green.svg', WCPAY_PLUGIN_FILE );
				$icon_alt        = __( 'Green check mark', 'woocommerce-payments' );
				$status          = __( 'Held for review', 'woocommerce-payments' );
				$description     = __( 'Need text to display once allowed.', 'woocommerce-payments' );
				$callout         = __( 'Review payment', 'woocommerce-payments' );
				$transaction_url = WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );
				echo '<p class="wcpay-fraud-risk-meta-allow"><img src="' . esc_url( $icon_url ) . '" alt="' . esc_html( $icon_alt ) . '"> ' . esc_html( $status ) . '</p><p>' . esc_html( $description ) . '</p><a href="' . esc_url( $transaction_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $callout ) . '</a>';
				break;

			case Fraud_Outcome_Status::BLOCK:
				// TODO: Are we going to cancel the orders? The note states we are, but if we do, we do not allow them to correct mistakes and could create a lot of additional orders.
				$icon_url        = plugins_url( 'assets/images/icons/shield-stroke-red.svg', WCPAY_PLUGIN_FILE );
				$icon_alt        = __( 'Red shield outline', 'woocommerce-payments' );
				$status          = __( 'Blocked', 'woocommerce-payments' );
				$description     = __( 'The payment for this order was blocked by your risk filtering, and the order has been cancelled.', 'woocommerce-payments' );
				$callout         = __( 'View payment details', 'woocommerce-payments' );
				$transaction_url = WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );
				// TODO: There is no transaction url returned due to there's no intent or charge ID.
				echo '<p class="wcpay-fraud-risk-meta-blocked"><img src="' . esc_url( $icon_url ) . '" alt="' . esc_html( $icon_alt ) . '"> ' . esc_html( $status ) . '</p><p>' . esc_html( $description ) . '</p><a href="' . esc_url( $transaction_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $callout ) . '</a>';
				break;

			case 'not_wcpay':
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
