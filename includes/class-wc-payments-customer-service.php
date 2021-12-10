<?php
/**
 * Class WC_Payments_Customer
 *
 * @package WooCommerce\Payments
 */

use WCPay\Exceptions\API_Exception;
use WCPay\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Class handling any customer functionality
 */
class WC_Payments_Customer_Service {
	/**
	 * Deprecated Stripe customer ID option.
	 *
	 * This option was used to store the customer_id in a WC_User options before we decoupled live and test customers.
	 */
	const DEPRECATED_WCPAY_CUSTOMER_ID_OPTION = '_wcpay_customer_id';

	/**
	 * Live Stripe customer ID option.
	 *
	 * This option is used to store new live mode customers in a WC_User options. Customers stored in the deprecated
	 * option are migrated to this one.
	 */
	const WCPAY_LIVE_CUSTOMER_ID_OPTION = '_wcpay_customer_id_live';

	/**
	 * Test Stripe customer ID option.
	 *
	 * This option is used to store new test mode customer IDs in a WC_User options.
	 */
	const WCPAY_TEST_CUSTOMER_ID_OPTION = '_wcpay_customer_id_test';

	/**
	 * Payment methods transient. Used in conjunction with the customer_id to cache a customer's payment methods.
	 */
	const PAYMENT_METHODS_TRANSIENT = 'wcpay_pm_';

	/**
	 * Key used to store customer id for non logged in users in WooCommerce Session.
	 */
	const CUSTOMER_ID_SESSION_KEY = 'wcpay_customer_id';

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * WC_Payments_Account instance to get information about the account
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * Class constructor
	 *
	 * @param WC_Payments_API_Client $payments_api_client Payments API client.
	 * @param WC_Payments_Account    $account             WC_Payments_Account instance.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client, WC_Payments_Account $account ) {
		$this->payments_api_client = $payments_api_client;
		$this->account             = $account;
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
			// Try to retrieve the customer id from the session if stored previously.
			$customer_id = WC()->session ? WC()->session->get( self::CUSTOMER_ID_SESSION_KEY ) : null;
			return is_string( $customer_id ) ? $customer_id : null;
		}

		$customer_id = get_user_option( $this->get_customer_id_option(), $user_id );

		// If customer_id is false it could mean that it hasn't been migrated from the deprecated key.
		if ( false === $customer_id ) {
			$this->maybe_migrate_deprecated_customer( $user_id );
			// Customer might've been migrated in maybe_migrate_deprecated_customer, so we need to fetch it again.
			$customer_id = get_user_option( $this->get_customer_id_option(), $user_id );
		}

		return $customer_id ? $customer_id : null;
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
		// Include the session ID for the user.
		$fraud_config                = $this->account->get_fraud_services_config();
		$customer_data['session_id'] = $fraud_config['sift']['session_id'] ?? null;

		// Create a customer on the WCPay server.
		$customer_id = $this->payments_api_client->create_customer( $customer_data );

		if ( $user->ID > 0 ) {
			$global = WC_Payments::is_network_saved_cards_enabled();
			$result = update_user_option( $user->ID, $this->get_customer_id_option(), $customer_id, $global );
			if ( ! $result ) {
				// Log the error, but continue since we have the customer ID we need.
				Logger::error( 'Failed to store new customer ID for user ' . $user->ID );
			}
		}

		// Save the customer id in the session for non logged in users to reuse it in payments.
		WC()->session->set( self::CUSTOMER_ID_SESSION_KEY, $customer_id );

		return $customer_id;
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
		try {
			// Update the customer on the WCPay server.
			$this->payments_api_client->update_customer(
				$customer_id,
				$customer_data
			);

			// We successfully updated the existing customer, so return the passed in ID unchanged.
			return $customer_id;
		} catch ( API_Exception $e ) {
			// If we failed to find the customer we wanted to update, then create a new customer and associate it to the
			// current user instead. This might happen if the customer was deleted from the server, the linked WCPay
			// account was changed, or if users were imported from another site.
			if ( $e->get_error_code() === 'resource_missing' ) {
				// Create a new customer to associate with this user. We'll return the new customer ID.
				return $this->recreate_customer( $user, $customer_data );
			}

			// For any other type of exception, just re-throw.
			throw $e;
		}
	}

	/**
	 * Sets a payment method as default for a customer.
	 *
	 * @param string $customer_id       The customer ID.
	 * @param string $payment_method_id The payment method ID.
	 */
	public function set_default_payment_method_for_customer( $customer_id, $payment_method_id ) {
		$this->payments_api_client->update_customer(
			$customer_id,
			[
				'invoice_settings' => [
					'default_payment_method' => $payment_method_id,
				],
			]
		);
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
		if ( ! $customer_id ) {
			return [];
		}

		$cache_payment_methods = ! WC_Payments::is_network_saved_cards_enabled();
		$transient_key         = self::PAYMENT_METHODS_TRANSIENT . $customer_id . '_' . $type;

		if ( $cache_payment_methods ) {
			$payment_methods = get_transient( $transient_key );
			if ( is_array( $payment_methods ) ) {
				return $payment_methods;
			}
		}

		try {
			$payment_methods = $this->payments_api_client->get_payment_methods( $customer_id, $type )['data'];
			if ( $cache_payment_methods ) {
				set_transient( $transient_key, $payment_methods, DAY_IN_SECONDS );
			}
			return $payment_methods;

		} catch ( API_Exception $e ) {
			// If we failed to find the we can simply return empty payment methods as this customer will
			// be recreated when the user succesfuly adds a payment method.
			if ( $e->get_error_code() === 'resource_missing' ) {
				return [];
			}

			// Rethrow for error codes we don't care about in this function.
			throw $e;
		}
	}

	/**
	 * Updates a customer payment method.
	 *
	 * @param string   $payment_method_id The payment method ID.
	 * @param WC_Order $order             Order to be used on the update.
	 */
	public function update_payment_method_with_billing_details_from_order( $payment_method_id, $order ) {
		$billing_details = WC_Payments_Utils::get_billing_details_from_order( $order );

		if ( ! empty( $billing_details ) ) {
			$this->payments_api_client->update_payment_method(
				$payment_method_id,
				[
					'billing_details' => $billing_details,
				]
			);
		}
	}

	/**
	 * Clear payment methods cache for a user.
	 *
	 * @param int $user_id WC user ID.
	 */
	public function clear_cached_payment_methods_for_user( $user_id ) {
		if ( WC_Payments::is_network_saved_cards_enabled() ) {
			return; // No need to do anything, payment methods will never be cached in this case.
		}
		$customer_id = $this->get_customer_id_by_user_id( $user_id );
		foreach ( WC_Payments::get_gateway()->get_upe_enabled_payment_method_ids() as $type ) {
			delete_transient( self::PAYMENT_METHODS_TRANSIENT . $customer_id . '_' . $type );
		}
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
	public static function map_customer_data( WC_Order $wc_order = null, WC_Customer $wc_customer = null ): array {
		if ( null === $wc_customer && null === $wc_order ) {
			return [];
		}

		// Where available, the order data takes precedence over the customer.
		$object_to_parse = $wc_order ?? $wc_customer;
		$name            = $object_to_parse->get_billing_first_name() . ' ' . $object_to_parse->get_billing_last_name();
		$description     = '';
		if ( null !== $wc_customer && ! empty( $wc_customer->get_username() ) ) {
			// We have a logged in user, so add their username to the customer description.
			// translators: %1$s Name, %2$s Username.
			$description = sprintf( __( 'Name: %1$s, Username: %2$s', 'woocommerce-payments' ), $name, $wc_customer->get_username() );
		} else {
			// Current user is not logged in.
			// translators: %1$s Name.
			$description = sprintf( __( 'Name: %1$s, Guest', 'woocommerce-payments' ), $name );
		}

		$data = [
			'name'        => $name,
			'description' => $description,
			'email'       => $object_to_parse->get_billing_email(),
			'phone'       => $object_to_parse->get_billing_phone(),
			'address'     => [
				'line1'       => $object_to_parse->get_billing_address_1(),
				'line2'       => $object_to_parse->get_billing_address_2(),
				'postal_code' => $object_to_parse->get_billing_postcode(),
				'city'        => $object_to_parse->get_billing_city(),
				'state'       => $object_to_parse->get_billing_state(),
				'country'     => $object_to_parse->get_billing_country(),
			],
		];

		if ( ! empty( $object_to_parse->get_shipping_postcode() ) ) {
			$data['shipping'] = [
				'name'    => $object_to_parse->get_shipping_first_name() . ' ' . $object_to_parse->get_shipping_last_name(),
				'address' => [
					'line1'       => $object_to_parse->get_shipping_address_1(),
					'line2'       => $object_to_parse->get_shipping_address_2(),
					'postal_code' => $object_to_parse->get_shipping_postcode(),
					'city'        => $object_to_parse->get_shipping_city(),
					'state'       => $object_to_parse->get_shipping_state(),
					'country'     => $object_to_parse->get_shipping_country(),
				],
			];
		}

		return $data;
	}

	/**
	 * Recreates the customer for this user.
	 *
	 * @param WP_User $user          User to recreate a customer for.
	 * @param array   $customer_data Customer data.
	 *
	 * @return string The newly created customer's ID
	 *
	 * @throws API_Exception Error creating customer.
	 */
	private function recreate_customer( WP_User $user, array $customer_data ): string {
		if ( $user->ID > 0 ) {
			$result = delete_user_option( $user->ID, $this->get_customer_id_option() );
			if ( ! $result ) {
				// Log the error, but continue since we'll be trying to update this option in create_customer.
				Logger::error( 'Failed to delete old customer ID for user ' . $user->ID );
			}
		}

		return $this->create_customer_for_user( $user, $customer_data );
	}

	/**
	 * Returns the name of the customer option meta, taking test mode into account.
	 *
	 * @return string The customer ID option name.
	 */
	private function get_customer_id_option(): string {
		return WC_Payments::get_gateway()->is_in_test_mode()
			? self::WCPAY_TEST_CUSTOMER_ID_OPTION
			: self::WCPAY_LIVE_CUSTOMER_ID_OPTION;
	}

	/**
	 * Migrate any customer ID that might be in the DEPRECATED_WCPAY_CUSTOMER_ID_OPTION.
	 *
	 * @param int $user_id The user ID to look for a customer ID with.
	 */
	private function maybe_migrate_deprecated_customer( $user_id ) {
		$customer_id = get_user_option( self::DEPRECATED_WCPAY_CUSTOMER_ID_OPTION, $user_id );
		if ( false !== $customer_id ) {
			// A customer was found in the deprecated key. Migrate it to the appropriate one and delete the old meta.
			// If an account is live mode, we optimistically assume that the customer is live mode, to avoid losing
			// live mode customer data. If the account is not live mode, it can only have test mode objects, so we
			// can safely migrate them to the test key.
			// If is_live cannot be determined, default it to true to avoid considering a live account as test.
			$account_is_live    = null === $this->account->get_is_live() || $this->account->get_is_live();
			$customer_option_id = $account_is_live
				? self::WCPAY_LIVE_CUSTOMER_ID_OPTION
				: self::WCPAY_TEST_CUSTOMER_ID_OPTION;
			if ( update_user_option( $user_id, $customer_option_id, $customer_id ) ) {
				delete_user_option( $user_id, self::DEPRECATED_WCPAY_CUSTOMER_ID_OPTION );
			} else {
				Logger::error( 'Failed to store new customer ID for user ' . $user_id . '; legacy customer was kept.' );
			}
		}
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
		$customer_id = null;
		$user        = $order->get_user();

		if ( false !== $user ) {
			// Determine the customer making the payment, create one if we don't have one already.
			$customer_id = $this->get_customer_id_by_user_id( $user->ID );

			if ( null === $customer_id ) {
				$customer_data = self::map_customer_data( $order, new WC_Customer( $user->ID ) );
				$customer_id   = $this->create_customer_for_user( $user, $customer_data );
			}
		}

		return $customer_id;
	}
}
