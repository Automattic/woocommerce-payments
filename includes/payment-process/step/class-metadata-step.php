<?php
/**
 * Class Metadata_Step
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use WC_Order;
use WC_Payments_Subscription_Service;
use WC_Payments_Subscriptions_Utilities;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;

/**
 * Prepares metadata from the order.
 */
class Metadata_Step extends Abstract_Step {
	use WC_Payments_Subscriptions_Utilities;

	/**
	 * Returns the ID of the step.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'metadata';
	}

	/**
	 * Checks if the step is applicable.
	 *
	 * @param Payment $payment A payment, which is being processed.
	 * @return bool
	 */
	public function is_applicable( Payment $payment ) {
		return is_a( $payment, Order_Payment::class );
	}

	/**
	 * Collects data after the payment action.
	 *
	 * @param Payment $payment A payment, which is being processed.
	 */
	public function collect_data( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return; // Make IDEs happy.
		}

		$order = $payment->get_order();

		$name     = sanitize_text_field( $order->get_billing_first_name() ) . ' ' . sanitize_text_field( $order->get_billing_last_name() );
		$email    = sanitize_email( $order->get_billing_email() );
		$metadata = [
			'customer_name'  => $name,
			'customer_email' => $email,
			'site_url'       => esc_url( get_site_url() ),
			'order_id'       => $order->get_id(),
			'order_number'   => $order->get_order_number(),
			'order_key'      => $order->get_order_key(),
			'payment_type'   => $payment->is( Payment::RECURRING ) ? 'recurring' : 'single',
		];

		// If the order belongs to a WCPay Subscription, set the payment context
		// to 'wcpay_subscription' (this helps with associating which fees belong to orders).
		if ( $payment->is( Payment::RECURRING ) && ! $this->is_subscriptions_plugin_active() ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order, [ 'order_type' => 'any' ] );

			foreach ( $subscriptions as $subscription ) {
				if ( WC_Payments_Subscription_Service::is_wcpay_subscription( $subscription ) ) {
					$metadata['payment_context'] = 'wcpay_subscription';
					break;
				}
			}
		}

		/**
		 * Allows the metadata to be modifeid before being set.
		 *
		 * @param array         $metadata Array of meta data for the payment.
		 * @param WC_Order      $order    Order, which the payment belongs to.
		 * @param Order_Payment $payment  Complete payment object.
		 */
		$metadata = apply_filters( 'wcpay_metadata_from_order', $metadata, $order, $payment );

		// Store within the payment.
		$payment->set_var( 'metadata', $metadata );
	}
}
