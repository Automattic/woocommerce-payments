<?php
/**
 * Class Express_Checkout_Element_States_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Express_Checkout_Element_States;

/**
 * Express_Checkout_Element_States_Test unit tests.
 */
class Express_Checkout_Element_States_Test extends WCPAY_UnitTestCase {

	public function set_up() {
		parent::set_up();

		include_once WCPAY_ABSPATH . 'includes/constants/class-express-checkout-element-states.php';
	}

	/**
	 * The express checkout state lookup matches against the code and name without checking they're set,
	 * so an empty one would match a blank state from the shopper.
	 */
	public function test_every_state_has_a_code_and_a_name() {
		$invalid_states = [];

		foreach ( Express_Checkout_Element_States::STATES as $country => $states ) {
			foreach ( $states as $wc_state => $state ) {
				if ( ! isset( $state[0], $state[1] ) || ! is_string( $state[0] ) || ! is_string( $state[1] ) || '' === trim( $state[0] ) || '' === trim( $state[1] ) ) {
					$invalid_states[] = "$country/$wc_state";
				}
			}
		}

		$this->assertSame( [], $invalid_states );
	}
}
