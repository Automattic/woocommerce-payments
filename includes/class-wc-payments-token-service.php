<?php
/**
 * WC_Payments_Payment_Tokens class
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WCPay\Logger;

/**
 * Handles and process WC payment tokens API.
 * Seen in checkout page and my account->add payment method page.
 */
class WC_Payments_Token_Service {

	const CUSTOMER_ID_META_KEY = '_wcpay_customer_id';
	const TEST_MODE_META_KEY   = '_wcpay_test_mode';

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * WC_Payments_Customer instance for working with customer information
	 *
	 * @var WC_Payments_Customer_Service
	 */
	private $customer_service;

	/**
	 * WC_Payments_Token_Service constructor.
	 *
	 * @param WC_Payments_API_Client       $payments_api_client Payments API client.
	 * @param WC_Payments_Customer_Service $customer_service Customer class instance.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client, WC_Payments_Customer_Service $customer_service ) {
		$this->payments_api_client = $payments_api_client;
		$this->customer_service    = $customer_service;

		add_action( 'woocommerce_payment_token_deleted', [ $this, 'woocommerce_payment_token_deleted' ], 10, 2 );
		add_action( 'woocommerce_payment_token_set_default', [ $this, 'woocommerce_payment_token_set_default' ], 10, 2 );
		add_filter( 'woocommerce_get_customer_payment_tokens', [ $this, 'woocommerce_get_customer_payment_tokens' ], 10, 3 );
	}

	/**
	 * Creates and add a token to an user, based on the payment_method object
	 *
	 * @param array   $payment_method Payment method to be added.
	 * @param WP_User $user           User to attach payment method to.
	 * @return WC_Payment_Token_CC    The WC object for the payment token.
	 */
	public function add_token_to_user( $payment_method, $user ) {
		// Clear cached payment methods.
		$this->customer_service->clear_cached_payment_methods_for_user( $user->ID );

		$token = new WC_Payment_Token_CC();
		$token->set_token( $payment_method['id'] );
		$token->set_gateway_id( WC_Payment_Gateway_WCPay::GATEWAY_ID );
		$token->set_card_type( strtolower( $payment_method['card']['brand'] ) );
		$token->set_last4( $payment_method['card']['last4'] );
		$token->set_expiry_month( $payment_method['card']['exp_month'] );
		$token->set_expiry_year( $payment_method['card']['exp_year'] );
		$token->set_user_id( $user->ID );
		$token->add_meta_data(
			self::CUSTOMER_ID_META_KEY,
			$this->customer_service->get_customer_id_by_user_id( $user->ID )
		);
		$token->add_meta_data(
			self::TEST_MODE_META_KEY,
			strval( WC_Payments::get_gateway()->is_in_test_mode() )
		);
		$token->save();

		return $token;
	}

	/**
	 * Adds a payment method to a user.
	 *
	 * @param string  $payment_method_id Payment method to be added.
	 * @param WP_User $user              User to attach payment method to.
	 * @return WC_Payment_Token_CC       The newly created token.
	 */
	public function add_payment_method_to_user( $payment_method_id, $user ) {
		$payment_method_object = $this->payments_api_client->get_payment_method( $payment_method_id );
		return $this->add_token_to_user( $payment_method_object, $user );
	}

	/**
	 * Filters tokens based on customer ID and imports saved tokens from API if they don't already exist in WooCommerce.
	 *
	 * @param array  $tokens     Array of tokens.
	 * @param string $user_id    WC user ID.
	 * @param string $gateway_id WC gateway ID.
	 * @return array
	 */
	public function woocommerce_get_customer_payment_tokens( $tokens = [], $user_id, $gateway_id ) {
		if ( ! is_user_logged_in() || WC_Payment_Gateway_WCPay::GATEWAY_ID !== $gateway_id ) {
			return $tokens;
		}

		$customer_id = $this->customer_service->get_customer_id_by_user_id( $user_id );

		if ( ! is_null( $customer_id ) ) {
			$tokens = $this->migrate_existing_tokens( $tokens, $customer_id, WC_Payments::get_gateway()->is_in_test_mode() );
			$tokens = $this->import_customer_tokens( $tokens, $customer_id, $user_id );

			// import_customer_tokens might change the customer ID if it doesn't match
			// current test mode, so we need to update it.
			$customer_id = $this->customer_service->get_customer_id_by_user_id( $user_id );
		}

		$tokens = $this->remove_unavailable_tokens( $tokens, $customer_id );

		return $tokens;
	}

	/**
	 * Delete token from Stripe.
	 *
	 * @param string           $token_id Token ID.
	 * @param WC_Payment_Token $token    Token object.
	 */
	public function woocommerce_payment_token_deleted( $token_id, $token ) {
		if ( WC_Payment_Gateway_WCPay::GATEWAY_ID === $token->get_gateway_id() ) {
			try {
				$this->payments_api_client->detach_payment_method( $token->get_token() );
				// Clear cached payment methods.
				$this->customer_service->clear_cached_payment_methods_for_user( $token->get_user_id() );
			} catch ( Exception $e ) {
				Logger::log( 'Error detaching payment method:' . $e->getMessage() );
			}
		}
	}

	/**
	 * Set as default in Stripe.
	 *
	 * @param string           $token_id Token ID.
	 * @param WC_Payment_Token $token    Token object.
	 */
	public function woocommerce_payment_token_set_default( $token_id, $token ) {
		if ( WC_Payment_Gateway_WCPay::GATEWAY_ID === $token->get_gateway_id() ) {
			$customer_id = $this->customer_service->get_customer_id_by_user_id( $token->get_user_id() );
			if ( $customer_id ) {
				$this->customer_service->set_default_payment_method_for_customer( $customer_id, $token->get_token() );
				// Clear cached payment methods.
				$this->customer_service->clear_cached_payment_methods_for_user( $token->get_user_id() );
			}
		}
	}

	/**
	 * Imports customer payment methods from the API and adds them to WooCommerce.
	 *
	 * @param array  $tokens      Token list.
	 * @param string $customer_id Customer ID.
	 * @param int    $user_id     User ID.
	 *
	 * @return array Token list with imported tokens.
	 *
	 * @throws WC_Payments_API_Exception This method handles 'resource_missing' code types and rethrows anything else.
	 */
	private function import_customer_tokens( $tokens, $customer_id, $user_id ) {
		if ( null === $customer_id ) {
			return $tokens;
		}

		try {
			$payment_methods = $this->customer_service->get_payment_methods_for_customer( $customer_id );
		} catch ( WC_Payments_API_Exception $e ) {
			// If we failed to find the customer we can simply use an empty $payment_methods as this
			// customer will be recreated when the user successfully adds a new payment method.
			if ( 'resource_missing' === $e->get_error_code() ) {
				// However, if we can find the customer in a different mode (based on the exception
				// message) we need to migrate it to the new meta, along with their tokens.
				if ( $this->payments_api_client->customer_exists_in_other_mode( $e ) ) {
					$actual_user_mode = ! WC_Payments::get_gateway()->is_in_test_mode();
					$this->customer_service->change_customer_mode( $user_id, $customer_id, $actual_user_mode );
					$this->migrate_existing_tokens( $tokens, $customer_id, $actual_user_mode );
				}

				$payment_methods = [];
			} else {
				// Rethrow for error codes we don't care about in this function.
				throw $e;
			}
		}

		$stored_tokens = [];

		foreach ( $tokens as $token ) {
			$stored_tokens[] = $token->get_token();
		}

		foreach ( $payment_methods as $payment_method ) {
			if ( isset( $payment_method['type'] ) && 'card' === $payment_method['type'] ) {
				if ( ! in_array( $payment_method['id'], $stored_tokens, true ) ) {
					$token                      = $this->add_token_to_user( $payment_method, get_user_by( 'id', $user_id ) );
					$tokens[ $token->get_id() ] = $token;
				}
			}
		}

		return $tokens;
	}

	/**
	 * Clears unavailable tokens from the token list.
	 *
	 * @param array  $tokens      Token list.
	 * @param string $customer_id Customer ID.
	 *
	 * @return array Token list with tokens for $customer_id.
	 */
	private function remove_unavailable_tokens( $tokens, $customer_id ) {
		return array_filter(
			$tokens,
			function ( $token ) use ( $customer_id ) {
				return $token->get_meta( self::CUSTOMER_ID_META_KEY ) === $customer_id &&
					$token->get_meta( self::TEST_MODE_META_KEY ) === strval( WC_Payments::get_gateway()->is_in_test_mode() );
			}
		);
	}

	/**
	 * Adds customer and test_mode to tokens that have none.
	 * This takes an optimistic approach based on the customer ID and current
	 * test mode flag.
	 *
	 * @param array   $tokens      Token list.
	 * @param string  $customer_id Customer ID.
	 * @param boolean $test_mode   Customer test mode flag.
	 *
	 * @return array Token list with tokens for $customer_id.
	 */
	private function migrate_existing_tokens( $tokens, $customer_id, $test_mode ) {
		foreach ( $tokens as $token ) {
			if (
				! $token->meta_exists( self::CUSTOMER_ID_META_KEY ) ||
				(
					$token->meta_exists( self::TEST_MODE_META_KEY ) &&
					$token->get_meta( self::CUSTOMER_ID_META_KEY ) === $customer_id &&
					$token->get_meta( self::TEST_MODE_META_KEY ) !== strval( $test_mode )
				)
			) {
				$token->update_meta_data(
					self::CUSTOMER_ID_META_KEY,
					$customer_id
				);

				$token->update_meta_data(
					self::TEST_MODE_META_KEY,
					strval( $test_mode )
				);

				$token->save();
			}
		}
		return $tokens;
	}
}
