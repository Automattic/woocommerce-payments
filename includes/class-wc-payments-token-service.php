<?php
/**
 * WC_Payments_Payment_Tokens class
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

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
	}

	/**
	 * Creates and add a token to an user, based on the payment_method object
	 *
	 * @param array   $payment_method Payment method to be added.
	 * @param WP_User $user           User to attach payment method to.
	 */
	public function add_token_to_user( $payment_method, $user ) {
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
	 * Delete token from Stripe.
	 *
	 * @param string           $token_id Token ID.
	 * @param WC_Payment_Token $token    Token object.
	 */
	public function woocommerce_payment_token_deleted( $token_id, $token ) {
		if ( WC_Payment_Gateway_WCPay::GATEWAY_ID === $token->get_gateway_id() ) {
			$this->payments_api_client->detach_payment_method( $token->get_token() );
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
			$customer_id = $this->customer_service->get_customer_id_by_user_id( get_current_user_id() );
			if ( $customer_id ) {
				$this->customer_service->set_default_payment_method_for_customer( $customer_id, $token->get_token() );
			}
		}
	}
}
