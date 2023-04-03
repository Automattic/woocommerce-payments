<?php
/**
 * Payment_Verified class.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\State;

use WC_Payments;
use WC_Payments_Subscription_Service;
use WC_Payments_Subscriptions_Utilities;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;
use WCPay\Payment_Process\Payment_Method\Payment_Method_Factory;
use WCPay\Payment_Process\Storage\Payment_Storage;

/**
 * Represents payments in a state, which requires setup.
 */
class Payment_Verified extends Order_Payment {
	use WC_Payments_Subscriptions_Utilities;

	/**
	 * The WCPay customer service.
	 *
	 * @var WC_Payments_Customer_Service
	 */
	protected $customer_service;

	/**
	 * Instantiates the state, and sets up all required dependencies.
	 *
	 * @param Payment_Storage        $storage                Storage to load/save payments from/to.
	 * @param Payment_Method_Factory $payment_method_factory Factory for payment methods.
	 */
	public function __construct(
		Payment_Storage $storage,
		Payment_Method_Factory $payment_method_factory
	) {
		parent::__construct( $storage, $payment_method_factory );

		// Load local dependencies. This should be done better.
		$this->customer_service = WC_Payments::get_customer_service();
	}

	/**
	 * Prepares all details, needed before performing live actions on an order.
	 *
	 * @return Payment_Ready_To_Process
	 */
	public function prepare() {
		$this->prepare_intent_metadata();
		$this->prepare_customer_details();

		return new Payment_Ready_To_Process( $this->payment_storage, $this->payment_method_factory );
	}

	/**
	 * Collects the metadata from an order for a payment/setup intent.
	 */
	protected function prepare_intent_metadata() {
		$order = $this->get_order();

		$name     = sanitize_text_field( $order->get_billing_first_name() ) . ' ' . sanitize_text_field( $order->get_billing_last_name() );
		$email    = sanitize_email( $order->get_billing_email() );
		$metadata = [
			'customer_name'  => $name,
			'customer_email' => $email,
			'site_url'       => esc_url( get_site_url() ),
			'order_id'       => $order->get_id(),
			'order_number'   => $order->get_order_number(),
			'order_key'      => $order->get_order_key(),
			'payment_type'   => $this->is( Payment::RECURRING ) ? 'recurring' : 'single',
		];

		// If the order belongs to a WCPay Subscription, set the payment context
		// to 'wcpay_subscription' (this helps with associating which fees belong to orders).
		if ( $this->is( Payment::RECURRING ) && ! $this->is_subscriptions_plugin_active() ) {
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
		$metadata = apply_filters( 'wcpay_metadata_from_order', $metadata, $order, $this );

		// Store within the payment.
		$this->set_metadata( $metadata );
	}

	/**
	 * Collects data before any actual actions.
	 */
	protected function prepare_customer_details() {
		$user = $this->get_order()->get_user();
		if ( false === $user ) {
			// Default to the current user when the order is not associated.
			$user = wp_get_current_user(); // @todo: Remove this, allow the process to be async.
		}

		// Determine the customer making the payment, create one if we don't have one already.
		$customer_id = $this->customer_service->get_customer_id_by_user_id( $user->ID ); // @todo: Handle/document exceptions.

		$this->set_user_id( $user->ID );
		$this->set_customer_id( $customer_id );
	}
}
