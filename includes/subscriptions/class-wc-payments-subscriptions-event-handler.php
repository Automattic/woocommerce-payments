<?php
/**
 * Class WC_Payments_Subscriptions_Event_Handler
 *
 * @package WooCommerce\Payments
 */

use WCPay\Exceptions\Rest_Request_Exception;

/**
 * Subscriptions Event/Webhook Handler class
 */
class WC_Payments_Subscriptions_Event_Handler {

	/**
	 * Maximum amount of payment retries to handle before cancelling the subscription.
	 *
	 * @var int
	 */
	const MAX_RETRIES = 4;

	/**
	 * Invoice Service.
	 *
	 * @var WC_Payments_Invoice_Service
	 */
	private $invoice_service;

	/**
	 * Subscription Service.
	 *
	 * @var WC_Payments_Subscription_Service
	 */
	private $subscription_service;

	/**
	 * Subscriptions event handler constructor.
	 *
	 * @param WC_Payments_Invoice_Service      $invoice_service      Invoice service.
	 * @param WC_Payments_Subscription_Service $subscription_service Subscription service.
	 */
	public function __construct( WC_Payments_Invoice_Service $invoice_service, WC_Payments_Subscription_Service $subscription_service ) {
		$this->invoice_service      = $invoice_service;
		$this->subscription_service = $subscription_service;
	}

	/**
	 * Validate and correct subscription status, date, and lines.
	 *
	 * @param array $body The event body that triggered the webhook.
	 *
	 * @throws Rest_Request_Exception Required parameters not found.
	 */
	public function handle_invoice_upcoming( array $body ) {
		$event_data            = $this->get_event_property( $body, 'data' );
		$event_object          = $this->get_event_property( $event_data, 'object' );
		$wcpay_subscription_id = $this->get_event_property( $event_object, 'subscription' );
		$wcpay_customer_id     = $this->get_event_property( $event_object, 'customer' );
		$wcpay_discounts       = $this->get_event_property( $event_object, 'discount' );
		$wcpay_lines           = $this->get_event_property( $event_object, 'lines' );
		$wcpay_lines           = $this->get_event_property( $wcpay_lines, 'data' );
		$subscription          = WC_Payments_Subscription_Service::get_subscription_from_wcpay_subscription_id( $wcpay_subscription_id );

		if ( ! $subscription ) {
			throw new Rest_Request_Exception( __( 'Cannot find subscription to handle the "invoice.upcoming" event.', 'woocommerce-payments' ) );
		}

		$wcpay_subscription = $this->subscription_service->get_wcpay_subscription( $subscription );

		// Suspend or cancel subscription if we didn't expect a next payment.
		if ( 0 === $subscription->get_time( 'next_payment' ) ) {
			// TODO: Add error handling to these {cancel/suspend}_subscription calls i.e. add a subscription order note if the WCPay subscription wasn't cancelled.
			if ( ! $subscription->has_status( 'on-hold' ) && 0 !== $subscription->get_time( 'end' ) ) {
				$this->subscription_service->cancel_subscription( $subscription );
			} else {
				$this->subscription_service->suspend_subscription( $subscription );
			}
		} else {
			// Translators: %s Scheduled/upcoming payment date in Y-m-d H:i:s format.
			$subscription->add_order_note( sprintf( __( 'Next automatic payment scheduled for %s.', 'woocommerce-payments' ), get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $wcpay_subscription['current_period_end'] ), wc_date_format() . ' ' . wc_time_format() ) ) );

			// Update the subscription in WC to match the WCPay Subscription's next payment date.
			$this->subscription_service->update_dates_to_match_wcpay_subscription( $wcpay_subscription, $subscription );
			$this->invoice_service->validate_invoice_items( $wcpay_lines, $wcpay_discounts, $subscription );
		}
	}

	/**
	 * Renews a subscription associated with paid invoice.
	 *
	 * @param array $body The event body that triggered the webhook.
	 *
	 * @throws Rest_Request_Exception Required parameters not found.
	 */
	public function handle_invoice_paid( array $body ) {
		$event_data            = $this->get_event_property( $body, 'data' );
		$event_object          = $this->get_event_property( $event_data, 'object' );
		$wcpay_subscription_id = $this->get_event_property( $event_object, 'subscription' );
		$wcpay_invoice_id      = $this->get_event_property( $event_object, 'id' );
		$subscription          = WC_Payments_Subscription_Service::get_subscription_from_wcpay_subscription_id( $wcpay_subscription_id );

		if ( ! $subscription ) {
			throw new Rest_Request_Exception( __( 'Cannot find subscription for the incoming "invoice.paid" event.', 'woocommerce-payments' ) );
		}

		// This incoming invoice.paid event is linked to the subscription parent invoice and can be ignored.
		if ( WC_Payments_Invoice_Service::get_subscription_invoice_id( $subscription ) === $wcpay_invoice_id ) {
			return;
		}

		$order = wc_get_order( WC_Payments_Invoice_Service::get_order_id_by_invoice_id( $wcpay_invoice_id ) );

		if ( ! $order ) {
			$order = wcs_create_renewal_order( $subscription );

			if ( is_wp_error( $order ) ) {
				throw new Rest_Request_Exception( __( 'Unable to generate renewal order for subscription on the "invoice.paid" event.', 'woocommerce-payments' ) );
			} else {
				$this->invoice_service->set_order_invoice_id( $order, $wcpay_invoice_id );
			}
		}

		if ( $order->needs_payment() ) {
			$order->payment_complete();
		}

		// Remove pending invoice ID in case one was recorded for previous failed renewal attempts.
		$this->invoice_service->mark_pending_invoice_paid_for_subscription( $subscription );
	}

	/**
	 * Marks a subscription payment associated with invoice as failed.
	 *
	 * @param array $body The event body that triggered the webhook.
	 *
	 * @throws Rest_Request_Exception Required parameters not found.
	 */
	public function handle_invoice_payment_failed( array $body ) {
		$event_data            = $this->get_event_property( $body, 'data' );
		$event_object          = $this->get_event_property( $event_data, 'object' );
		$wcpay_subscription_id = $this->get_event_property( $event_object, 'subscription' );
		$wcpay_invoice_id      = $this->get_event_property( $event_object, 'id' );
		$attempts              = (int) $this->get_event_property( $event_object, 'attempt_count' );
		$subscription          = WC_Payments_Subscription_Service::get_subscription_from_wcpay_subscription_id( $wcpay_subscription_id );

		if ( ! $subscription ) {
			throw new Rest_Request_Exception( __( 'Cannot find subscription for the incoming "invoice.upcoming" event.', 'woocommerce-payments' ) );
		}

		$order = wc_get_order( WC_Payments_Invoice_Service::get_order_id_by_invoice_id( $wcpay_invoice_id ) );

		if ( ! $order ) {
			$order = wcs_create_renewal_order( $subscription );

			if ( is_wp_error( $order ) ) {
				throw new Rest_Request_Exception( __( 'Unable to generate renewal order for subscription to record the incoming "invoice.payment_failed" event.', 'woocommerce-payments' ) );
			} else {
				$this->invoice_service->set_order_invoice_id( $order, $wcpay_invoice_id );
			}
		}

		// Translators: %d Number of failed renewal attempts.
		$subscription->add_order_note( sprintf( __( 'WCPay subscription renewal attempt %d failed.', 'woocommerce-payments' ), $attempts ) );

		if ( self::MAX_RETRIES > $attempts ) {
			remove_action( 'woocommerce_subscription_status_on-hold', [ $this->subscription_service, 'suspend_subscription' ] );
			$subscription->payment_failed();
			add_action( 'woocommerce_subscription_status_on-hold', [ $this->subscription_service, 'suspend_subscription' ] );
		} else {
			$subscription->payment_failed( 'cancelled' );
		}

		// Record invoice ID so we can trigger repayment on payment method update.
		$this->invoice_service->mark_pending_invoice_for_subscription( $subscription, $wcpay_invoice_id );
	}

	/**
	 * Gets the event data by property.
	 *
	 * @param array  $event_data Event data.
	 * @param string $key        Requested key.
	 *
	 * @return mixed
	 *
	 * @throws Rest_Request_Exception Event data not found by key.
	 */
	private function get_event_property( array $event_data, string $key ) {
		if ( ! isset( $event_data[ $key ] ) ) {
			// Translators: %s Property name not found in event data array.
			throw new Rest_Request_Exception( sprintf( __( '%s not found in array', 'woocommerce-payments' ), $key ) );
		}

		return $event_data[ $key ];
	}
}
