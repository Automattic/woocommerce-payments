<?php
/**
 * Class file for WCPay\Core\WC_Payments_Customer_Service_API.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core;

use WCPay\Exceptions\API_Exception;
use WC_Payments_Customer_Service;
use WP_User;
use WC_Customer;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Class handling any customer functionality
 */
class WC_Payments_Customer_Service_API {

	/**
	 * Internal Customer_Service instance to invoke.
	 *
	 * @var WC_Payments_Customer_Service
	 */
	private $customer_service;

	/**
	 * Class constructor
	 *
	 * @param WC_Payments_Customer_Service $customer_service Customer Service instance.
	 */
	public function __construct( WC_Payments_Customer_Service $customer_service ) {
		$this->customer_service = $customer_service;
	}

	/**
	 * Get WCPay customer ID for the given WordPress user ID
	 *
	 * @param int $user_id The user ID to look for a customer ID with.
	 *
	 * @return string|null WCPay customer ID or null if not found.
	 */
	public function get_customer_id_by_user_id( $user_id ) {
		return $this->customer_service->get_customer_id_by_user_id( $user_id );
	}

	/**
	 * Create a customer and associate it with a WordPress user.
	 *
	 * @param WP_User $user          User to create a customer for.
	 * @param array   $customer_data Customer data.
	 *
	 * @return string The created customer's ID
	 *
	 * @throws API_Exception Error creating customer.
	 */
	public function create_customer_for_user( WP_User $user, array $customer_data ): string {
		return $this->customer_service->create_customer_for_user( $user, $customer_data );
	}

	/**
	 * Update the customer details held on the WCPay server associated with the given WordPress user.
	 *
	 * @param string  $customer_id WCPay customer ID.
	 * @param WP_User $user        WordPress user.
	 * @param array   $customer_data Customer data.
	 *
	 * @return string The updated customer's ID. Can be different to the ID parameter if the customer was re-created.
	 *
	 * @throws API_Exception Error updating the customer.
	 */
	public function update_customer_for_user( string $customer_id, WP_User $user, array $customer_data ): string {
		return $this->customer_service->update_customer_for_user( $customer_id, $user, $customer_data );
	}

	/**
	 * Sets a payment method as default for a customer.
	 *
	 * @param string $customer_id       The customer ID.
	 * @param string $payment_method_id The payment method ID.
	 */
	public function set_default_payment_method_for_customer( $customer_id, $payment_method_id ) {
		return $this->customer_service->set_default_payment_method_for_customer( $customer_id, $payment_method_id );
	}

	/**
	 * Gets all payment methods for a customer.
	 *
	 * @param string $customer_id The customer ID.
	 * @param string $type        Type of payment methods to fetch.
	 *
	 * @throws API_Exception We only handle 'resource_missing' code types and rethrow anything else.
	 */
	public function get_payment_methods_for_customer( $customer_id, $type = 'card' ) {
		return $this->customer_service->get_payment_methods_for_customer( $customer_id, $type );
	}

	/**
	 * Updates a customer payment method.
	 *
	 * @param string   $payment_method_id The payment method ID.
	 * @param WC_Order $order             Order to be used on the update.
	 */
	public function update_payment_method_with_billing_details_from_order( $payment_method_id, $order ) {
		return $this->customer_service->update_payment_method_with_billing_details_from_order( $payment_method_id, $order );
	}

	/**
	 * Clear payment methods cache for a user.
	 *
	 * @param int $user_id WC user ID.
	 */
	public function clear_cached_payment_methods_for_user( $user_id ) {
		return $this->customer_service->clear_cached_payment_methods_for_user( $user_id );
	}

	/**
	 * Given a WC_Order or WC_Customer, returns an array representing a Stripe customer object.
	 * At least one parameter has to not be null.
	 *
	 * @param WC_Order    $wc_order    The Woo order to parse.
	 * @param WC_Customer $wc_customer The Woo customer to parse.
	 *
	 * @return array Customer data.
	 */
	public static function map_customer_data( WC_Order $wc_order, WC_Customer $wc_customer ): array {
		return WC_Payments_Customer_Service::map_customer_data( $wc_order, $wc_customer );
	}

	/**
	 * Delete all saved payment methods that are stored inside database cache driver.
	 *
	 * @return void
	 */
	public function delete_cached_payment_methods() {
		$this->customer_service->delete_cached_payment_methods();
	}

	/**
	 * Get the WCPay customer ID associated with an order, or create one if none found.
	 *
	 * @param WC_Order $order WC Order object.
	 *
	 * @return string|null    WCPay customer ID.
	 * @throws API_Exception  If there's an error creating customer.
	 */
	public function get_customer_id_for_order( $order ) {
		return $this->customer_service->get_customer_id_for_order( $order );
	}

	/**
	 * Updates the given user with the given WooPayments
	 * customer ID.
	 *
	 * @param int    $user_id     The WordPress user ID.
	 * @param string $customer_id The WooPayments customer ID.
	 */
	public function update_user_customer_id( int $user_id, string $customer_id ) {
		$this->customer_service->update_user_customer_id( $user_id, $customer_id );
	}

	/**
	 * Adds the WooComerce Payments customer ID found in the user session
	 * to the WordPress user as metadata.
	 *
	 * @param int $user_id The WordPress user ID.
	 */
	public function add_customer_id_to_user( $user_id ) {
		$this->customer_service->add_customer_id_to_user( $user_id );
	}

	/**
	 * Prepares customer data to be used on 'Pay for Order' or 'Add Payment Method' pages.
	 * Customer data is retrieved from order when on Pay for Order.
	 * Customer data is retrieved from customer when on 'Add Payment Method'.
	 *
	 * @return array|null An array with customer data or nothing.
	 */
	public function get_prepared_customer_data() {
		return $this->customer_service->get_prepared_customer_data();
	}
}
