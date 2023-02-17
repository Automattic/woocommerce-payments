<?php
namespace WCPay\Payment_Process\Step;

use WC_Customer;
use WC_Payments;
use WC_Payments_Customer_Service;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;

class Customer_Details_Step extends Abstract_Step {
	protected $customer_service;
	protected $user;
	protected $customer_id;

	public function __construct() {
		// @todo: Use a proper dependency here.
		$this->customer_service = WC_Payments::get_customer_service();
	}

	public function get_id() {
		return 'customer-details';
	}

	public function is_applicable( Payment $payment ) {
		return $payment instanceof Order_Payment;
	}

	public function collect_data( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return; // Keep IDEs happy.
		}

		$this->user = $payment->get_order()->get_user();
		if ( false === $this->user ) {
			// Default to the current user when the order is not associated.
			$this->user = wp_get_current_user();
		}

		// Determine the customer making the payment, create one if we don't have one already.
		$this->customer_id = $this->customer_service->get_customer_id_by_user_id( $this->user->ID );

		$payment->set_var( 'user_id', $this->user->ID );
		$payment->set_var( 'customer_id', $this->customer_id );
	}

	public function action( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return; // Keep IDEs happy.
		}

		$order = $payment->get_order();

		if ( null === $this->customer_id ) {
			// Create a new customer.
			$customer_data = WC_Payments_Customer_Service::map_customer_data( $order, new WC_Customer( $this->user->ID ) );
			$this->customer_id = $this->customer_service->create_customer_for_user( $this->user, $customer_data );
			$payment->set_var( 'customer_id', $this->customer_id );
		} else { // carried over from WC_Payment_Gateway_WCPay::manage_customer_details_for_order().
			// Update the existing customer with the current order details.
			$customer_data = WC_Payments_Customer_Service::map_customer_data( $order, new WC_Customer( $this->user->ID ) );
			$this->customer_service->update_customer_for_user( $this->customer_id, $this->user, $customer_data );
		}
	}
}
