<?php
/**
 * Class WC_Payment_Token_WCPay_Link
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WooCommerce Stripe Link Payment Token.
 *
 * Representation of a payment token for Link.
 *
 * @class    WC_Payment_Token_WCPay_Link
 */
class WC_Payment_Token_WCPay_Link extends WC_Payment_Token {

	/**
	 * Class Constant so other code can be unambiguous.
	 *
	 * @type string
	 */
	const TYPE = 'wcpay_link';

	/**
	 * The payment method type of this token.
	 *
	 * @var string
	 */
	protected $type = self::TYPE;

	/**
	 * Stores Link payment token data.
	 *
	 * @var array
	 */
	protected $extra_data = [
		'email' => '',
	];

	/**
	 * Get payment method type to display to user.
	 *
	 * @param  string $deprecated Deprecated since WooCommerce 3.0.
	 * @return string
	 */
	public function get_display_name( $deprecated = '' ) {
		$display = sprintf(
			/* translators: customer email */
			__( 'Stripe Link email %s', 'woocommerce-payments' ),
			$this->get_email()
		);

		return $display;
	}

	/**
	 * Hook prefix.
	 */
	protected function get_hook_prefix() {
		return 'woocommerce_payments_token_wcpay_link_get_';
	}

	/**
	 * Returns the customer email.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string Customer email.
	 */
	public function get_email( $context = 'view' ) {
		return $this->get_prop( 'email', $context );
	}

	/**
	 * Set the customer email.
	 *
	 * @param string $email Customer email.
	 */
	public function set_email( $email ) {
		$this->set_prop( 'email', $email );
	}
}
