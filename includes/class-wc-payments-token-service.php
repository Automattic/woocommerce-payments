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
	 * WC_Payments_Token_Service constructor.
	 *
	 * @param WC_Payments_API_Client $payments_api_client Payments API client.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client ) {
		$this->payments_api_client = $payments_api_client;
	}

	/**
	 * Creates and add a token to an user, based on the payment_method_id
	 *
	 * @param string  $payment_method_id ID of the payment method to be added.
	 * @param WP_User $user              User to attach payment method to.
	 */
	public function add_token_to_user( $payment_method_id, $user ) {
		$payment_method = $this->payments_api_client->get_payment_method( $payment_method_id );

		$token = new WC_Payment_Token_CC();
		$token->set_token( $payment_method_id );
		$token->set_gateway_id( WC_Payment_Gateway_WCPay::GATEWAY_ID );
		$token->set_card_type( strtolower( $payment_method['card']['brand'] ) );
		$token->set_last4( $payment_method['card']['last4'] );
		$token->set_expiry_month( $payment_method['card']['exp_month'] );
		$token->set_expiry_year( $payment_method['card']['exp_year'] );
		$token->set_user_id( $user->ID );
		$token->save();

		return $token;
	}
}
