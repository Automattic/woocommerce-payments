<?php
/**
 * Class Verified_State
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\State;

use WCPay\Payment\Payment;
use WC_Customer;
use WC_Payments;
use WC_Payments_Customer_Service;
use WCPay\Payment\Strategy\Strategy;

/**
 * Represents a payment in the verified state, meaning the payment is ready to process.
 */
final class Verified_State extends Payment_State {
	/**
	 * The WCPay customer service.
	 *
	 * @var WC_Payments_Customer_Service
	 */
	protected $customer_service;

	/**
	 * Instantiates the state and dependencies.
	 *
	 * @param Payment $payment The context of the state.
	 */
	public function __construct( Payment $payment ) {
		parent::__construct( $payment );

		// @todo: Use a proper dependency here.
		$this->customer_service = WC_Payments::get_customer_service();
	}

	/**
	 * Processes the payment.
	 *
	 * @param Strategy $strategy The strategy for processing the payment.
	 */
	public function process( Strategy $strategy ) {
		// Before proceeding with the strategy, update customer details.
		$this->update_customer_details();

		// Proceed with the strategy.
		$next_state = $strategy->process( $this->context );
		$this->context->switch_state( $next_state );
	}

	/**
	 * Updates customer details before processing intents.
	 */
	public function update_customer_details() {
		$order       = $this->context->get_order();
		$user        = get_user_by( 'id', $this->context->get_user_id() );
		$customer_id = $this->context->get_customer_id();

		if ( null === $customer_id ) {
			// Create a new customer.
			$customer_data = WC_Payments_Customer_Service::map_customer_data( $order, new WC_Customer( $user->ID ) );
			$customer_id   = $this->customer_service->create_customer_for_user( $user, $customer_data );
			$this->context->set_customer_id( $customer_id );
		} else { // carried over from WC_Payment_Gateway_WCPay::manage_customer_details_for_order().
			// Update the existing customer with the current order details.
			$customer_data = WC_Payments_Customer_Service::map_customer_data( $order, new WC_Customer( $user->ID ) );
			$this->customer_service->update_customer_for_user( $customer_id, $user, $customer_data );
		}
	}
}
