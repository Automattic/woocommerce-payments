<?php
/**
 * Token helpers.
 *
 * @package WooCommerce/Tests
 */

use WCPay\Payment_Methods\Sepa_Payment_Gateway;
use WCPay\Payment_Methods\CC_Payment_Gateway;

/**
 * Class WC_Helper_Token.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Helper_Token {

	/**
	 * Create a token.
	 *
	 * @param string $payment_method Token payment method.
	 * @param int    $user_id        ID of the token's user, defaults to get_current_user_id().
	 * @param string $gateway        Token's Gateway ID, default to CC_Payment_Gateway::GATEWAY_ID
	 */
	public static function create_token( $payment_method, $user_id = null, $gateway = CC_Payment_Gateway::GATEWAY_ID ) {
		$token = new WC_Payment_Token_CC();
		$token->set_token( $payment_method );
		$token->set_gateway_id( $gateway );
		$token->set_user_id( $user_id ?? get_current_user_id() );
		$token->set_card_type( 'visa' );
		$token->set_last4( '4242' );
		$token->set_expiry_month( 6 );
		$token->set_expiry_year( intval( gmdate( 'Y' ) ) + 1 );
		$token->save();

		return WC_Payment_Tokens::get( $token->get_id() );
	}

	/**
	 * Create a SEPA token.
	 *
	 * @param string $payment_method Token payment method.
	 * @param int    $user_id        ID of the token's user, defaults to get_current_user_id().
	 */
	public static function create_sepa_token( $payment_method, $user_id = null ) {
		$token = new WC_Payment_Token_WCPay_SEPA();
		$token->set_token( $payment_method );
		$token->set_gateway_id( Sepa_Payment_Gateway::GATEWAY_ID );
		$token->set_user_id( $user_id ?? get_current_user_id() );
		$token->set_last4( '3000' );
		$token->save();

		return WC_Payment_Tokens::get( $token->get_id() );
	}
}
