<?php
/**
 * Class WC_Payments_Customer
 *
 * @package WooCommerce\Payments
 */

use WCPay\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Class handling any customer functionality
 */
class WC_Payments_Customer_Service {

	const WCPAY_CUSTOMER_ID_OPTION = '_wcpay_customer_id';

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * Class constructor
	 *
	 * @param WC_Payments_API_Client $payments_api_client Payments API client.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client ) {
		$this->payments_api_client = $payments_api_client;
	}

	/**
	 * Get WCPay customer ID for the given WordPress user ID
	 *
	 * @param int $user_id The user ID to look for a customer ID with.
	 *
	 * @return string|null WCPay customer ID or null if not found.
	 */
	public function get_customer_id_by_user_id( $user_id ) {
		// User ID might be 0 if fetched from a WP_User instance for a user who isn't logged in.
		if ( null === $user_id || 0 === $user_id ) {
			return null;
		}

		$customer_id = get_user_option( self::WCPAY_CUSTOMER_ID_OPTION, $user_id );
		if ( false === $customer_id ) {
			return null;
		}

		return $customer_id;
	}

	/**
	 * Create a customer and associate it with a WordPress user.
	 *
	 * @param WP_User $user  User to create a customer for.
	 * @param string  $name  Customer name.
	 * @param string  $email Customer email.
	 *
	 * @return string The created customer's ID
	 *
	 * @throws WC_Payments_API_Exception Error creating customer.
	 */
	public function create_customer_for_user( $user, $name, $email ) {
		$description = $this->build_description_string( $user, $name );

		// Create a customer on the WCPay server.
		$customer_id = $this->payments_api_client->create_customer( $name, $email, $description );

		if ( $user->ID > 0 ) {
			$result = update_user_option( $user->ID, self::WCPAY_CUSTOMER_ID_OPTION, $customer_id );
			if ( ! $result ) {
				// Log the error, but continue since we have the customer ID we need.
				Logger::error( 'Failed to store new customer ID for user ' . $user->ID );
			}
		}

		return $customer_id;
	}

	/**
	 * Update the customer details held on the WCPay server associated with the given WordPress user.
	 *
	 * @param string  $customer_id WCPay customer ID.
	 * @param WP_User $user        WordPress user.
	 * @param string  $name        Customer's full name.
	 * @param string  $email       Customer's email address.
	 *
	 * @return string The updated customer's ID. Can be different to the ID parameter if the customer was re-created.
	 *
	 * @throws WC_Payments_API_Exception Error updating the customer.
	 */
	public function update_customer_for_user( $customer_id, $user, $name, $email ) {
		$description = $this->build_description_string( $user, $name );

		try {
			// Update the customer on the WCPay server.
			$this->payments_api_client->update_customer( $customer_id, $name, $email, $description );

			// We successfully updated the existing customer, so return the passed in ID unchanged.
			return $customer_id;
		} catch ( WC_Payments_API_Exception $e ) {
			// If we failed to find the customer we wanted to update, then create a new customer and associate it to the
			// current user instead. This might happen if the customer was deleted from the server, the linked WCPay
			// account was changed, or if users were imported from another site.
			if ( $e->get_error_code() === 'resource_missing' ) {
				// Create a new customer to associate with this user. We'll return the new customer ID.
				return $this->recreate_customer( $user, $name, $email );
			}

			// For any other type of exception, just re-throw.
			throw $e;
		}
	}

	/**
	 * Build the customer description string.
	 *
	 * @param WP_User $user WordPress user.
	 * @param string  $name Customer's full name.
	 *
	 * @return string
	 */
	private function build_description_string( $user, $name ) {
		if ( $user->ID > 0 ) {
			// We have a logged in user, so add their username to the customer description.
			// translators: %1$s Name, %2$s Username.
			return sprintf( __( 'Name: %1$s, Username: %2$s', 'woocommerce-payments' ), $name, $user->user_login );
		} else {
			// Current user is not logged in.
			// translators: %1$s Name.
			return sprintf( __( 'Name: %1$s, Guest', 'woocommerce-payments' ), $name );
		}
	}

	/**
	 * Recreates the customer for this user.
	 *
	 * @param WP_User $user  User to recreate a customer for.
	 * @param string  $name  Customer name.
	 * @param string  $email Customer email.
	 *
	 * @return string The newly created customer's ID
	 *
	 * @throws WC_Payments_API_Exception Error creating customer.
	 */
	private function recreate_customer( $user, $name, $email ) {
		if ( $user->ID > 0 ) {
			$result = delete_user_option( $user->ID, self::WCPAY_CUSTOMER_ID_OPTION );
			if ( ! $result ) {
				// Log the error, but continue since we'll be trying to update this option in create_customer.
				Logger::error( 'Failed to delete old customer ID for user ' . $user->ID );
			}
		}

		return $this->create_customer_for_user( $user, $name, $email );
	}
}
