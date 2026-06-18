<?php
/**
 * Class Order_Fraud_And_Risk_Meta_Box
 *
 * @package WCPay\Fraud_Prevention
 */

namespace WCPay\Fraud_Prevention;

use WC_Payments_Admin_Settings;
use WC_Payments_Order_Service;
use WC_Payments_Utils;
use WCPay\Constants\Fraud_Meta_Box_Type;
use WCPay\Fraud_Prevention\Models\Rule;

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
		$this->order_service = $order_service;
	}

	/**
	 * Initializes this class's WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'add_meta_boxes', [ $this, 'maybe_add_meta_box' ] );
	}

	/**
	 * Maybe add the meta box.
	 */
	public function maybe_add_meta_box() {
		// If we cannot get the screen ID, exit.
		if ( ! function_exists( '\wc_get_page_screen_id' ) ) {
			return;
		}

		// Get the order edit screen to be able to add the meta box to.
		$wc_screen_id = \wc_get_page_screen_id( 'shop-order' );

		add_meta_box( 'wcpay-order-fraud-and-risk-meta-box', __( 'Fraud &amp; Risk', 'woocommerce-payments' ), [ $this, 'display_order_fraud_and_risk_meta_box_message' ], $wc_screen_id, 'side', 'default' );
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
		$meta_box_type  = $this->order_service->get_fraud_meta_box_type_for_order( $order );
		$risk_level     = $this->order_service->get_charge_risk_level_for_order( $order );
		$payment_method = $order->get_payment_method();

		if ( strstr( $payment_method, 'woocommerce_payments_' ) ) {
			$meta_box_type = Fraud_Meta_Box_Type::NOT_CARD;
		} elseif ( 'woocommerce_payments' !== $payment_method ) {
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
			'approved'        => __( 'Approved', 'woocommerce-payments' ),
			'held_for_review' => __( 'Held for review', 'woocommerce-payments' ),
			'no_action_taken' => __( 'No action taken', 'woocommerce-payments' ),
		];

		$risk_filters_callout          = __( 'Adjust risk filters', 'woocommerce-payments' );
		$risk_filters_url              = WC_Payments_Admin_Settings::get_settings_url( [ 'anchor' => '%23fp-settings' ] );
		$show_adjust_risk_filters_link = true;

		$this->maybe_print_risk_level_block( $risk_level );

		// MOCKUP (EFW #304): preview the Early Fraud Warning block. Remove when
		// the real EFW data path lands. EFW is orthogonal to the Radar outcome
		// below — an ALLOW'd charge can still get an EFW — so it prints as its
		// own additive block here, not as a fraud-outcome switch case.
		$this->maybe_print_early_fraud_warning_block( $order );

		echo '<div class="wcpay-fraud-risk-action">';

		switch ( $meta_box_type ) {
			case Fraud_Meta_Box_Type::ALLOW:
				$description = __( 'The payment for this order passed your risk filtering.', 'woocommerce-payments' );
				echo '<p class="wcpay-fraud-risk-meta-allow"><img src="' . esc_url( $icons['green_check_mark']['url'] ) . '" alt="' . esc_html( $icons['green_check_mark']['alt'] ) . '"> ' . esc_html( $statuses['no_action_taken'] ) . '</p><p>' . esc_html( $description ) . '</p>';
				break;

			case Fraud_Meta_Box_Type::BLOCK:
				$description     = __( 'The payment for this order was blocked by your risk filtering. There is no pending authorization, and the order can be cancelled to reduce any held stock.', 'woocommerce-payments' );
				$callout         = __( 'View more details', 'woocommerce-payments' );
				$transaction_url = $this->compose_transaction_url_with_tracking( $order->get_id(), '', Rule::FRAUD_OUTCOME_BLOCK );
				echo '<p class="wcpay-fraud-risk-meta-blocked"><img src="' . esc_url( $icons['red_shield']['url'] ) . '" alt="' . esc_html( $icons['red_shield']['alt'] ) . '"> ' . esc_html( $statuses['blocked'] ) . '</p><p>' . esc_html( $description ) . '</p><p><a href="' . esc_url( $transaction_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $callout ) . '</a></p>';
				break;

			case Fraud_Meta_Box_Type::NOT_CARD:
			case Fraud_Meta_Box_Type::NOT_WCPAY:
				$payment_method_title          = $order->get_payment_method_title();
				$show_adjust_risk_filters_link = false;

				if ( ! empty( $payment_method_title ) && 'Popular payment methods' !== $payment_method_title ) {
					$description = sprintf(
						/* translators: %1: WooPayments, %2: Payment method title */
						__( 'Risk filtering is only available for orders processed using credit cards with %1$s. This order was processed with %2$s.', 'woocommerce-payments' ),
						'WooPayments',
						$payment_method_title
					);
				} else {
					$description = sprintf(
						/* translators: %s: WooPayments */
						__( 'Risk filtering is only available for orders processed using credit cards with %s.', 'woocommerce-payments' ),
						'WooPayments'
					);
				}

				$callout     = __( 'Learn more', 'woocommerce-payments' );
				$callout_url = 'https://woocommerce.com/document/woopayments/fraud-and-disputes/fraud-protection/';
				$callout_url = add_query_arg( 'status_is', 'fraud-meta-box-not-wcpay-learn-more', $callout_url );
				echo '<p>' . esc_html( $description ) . '</p><p><a href="' . esc_url( $callout_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $callout ) . '</a></p>';
				break;

			case Fraud_Meta_Box_Type::PAYMENT_STARTED:
				$description = __( 'The payment for this order has not yet been passed to the fraud and risk filters to determine its outcome status.', 'woocommerce-payments' );
				echo '<p class="wcpay-fraud-risk-meta-review"><img src="' . esc_url( $icons['orange_shield']['url'] ) . '" alt="' . esc_html( $icons['orange_shield']['alt'] ) . '"> ' . esc_html( $statuses['no_action_taken'] ) . '</p><p>' . esc_html( $description ) . '</p>';
				break;

			case Fraud_Meta_Box_Type::REVIEW:
				$description     = __( 'The payment for this order was held for review by your risk filtering. You can review the details and determine whether to approve or block the payment.', 'woocommerce-payments' );
				$callout         = __( 'Review payment', 'woocommerce-payments' );
				$transaction_url = $this->compose_transaction_url_with_tracking( $intent_id, $charge_id, Rule::FRAUD_OUTCOME_REVIEW );
				echo '<p class="wcpay-fraud-risk-meta-review"><img src="' . esc_url( $icons['orange_shield']['url'] ) . '" alt="' . esc_html( $icons['orange_shield']['alt'] ) . '"> ' . esc_html( $statuses['held_for_review'] ) . '</p><p>' . esc_html( $description ) . '</p><p><a href="' . esc_url( $transaction_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $callout ) . '</a></p>';
				break;

			case Fraud_Meta_Box_Type::REVIEW_ALLOWED:
				$description = __( 'The payment for this order was held for review by your risk filtering and manually approved.', 'woocommerce-payments' );
				echo '<p class="wcpay-fraud-risk-meta-allow"><img src="' . esc_url( $icons['green_check_mark']['url'] ) . '" alt="' . esc_html( $icons['green_check_mark']['alt'] ) . '"> ' . esc_html( $statuses['approved'] ) . '</p><p>' . esc_html( $description ) . '</p>';
				break;

			case Fraud_Meta_Box_Type::REVIEW_BLOCKED:
				$description     = __( 'This transaction was held for review by your risk filters, and the charge was manually blocked after review.', 'woocommerce-payments' );
				$callout         = __( 'Review payment', 'woocommerce-payments' );
				$transaction_url = WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );
				echo '<p class="wcpay-fraud-risk-meta-blocked"><img src="' . esc_url( $icons['red_shield']['url'] ) . '" alt="' . esc_html( $icons['orange_shield']['alt'] ) . '"> ' . esc_html( $statuses['held_for_review'] ) . '</p><p>' . esc_html( $description ) . '</p><p><a href="' . esc_url( $transaction_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $callout ) . '</a></p>';
				break;

			case Fraud_Meta_Box_Type::REVIEW_EXPIRED:
				$description     = __( 'The payment for this order was held for review by your risk filtering. The authorization for the charge appears to have expired.', 'woocommerce-payments' );
				$callout         = __( 'Review payment', 'woocommerce-payments' );
				$transaction_url = WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );
				echo '<p class="wcpay-fraud-risk-meta-review"><img src="' . esc_url( $icons['orange_shield']['url'] ) . '" alt="' . esc_html( $icons['orange_shield']['alt'] ) . '"> ' . esc_html( $statuses['held_for_review'] ) . '</p><p>' . esc_html( $description ) . '</p><p><a href="' . esc_url( $transaction_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $callout ) . '</a></p>';
				break;

			case Fraud_Meta_Box_Type::REVIEW_FAILED:
				$description     = __( 'The payment for this order was held for review by your risk filtering. The authorization for the charge appears to have failed.', 'woocommerce-payments' );
				$callout         = __( 'Review payment', 'woocommerce-payments' );
				$transaction_url = WC_Payments_Utils::compose_transaction_url( $intent_id, $charge_id );
				echo '<p class="wcpay-fraud-risk-meta-review"><img src="' . esc_url( $icons['orange_shield']['url'] ) . '" alt="' . esc_html( $icons['orange_shield']['alt'] ) . '"> ' . esc_html( $statuses['held_for_review'] ) . '</p><p>' . esc_html( $description ) . '</p><p><a href="' . esc_url( $transaction_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $callout ) . '</a></p>';
				break;

			case Fraud_Meta_Box_Type::TERMINAL_PAYMENT:
				$description = __( 'The payment for this order was done in person and has bypassed your risk filtering.', 'woocommerce-payments' );
				echo '<p class="wcpay-fraud-risk-meta-allow"><img src="' . esc_url( $icons['green_check_mark']['url'] ) . '" alt="' . esc_html( $icons['green_check_mark']['alt'] ) . '"> ' . esc_html( $statuses['no_action_taken'] ) . '</p><p>' . esc_html( $description ) . '</p>';
				break;

			default:
				$description = sprintf(
					/* translators: %s: WooPayments */
					__( 'Risk filtering through %s was not found on this order, it may have been created while filtering was not enabled.', 'woocommerce-payments' ),
					'WooPayments'
				);
				echo '<p>' . esc_html( $description ) . '</p>';
				break;
		}

		if ( $show_adjust_risk_filters_link ) {
			echo '<p><a href="' . esc_url( $risk_filters_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $risk_filters_callout ) . '</a></p>';
		}

		echo '</div>';
	}

	/**
	 * Prints the risk level block.
	 *
	 * @param string $risk_level The risk level to display.
	 *
	 * @return void
	 */
	private function maybe_print_risk_level_block( $risk_level ) {
		$valid_risk_levels = [ 'normal', 'elevated', 'highest' ];

		if ( ! in_array( $risk_level, $valid_risk_levels, true ) ) {
			return;
		}

		$titles = [
			'normal'   => __( 'Normal', 'woocommerce-payments' ),
			'elevated' => __( 'Elevated', 'woocommerce-payments' ),
			'highest'  => __( 'High', 'woocommerce-payments' ),
		];

		$descriptions = [
			'normal'   => __( 'This payment shows a lower than normal risk of fraudulent activity.', 'woocommerce-payments' ),
			'elevated' => __( 'This order has a moderate risk of being fraudulent. We suggest contacting the customer to confirm their details before fulfilling it.', 'woocommerce-payments' ),
			'highest'  => __( 'This order has a high risk of being fraudulent. We suggest contacting the customer to confirm their details before fulfilling it.', 'woocommerce-payments' ),
		];

		echo '<div class="wcpay-fraud-risk-level wcpay-fraud-risk-level--' . esc_attr( $risk_level ) . '">';
		echo '<p class="wcpay-fraud-risk-level__title">' . esc_html( $titles[ $risk_level ] ) . '</p>';
		echo '<div class="wcpay-fraud-risk-level__bar"></div>';
		echo '<p>' . esc_html( $descriptions[ $risk_level ] ) . '</p>';
		echo '</div>';
	}

	/**
	 * MOCKUP (EFW #304): prints a preview of the Early Fraud Warning block.
	 *
	 * Fabricates an EFW scenario so the UI can be previewed before the real
	 * server-side EFW pipeline exists. Force a scenario with the `efw_mock`
	 * query param (`actionable`, `actionable_stolen`, `actionable_unauthorized`,
	 * `resolved`, `none`); otherwise it's derived deterministically from the
	 * order ID so each order is stable but the store shows variety.
	 *
	 * Remove this method (and its caller) when the real EFW feature lands.
	 *
	 * @param \WC_Order $order The order we are working with.
	 *
	 * @return void
	 */
	private function maybe_print_early_fraud_warning_block( $order ) {
		// Never let the mock alter test output (the metabox tests assert exact
		// echoed markup); only render it on real page loads.
		if ( defined( 'WCPAY_TEST_ENV' ) && WCPAY_TEST_ENV ) {
			return;
		}

		$scenario = $this->resolve_mock_early_fraud_warning_scenario( $order );

		if ( null === $scenario ) {
			return;
		}

		$fraud_type_labels = [
			'card_never_received'         => __( 'Card never received', 'woocommerce-payments' ),
			'fraudulent_card_application' => __( 'Fraudulent card application', 'woocommerce-payments' ),
			'made_with_counterfeit_card'  => __( 'Made with counterfeit card', 'woocommerce-payments' ),
			'made_with_lost_card'         => __( 'Made with lost card', 'woocommerce-payments' ),
			'made_with_stolen_card'       => __( 'Made with stolen card', 'woocommerce-payments' ),
			'misc'                        => __( 'Other', 'woocommerce-payments' ),
			'unauthorized_use_of_card'    => __( 'Unauthorized use of card', 'woocommerce-payments' ),
		];

		$actionable      = $scenario['actionable'];
		$fraud_type      = $scenario['fraud_type'];
		$fraud_type_text = $fraud_type_labels[ $fraud_type ] ?? '';

		$icon = $actionable
			? [
				'url' => plugins_url( 'assets/images/icons/shield-stroke-orange.svg', WCPAY_PLUGIN_FILE ),
				'alt' => __( 'Orange shield outline', 'woocommerce-payments' ),
			]
			: [
				'url' => plugins_url( 'assets/images/icons/check-green.svg', WCPAY_PLUGIN_FILE ),
				'alt' => __( 'Green check mark', 'woocommerce-payments' ),
			];

		$modifier = $actionable ? 'actionable' : 'resolved';
		$title    = $actionable
			? __( 'Early fraud warning', 'woocommerce-payments' )
			: __( 'Early fraud warning resolved', 'woocommerce-payments' );

		$description = $actionable
			? __( 'The card issuer flagged this payment as likely fraudulent. About 80% of early fraud warnings become disputes if no action is taken. Refunding now can prevent a dispute.', 'woocommerce-payments' )
			: __( 'This payment was refunded or disputed, so the warning is no longer actionable.', 'woocommerce-payments' );

		echo '<div class="wcpay-fraud-risk-efw wcpay-fraud-risk-efw--' . esc_attr( $modifier ) . '">';
		echo '<p class="wcpay-fraud-risk-efw__title"><img src="' . esc_url( $icon['url'] ) . '" alt="' . esc_attr( $icon['alt'] ) . '"> ' . esc_html( $title ) . '</p>';

		if ( '' !== $fraud_type_text ) {
			echo '<p class="wcpay-fraud-risk-efw__reason">' . sprintf(
				/* translators: %s is the card network's reported fraud reason, e.g. "Made with stolen card" */
				esc_html__( 'Reported reason: %s', 'woocommerce-payments' ),
				esc_html( $fraud_type_text )
			) . '</p>';
		}

		echo '<p>' . esc_html( $description ) . '</p>';

		if ( $actionable ) {
			// MOCKUP (EFW #304): the real CTA would launch the refund flow; this
			// anchors to the order items panel on this page, where Refund lives.
			echo '<p><a href="#woocommerce-order-items">' . esc_html__( 'Refund to avoid a dispute', 'woocommerce-payments' ) . '</a></p>';
		}

		echo '</div>';
	}

	/**
	 * MOCKUP (EFW #304): resolves which mock EFW scenario to render for an order.
	 *
	 * @param \WC_Order $order The order we are working with.
	 *
	 * @return array|null Scenario as [ 'actionable' => bool, 'fraud_type' => string ], or null for "no EFW".
	 */
	private function resolve_mock_early_fraud_warning_scenario( $order ) {
		$scenarios = [
			'none'                    => null,
			'actionable_stolen'       => [
				'actionable' => true,
				'fraud_type' => 'made_with_stolen_card',
			],
			'actionable_unauthorized' => [
				'actionable' => true,
				'fraud_type' => 'unauthorized_use_of_card',
			],
			'resolved'                => [
				'actionable' => false,
				'fraud_type' => 'made_with_stolen_card',
			],
		];

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only mock display toggle, no state change.
		$requested = isset( $_GET['efw_mock'] ) ? sanitize_text_field( wp_unslash( $_GET['efw_mock'] ) ) : '';
		if ( 'actionable' === $requested ) {
			$requested = 'actionable_stolen';
		}
		if ( '' !== $requested && array_key_exists( $requested, $scenarios ) ) {
			return $scenarios[ $requested ];
		}

		$pool = [ 'actionable_stolen', 'actionable_unauthorized', 'resolved', 'none' ];
		$pick = $pool[ crc32( (string) $order->get_id() ) % count( $pool ) ];

		return $scenarios[ $pick ];
	}

	/**
	 * Composes url for transaction details page.
	 *
	 * @param string $primary_id  Usually the Payment Intent ID, but can be an order ID.
	 * @param string $fallback_id Usually the Charge ID.
	 * @param string $status      The status we're wanting to add to the meta box tracking.
	 *
	 * @return string Transaction details page url with tracking.
	 */
	private function compose_transaction_url_with_tracking( $primary_id, $fallback_id, $status ) {
		return WC_Payments_Utils::compose_transaction_url(
			$primary_id,
			$fallback_id,
			[
				'status_is' => $status,
				'type_is'   => 'meta_box',
			]
		);
	}
}
