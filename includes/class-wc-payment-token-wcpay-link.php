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
			__( 'Stripe Link email ending in %s', 'woocommerce-payments' ),
			$this->get_redacted_email()
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

	/**
	 * Returns redacted/shortened customer email
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string Redacted/shortened customer email.
	 */
	public function get_redacted_email( $context = 'view' ) {
		return $this->redact_email_address( $this->get_email( $context ) );
	}

	/**
	 * Transforms email address into redacted/shortened format like ***xxxx@x.com.
	 * Using shortened length of four characters will mimic CC last-4 digits of card number.
	 *
	 * @param string $email Email address.
	 * @return string Redacted/shortened email address.
	 */
	private function redact_email_address( $email ) {
		$placeholder             = '***';
		$shortened_length        = 4;
		list( $handle, $domain ) = explode( '@', $email );
		$redacted_handle         = strlen( $handle ) > $shortened_length ? substr( $handle, - $shortened_length ) : $handle;

		return "$placeholder$redacted_handle@$domain";
	}
}
