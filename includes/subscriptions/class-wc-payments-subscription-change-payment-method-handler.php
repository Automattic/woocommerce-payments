<?php
/**
 * Class WC_Payments_Subscription_Change_Payment_Method
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class handling any WCPay subscription change payment method functionality.
 */
class WC_Payments_Subscription_Change_Payment_Method_Handler {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Add an "Update card" action to all WCPay billing subscriptions with a failed renewal order.
		add_filter( 'wcs_view_subscription_actions', [ $this, 'update_subscription_change_payment_button' ], 15, 2 );
		add_filter( 'woocommerce_can_subscription_be_updated_to_new-payment-method', [ $this, 'can_update_payment_method' ], 15, 2 );

		// Override the pay for order link on the order to redirect to a change payment method page.
		add_filter( 'woocommerce_my_account_my_orders_actions', [ $this, 'update_order_pay_button' ], 15, 2 );

		// Filter elements/messaging on the "Change payment method" page to reflect updating a WCPay billing card.
		add_filter( 'woocommerce_subscriptions_change_payment_method_page_title', [ $this, 'change_payment_method_page_title' ], 10, 2 );
		add_filter( 'woocommerce_subscriptions_change_payment_method_page_notice_message', [ $this, 'change_payment_method_page_notice' ], 10, 2 );

		// Fallback to redirecting all pay for order pages for WCPay billing invoices to the update card page.
		add_action( 'template_redirect', [ $this, 'redirect_pay_for_order_to_update_payment_method' ] );

		add_filter( 'woocommerce_change_payment_button_text', [ $this, 'change_payment_method_form_submit_text' ] );
	}

	/**
	 * Replaces the default change payment method action for WC Pay subscriptions when the subscription needs a new payment method after a failed attempt.
	 *
	 * @param array           $actions The My Account > View Subscription actions.
	 * @param WC_Subscription $subscription The subscription object.
	 *
	 * @return array The subscription actions.
	 */
	public function update_subscription_change_payment_button( $actions, $subscription ) {
		if ( $this->does_subscription_need_payment_updated( $subscription ) ) {
			// Override any existing button on $actions['change_payment_method'] to show "Update Card" button.
			$actions['change_payment_method'] = [
				'url'  => $this->get_subscription_update_payment_url( $subscription ),
				'name' => __( 'Update payment method', 'woocommerce-payments' ),
			];
		}

		return $actions;
	}

	/**
	 * Updates the 'Pay' link displayed on the My Account > Orders or from a subscriptions related orders table, to make sure customers are directed to update their card.
	 *
	 * @param array    $actions Order actions.
	 * @param WC_Order $order   The WC Order object.
	 *
	 * @return array The order actions.
	 */
	public function update_order_pay_button( $actions, $order ) {
		// If the order isn't payable, there's nothing to update.
		if ( ! isset( $actions['pay'] ) ) {
			return $actions;
		}

		$invoice_id         = WC_Payments_Invoice_Service::get_order_invoice_id( $order );
		$updated_pay_action = false;

		// Don't show the default pay link for any WC Pay Subscription order because we don't want customer paying for them.
		if ( $invoice_id ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order, [ 'order_type' => 'any' ] );

			if ( ! empty( $subscriptions ) ) {
				$subscription = array_pop( $subscriptions );

				if ( $subscription && WC_Payments_Invoice_Service::get_pending_invoice_id( $subscription ) ) {
					$actions['pay']['url'] = $this->get_subscription_update_payment_url( $subscription );
					$updated_pay_action    = true;
				}
			}

			if ( ! $updated_pay_action ) {
				unset( $actions['pay'] );
			}
		}

		return $actions;
	}

	/**
	 * Filters subscription `can_be_updated_to( 'new-payment-method' )` calls to allow customers to update their subscription's payment method.
	 *
	 * @param bool            $can_update   Whether the subscription's payment method can be updated.
	 * @param WC_Subscription $subscription The WC Subscription object.
	 *
	 * @return bool Whether the subscription's payment method can be updated.
	 */
	public function can_update_payment_method( bool $can_update, WC_Subscription $subscription ) {
		return $this->does_subscription_need_payment_updated( $subscription ) ? true : $can_update;
	}

	/**
	 * Redirects customers to update their payment method rather than pay for a WC Pay Subscription's failed order.
	 */
	public function redirect_pay_for_order_to_update_payment_method() {
		global $wp;

		if ( isset( $_GET['pay_for_order'], $_GET['key'] ) && empty( $_GET['change_payment_method'] ) && ( isset( $_GET['order_id'] ) || isset( $wp->query_vars['order-pay'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// Check if the order is linked to a billing invoice.
			$order_id = ( isset( $wp->query_vars['order-pay'] ) ) ? absint( $wp->query_vars['order-pay'] ) : absint( $_GET['order_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order    = wc_get_order( $order_id );

			if ( $order && $order instanceof WC_Order ) {
				$invoice_id = WC_Payments_Invoice_Service::get_order_invoice_id( $order );

				if ( $invoice_id ) {
					$subscriptions = wcs_get_subscriptions_for_order( $order, [ 'order_type' => 'any' ] );

					if ( ! empty( $subscriptions ) ) {
						$subscription = array_pop( $subscriptions );

						if ( $subscription && WC_Payments_Invoice_Service::get_pending_invoice_id( $subscription ) ) {
							wp_safe_redirect( $this->get_subscription_update_payment_url( $subscription ) );
							exit;
						}
					}
				}
			}
		}
	}

	/**
	 * Modifies the change payment method page title (and page breadcrumbs) when updating card details for WC Pay subscriptions.
	 *
	 * @param string          $title        The default page title.
	 * @param WC_Subscription $subscription The WC Subscription object.
	 *
	 * @return string The page title.
	 */
	public function change_payment_method_page_title( string $title, WC_Subscription $subscription ) {
		if ( $this->does_subscription_need_payment_updated( $subscription ) ) {
			$title = __( 'Update payment details', 'woocommerce-payments' );
		}

		return $title;
	}

	/**
	 * Modifies the message shown on the change payment method page.
	 *
	 * @param string          $message      The default customer notice shown on the change payment method page.
	 * @param WC_Subscription $subscription The Subscription.
	 *
	 * @return string The customer notice shown on the change payment method page.
	 */
	public function change_payment_method_page_notice( string $message, WC_Subscription $subscription ) {
		if ( $this->does_subscription_need_payment_updated( $subscription ) ) {
			$message = __( "Your subscription's last renewal failed payment. Please update your payment details so we can reattempt payment.", 'woocommerce-payments' );
		}

		return $message;
	}

	/**
	 * Checks if a subscription needs to update it's WCPay payment method.
	 *
	 * @param WC_Subscription $subscription The WC Subscription object.
	 * @return bool Whether the subscription's last order failed and needs a new updated payment method.
	 */
	private function does_subscription_need_payment_updated( $subscription ) {
		// We're only interested in WC Pay subscriptions that are on hold due to a failed payment.
		if ( ! $subscription->has_status( 'on-hold' ) || ! WC_Payments_Subscription_Service::get_wcpay_subscription_id( $subscription ) ) {
			return false;
		}

		$last_order = $subscription->get_last_order( 'all', 'any' );

		return $last_order && $last_order->has_status( 'failed' );
	}

	/**
	 * Generates the URL for the WC Pay Subscription's update payment method screen.
	 *
	 * @param WC_Subscription $subscription The WC Subscription object.
	 * @return string The update payment method
	 */
	private function get_subscription_update_payment_url( $subscription ) {
		return add_query_arg(
			[
				'change_payment_method' => $subscription->get_id(),
				'_wpnonce'              => wp_create_nonce(),
			],
			$subscription->get_checkout_payment_url()
		);
	}

	/**
	 * Modifies the change payment method form submit button to include language about retrying payment if there's a failed order.
	 *
	 * @param string $button_text The change subscription payment method button text.
	 * @return string The change subscription payment method button text.
	 */
	public function change_payment_method_form_submit_text( $button_text ) {

		if ( isset( $_GET['change_payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$subscription = wcs_get_subscription( wc_clean( wp_unslash( $_GET['change_payment_method'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification

			if ( $subscription && $this->does_subscription_need_payment_updated( $subscription ) ) {
				$button_text = __( 'Update and retry payment', 'woocommerce-payments' );
			}
		}

		return $button_text;
	}
}
