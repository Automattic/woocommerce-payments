<?php
/**
 * Class WCPay\Payment\State\Initial_State
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\State;

use WCPay\Exceptions\Process_Payment_Exception;
use WCPay\Fraud_Prevention\Fraud_Prevention_Service;
use WCPay\Payment\Payment;
use WCPay\Payment\Flags;
use WC_Order;
use WC_Payments;
use WC_Payments_Customer_Service;
use WC_Payments_Subscription_Service;
use WC_Payments_Subscriptions_Utilities;

/**
 * Represents the payment in its initial state,
 * before being fully set up and ready for processing.
 */
final class Initial_State extends Payment_State {
	use WC_Payments_Subscriptions_Utilities;

	/**
	 * The WCPay customer service.
	 *
	 * @var WC_Payments_Customer_Service
	 */
	protected $customer_service;

	/**
	 * Holds the fraud prevention service.
	 *
	 * @var Fraud_Prevention_Service
	 */
	protected $fraud_prevention_service;

	/**
	 * Instantiates the state and dependencies.
	 *
	 * @param Payment $payment The context of the state.
	 */
	public function __construct( Payment $payment ) {
		parent::__construct( $payment );

		// @todo: Use a proper dependency here.
		$this->customer_service         = WC_Payments::get_customer_service();
		$this->fraud_prevention_service = Fraud_Prevention_Service::get_instance();
	}

	/**
	 * Verifies the details of the payment.
	 * If successful, transitions to a verified state.
	 */
	public function prepare() {
		try {
			// Require order.
			$this->prepare_metadata( $this->context, $this->context->get_order() );
			$this->prepare_customer_data( $this->context );

			// Entry points should set a non-null token to enable checks.
			if (
				is_string( $this->context->get_fraud_prevention_token() )
				// The service might not be available if the WC session is not initialized.
				&& $this->fraud_prevention_service
				// We need the service to be not just there, but enabled.
				&& $this->fraud_prevention_service->is_enabled()
			) {
				$this->verify_fraud_token( $this->context->get_fraud_prevention_token() );
			}

			$this->context->switch_state( new Prepared_State( $this->context ) );
		} catch ( Process_Payment_Exception $e ) {
			$this->context->switch_state( new Failed_Preparation_State( $this->context ) );
		}
	}

	/**
	 * Prepares the metadata, needed for the payment.
	 *
	 * @param Payment  $payment The payment object.
	 * @param WC_Order $order   The order that requires payment.
	 */
	public function prepare_metadata( Payment $payment, WC_Order $order ) {
		$name     = sanitize_text_field( $order->get_billing_first_name() ) . ' ' . sanitize_text_field( $order->get_billing_last_name() );
		$email    = sanitize_email( $order->get_billing_email() );
		$metadata = [
			'customer_name'  => $name,
			'customer_email' => $email,
			'site_url'       => esc_url( get_site_url() ),
			'order_id'       => $order->get_id(),
			'order_number'   => $order->get_order_number(),
			'order_key'      => $order->get_order_key(),
			'payment_type'   => $payment->is( Flags::RECURRING ) ? 'recurring' : 'single',
		];

		// If the order belongs to a WCPay Subscription, set the payment context
		// to 'wcpay_subscription' (this helps with associating which fees belong to orders).
		if ( $payment->is( Flags::RECURRING ) && ! $this->is_subscriptions_plugin_active() ) {
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
		 * @param array     $metadata Array of meta data for the payment.
		 * @param WC_Order  $order    Order, which the payment belongs to.
		 * @param Payment   $this->context  Complete payment object.
		 */
		$metadata = apply_filters( 'wcpay_metadata_from_order', $metadata, $order, $payment );

		// Store within the payment.
		$payment->set_metadata( $metadata );
	}

	/**
	 * Prepares the customer data.
	 *
	 * @param Payment $payment The payment object.
	 */
	public function prepare_customer_data( Payment $payment ) {
		$user = $payment->get_order()->get_user();
		if ( false === $user ) {
			// Default to the current user when the order is not associated.
			$user = wp_get_current_user();
		}

		// Determine the customer making the payment, create one if we don't have one already.
		$customer_id = $this->customer_service->get_customer_id_by_user_id( $user->ID );

		if ( $customer_id ) {
			$payment->set_user_id( $user->ID );
			$payment->set_customer_id( $customer_id );
		}
	}

	/**
	 * While collecting data for a payment, checks if it should be prevented altogether.
	 *
	 * @param  string $token The token to verify.
	 * @throws Process_Payment_Exception In case the token could not be verified.
	 */
	public function verify_fraud_token( string $token ) {
		if ( $this->fraud_prevention_service->verify_token( $token ) ) {
			return;
		}

		throw new Process_Payment_Exception(
			__( 'We are not able to process this payment. Please refresh the page and try again.', 'woocommerce-payments' ),
			'fraud_prevention_enabled'
		);
	}
}
