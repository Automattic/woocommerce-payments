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
use WCPay\Constants\Fraud_Meta_Box_Type;

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

		$intent_id     = $this->order_service->get_intent_id_for_order( $order );
		$charge_id     = $this->order_service->get_charge_id_for_order( $order );
		$meta_box_type = $this->order_service->get_fraud_meta_box_type_for_order( $order );

		if ( 'woocommerce_payments' !== $order->get_payment_method() ) {
			$meta_box_type = Fraud_Meta_Box_Type::NOT_WCPAY;
		}

		$icons = [
			'green_check_mark' => [
				'url' => plugins_url( 'assets/images/icons/check-green.svg', WCPAY_PLUGIN_FILE ),
				'alt' => __( 'Green check mark', 'woocommerce-payments' ),
			],
			'orange_shield'    => [
				'url' => plugins_url( 'assets/images/icons/shield-stroke-orange.svg', WCPAY_PLUGIN_FILE ),
				'alt' => __( 'Orange shield outline', 'woocommerce-payments' ),
			],
			'red_shield'       => [
				'url' => plugins_url( 'assets/images/icons/shield-stroke-red.svg', WCPAY_PLUGIN_FILE ),
				'alt' => __( 'Red shield outline', 'woocommerce-payments' ),
			],
		];

		$statuses = [
			'blocked'         => __( 'Blocked', 'woocommerce-payments' ),
			'held_for_review' => __( 'Held for review', 'woocommerce-payments' ),
			'no_action_taken' => __( 'No action taken', 'woocommerce-payments' ),
		];

		switch ( $meta_box_type ) {
			case Fraud_Meta_Box_Type::ALLOW:
				$description = __( 'The payment for this order passed your risk filtering.', 'woocommerce-payments' );
				echo '<p class="wcpay-fraud-risk-meta-allow"><img src="' . esc_url( $icons['green_check_mark']['url'] ) . '" alt="' . esc_html( $icons['green_check_mark']['alt'] ) . '"> ' . esc_html( $statuses['no_action_taken'] ) . '</p><p>' . esc_html( $description ) . '</p>';
				break;

			case Fraud_Meta_Box_Type::BLOCK:
				$description     = __( 'The payment for this order was blocked by your risk filtering. There is no pending authorization, and the order can be cancelled to reduce any held stock.', 'woocommerce-payments' );
				$callout         = __( 'View more details', 'woocommerce-payments' );
				$transaction_url = WC_Payments_Utils::compose_transaction_url( $order->get_id(), '' );
				echo '<p class="wcpay-fraud-risk-meta-blocked"><img src="' . esc_url( $icons['red_shield']['url'] ) . '" alt="' . esc_html( $icons['red_shield']['alt'] ) . '"> ' . esc_html( $statuses['blocked'] ) . '</p><p>' . esc_html( $description ) . '</p><a href="' . esc_url( $transaction_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $callout ) . '</a>';
				break;

			case Fraud_Meta_Box_Type::NOT_WCPAY:
				$description = __( 'Risk filtering is only available for orders that are processed with WooCommerce Payments.', 'woocommerce-payments' );
				$callout     = __( 'Learn more', 'woocommerce-payments' );
				$callout_url = '';
				// TODO: Need callout url for Learn more.
				echo '<p>' . esc_html( $description ) . '</p><a href="' . esc_url( $callout_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $callout ) . '</a>';
				break;

			case Fraud_Meta_Box_Type::PAYMENT_STARTED:
				$description = __( 'The payment for this order has not yet been passed to the fraud and risk filters to determine its outcome status.', 'woocommerce-payments' );
				echo '<p class="wcpay-fraud-risk-meta-review"><img src="' . esc_url( $icons['orange_shield']['url'] ) . '" alt="' . esc_html( $icons['orange_shield']['alt'] ) . '"> ' . esc_html( $statuses['no_action_taken'] ) . '</p><p>' . esc_html( $description ) . '</p>';
				break;

			case Fraud_Meta_Box_Type::REVIEW:
				$description     = __( 'The payment for this order was held for review by your risk filtering. You can review the details and determine whether to approve or block the payment.', 'woocommerce-payments' );
				$callout         = __( 'Review payment', 'woocommerce-payments' );
				$transaction_url = WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );
				echo '<p class="wcpay-fraud-risk-meta-review"><img src="' . esc_url( $icons['orange_shield']['url'] ) . '" alt="' . esc_html( $icons['orange_shield']['alt'] ) . '"> ' . esc_html( $statuses['held_for_review'] ) . '</p><p>' . esc_html( $description ) . '</p><a href="' . esc_url( $transaction_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $callout ) . '</a>';
				break;

			case Fraud_Meta_Box_Type::REVIEW_ALLOWED:
				$description     = __( 'This transaction was held for review by your risk filters, and the charge was manually approved after review.', 'woocommerce-payments' );
				$callout         = __( 'Review payment', 'woocommerce-payments' );
				$transaction_url = WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );
				echo '<p class="wcpay-fraud-risk-meta-allow"><img src="' . esc_url( $icons['green_check_mark']['url'] ) . '" alt="' . esc_html( $icons['green_check_mark']['alt'] ) . '"> ' . esc_html( $statuses['held_for_review'] ) . '</p><p>' . esc_html( $description ) . '</p><a href="' . esc_url( $transaction_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $callout ) . '</a>';
				break;

			case Fraud_Meta_Box_Type::REVIEW_CANCELLED:
				$description     = __( 'The payment for this order was held for review by your risk filtering. The charge appears to have been cancelled.', 'woocommerce-payments' );
				$callout         = __( 'Review payment', 'woocommerce-payments' );
				$transaction_url = WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );
				echo '<p class="wcpay-fraud-risk-meta-review"><img src="' . esc_url( $icons['orange_shield']['url'] ) . '" alt="' . esc_html( $icons['orange_shield']['alt'] ) . '"> ' . esc_html( $statuses['held_for_review'] ) . '</p><p>' . esc_html( $description ) . '</p><a href="' . esc_url( $transaction_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $callout ) . '</a>';
				break;

			case Fraud_Meta_Box_Type::REVIEW_EXPIRED:
				$description     = __( 'The payment for this order was held for review by your risk filtering. The authorization for the charge appears to have expired.', 'woocommerce-payments' );
				$callout         = __( 'Review payment', 'woocommerce-payments' );
				$transaction_url = WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );
				echo '<p class="wcpay-fraud-risk-meta-review"><img src="' . esc_url( $icons['orange_shield']['url'] ) . '" alt="' . esc_html( $icons['orange_shield']['alt'] ) . '"> ' . esc_html( $statuses['held_for_review'] ) . '</p><p>' . esc_html( $description ) . '</p><a href="' . esc_url( $transaction_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $callout ) . '</a>';
				break;

			default:
				$description = __( 'Risk filtering through WooCommerce Payments was not found on this order, it may have been created while filtering was not enabled.', 'woocommerce-payments' );
				echo '<p>' . esc_html( $description ) . '</p>';
				break;
		}
	}
}
