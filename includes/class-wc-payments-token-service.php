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
	 * Gets saved tokens from API if they don't already exist in WooCommerce.
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

		if ( null === $customer_id ) {
			return $tokens;
		}

		$stored_tokens = [];

		foreach ( $tokens as $token ) {
			$stored_tokens[] = $token->get_token();
		}

		$payment_methods = $this->customer_service->get_payment_methods_for_customer( $customer_id );

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
}
