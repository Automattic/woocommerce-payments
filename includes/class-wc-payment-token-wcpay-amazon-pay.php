<?php
/**
 * Class WC_Payment_Token_WCPay_Amazon_Pay
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WooCommerce Amazon Pay Payment Token.
 *
 * Representation of a payment token for Amazon Pay.
 *
 * @class    WC_Payment_Token_WCPay_Amazon_Pay
 */
class WC_Payment_Token_WCPay_Amazon_Pay extends WC_Payment_Token {

	/**
	 * Class Constant so other code can be unambiguous.
	 *
	 * @type string
	 */
	const TYPE = 'wcpay_amazon_pay';

	/**
	 * The payment method type of this token.
	 *
	 * @var string
	 */
	protected $type = self::TYPE;

	/**
	 * Stores Amazon Pay payment token data.
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
		$email = $this->get_email();
		if ( ! empty( $email ) ) {
			return sprintf(
				/* translators: %s: redacted customer email */
				__( 'Amazon Pay (%s)', 'woocommerce-payments' ),
				$email
			);
		}

		return __( 'Amazon Pay', 'woocommerce-payments' );
	}

	/**
	 * Hook prefix.
	 */
	protected function get_hook_prefix() {
		return 'woocommerce_payments_token_wcpay_amazon_pay_get_';
	}

	/**
	 * Returns the redacted customer email.
	 * Note: The email is stored in redacted format for privacy.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 *
	 * @return string Redacted customer email.
	 */
	public function get_email( $context = 'view' ) {
		$email = $this->get_prop( 'email', $context );

		return $email ?? '';
	}

	/**
	 * Set the customer email. The email is automatically redacted for privacy.
	 *
	 * @param string $email Customer email (will be redacted before storage).
	 */
	public function set_email( $email ) {
		$this->set_prop( 'email', $this->redact_email_address( $email ) );
	}

	/**
	 * Returns the type of this payment token.
	 *
	 * @param  string $deprecated Deprecated since WooCommerce 3.0.
	 * @return string Payment Token Type.
	 */
	public function get_type( $deprecated = '' ) {
		return self::TYPE;
	}

	/**
	 * Transforms email address into redacted/shortened format like ***xxxx@domain.com.
	 * Using shortened length of four characters to mimic CC last-4 digits.
	 *
	 * @param string $email Email address.
	 * @return string Redacted/shortened email address.
	 */
	private function redact_email_address( $email ) {
		if ( empty( $email ) || false === strpos( $email, '@' ) ) {
			return $email;
		}

		$placeholder             = '***';
		$shortened_length        = 4;
		list( $handle, $domain ) = explode( '@', $email );
		$redacted_handle         = strlen( $handle ) > $shortened_length ? substr( $handle, - $shortened_length ) : $handle;

		return "$placeholder$redacted_handle@$domain";
	}
}
