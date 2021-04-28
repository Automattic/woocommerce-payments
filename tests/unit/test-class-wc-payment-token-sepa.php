<?php
/**
 * Class WC_Payment_Token_WCPay_SEPA_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Payment_Methods\Sepa_Payment_Gateway;

/**
 * WC_Payment_Token_WCPay_SEPA unit tests.
 */
class WC_Payment_Token_WCPay_SEPA_Test extends WP_UnitTestCase {

	/**
	 * Test creating and saving a SEPA token.
	 *
	 * @return void
	 */
	public function test_create_token() {
		$token = new WC_Payment_Token_WCPay_SEPA();
		$token->set_token( 'pm_1If1If1If1If1If1If' );
		$token->set_gateway_id( Sepa_Payment_Gateway::GATEWAY_ID );
		$token->set_user_id( get_current_user_id() );
		$token->set_last4( '3000' );
		$token->save();

		$actual = WC_Payment_Tokens::get( $token->get_id() );
		$this->assertInstanceOf( WC_Payment_Token_WCPay_SEPA::class, $actual );
		$this->assertSame( 'SEPA IBAN ending in 3000', $actual->get_display_name() );
		$this->assertTrue( $actual->validate() );
		$this->assertSame( '3000', $actual->get_last4() );
		$this->assertSame( 'wcpay_sepa', $actual->get_type() );
		$this->assertSame( 'woocommerce_payments_sepa', $actual->get_gateway_id() );
		$this->assertSame( 'pm_1If1If1If1If1If1If', $actual->get_token() );
	}
}
