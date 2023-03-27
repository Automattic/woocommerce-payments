<?php
/**
 * Class Customer_Details_Step
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use WP_User;
use WC_Customer;
use WC_Payments;
use WC_Payments_Customer_Service;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;

/**
 * Collects and prepares customer and user data.
 */
class Customer_Details_Step extends Abstract_Step {
	/**
	 * The WCPay customer service.
	 *
	 * @var WC_Payments_Customer_Service
	 */
	protected $customer_service;

	/**
	 * A user, associated with the payment.
	 *
	 * @var WP_User
	 */
	protected $user;

	/**
	 * The WCPay server customer ID.
	 *
	 * @var string
	 */
	protected $customer_id;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		// @todo: Use a proper dependency here.
		$this->customer_service = WC_Payments::get_customer_service();
	}

	/**
	 * Returns the ID of the step.
	 */
	public function get_id() {
		return 'customer-details';
	}

	/**
	 * Checks if the step is applicable.
	 *
	 * @param Payment $payment A payment, which is being processed.
	 * @return bool
	 */
	public function is_applicable( Payment $payment ) {
		return $payment instanceof Order_Payment;
	}

	/**
	 * Collects data before any actual actions.
	 *
	 * @param Payment $payment A payment, which is being processed.
	 */
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

		$payment->set_user_id( $this->user->ID );
		$payment->set_customer_id( $this->customer_id );
	}

	/**
	 * Performs the necessary payment actions.
	 *
	 * @param Payment $payment A payment, which is being processed.
	 */
	public function action( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return; // Keep IDEs happy.
		}

		$order = $payment->get_order();

		// In case the object was destroyed in-between `collect_data` and `action`.
		if ( ! $this->user ) {
			$this->user = get_user_by( 'id', $payment->get_user_id() );
		}

		if ( null === $this->customer_id ) {
			// Create a new customer.
			$customer_data     = WC_Payments_Customer_Service::map_customer_data( $order, new WC_Customer( $this->user->ID ) );
			$this->customer_id = $this->customer_service->create_customer_for_user( $this->user, $customer_data );
			$payment->set_customer_id( $this->customer_id );
		} else { // carried over from WC_Payment_Gateway_WCPay::manage_customer_details_for_order().
			// Update the existing customer with the current order details.
			$customer_data = WC_Payments_Customer_Service::map_customer_data( $order, new WC_Customer( $this->user->ID ) );
			$this->customer_service->update_customer_for_user( $this->customer_id, $this->user, $customer_data );
		}
	}
}
