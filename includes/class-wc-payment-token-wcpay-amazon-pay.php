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
		'email'        => '',
		'last4'        => '',
		'card_type'    => '',
		'expiry_month' => '',
		'expiry_year'  => '',
	];

	/**
	 * Get payment method type to display to user.
	 *
	 * When the funding card is known (last4 is set), returns a label like
	 * "Amazon Pay Visa ending in 4242 (expires 06/30)". Falls back to the
	 * email-based label or plain "Amazon Pay" when the funding card is absent.
	 *
	 * @param  string $deprecated Deprecated since WooCommerce 3.0.
	 * @return string
	 */
	public function get_display_name( $deprecated = '' ) {
		$last4 = $this->get_last4();
		if ( '' !== $last4 ) {
			$brand_label  = wc_get_credit_card_type_label( $this->get_card_type() );
			$expiry_month = $this->get_expiry_month();
			$expiry_year  = $this->get_expiry_year();
			if ( '' !== $expiry_month && '' !== $expiry_year ) {
				return sprintf(
					/* translators: 1: card brand label (e.g. Visa), 2: last 4 digits, 3: 2-digit expiry month, 4: 2-digit expiry year */
					__( 'Amazon Pay %1$s ending in %2$s (expires %3$s/%4$s)', 'woocommerce-payments' ),
					$brand_label,
					$last4,
					str_pad( (string) $expiry_month, 2, '0', STR_PAD_LEFT ),
					substr( (string) $expiry_year, -2 )
				);
			}
			return sprintf(
				/* translators: 1: card brand label, 2: last 4 digits */
				__( 'Amazon Pay %1$s ending in %2$s', 'woocommerce-payments' ),
				$brand_label,
				$last4
			);
		}

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
	 * Returns the last 4 digits of the Amazon Pay funding card, if shared.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string Last 4 digits, or empty string if not available.
	 */
	public function get_last4( $context = 'view' ) {
		return (string) ( $this->get_prop( 'last4', $context ) ?? '' );
	}

	/**
	 * Set the last 4 digits of the Amazon Pay funding card.
	 *
	 * @param string $last4 Last 4 digits.
	 */
	public function set_last4( $last4 ) {
		$this->set_prop( 'last4', $last4 );
	}

	/**
	 * Returns the card brand of the Amazon Pay funding card, if shared.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string Card brand (lowercased), or empty string if not available.
	 */
	public function get_card_type( $context = 'view' ) {
		return (string) ( $this->get_prop( 'card_type', $context ) ?? '' );
	}

	/**
	 * Set the card brand of the Amazon Pay funding card.
	 *
	 * @param string $card_type Card brand (e.g. 'visa', 'mastercard').
	 */
	public function set_card_type( $card_type ) {
		$this->set_prop( 'card_type', $card_type );
	}

	/**
	 * Returns the expiry month of the Amazon Pay funding card, if shared.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string Two-digit expiry month (zero-padded), or empty string if not available.
	 */
	public function get_expiry_month( $context = 'view' ) {
		return (string) ( $this->get_prop( 'expiry_month', $context ) ?? '' );
	}

	/**
	 * Set the expiry month of the Amazon Pay funding card.
	 *
	 * @param string $expiry_month Two-digit expiry month (zero-padded, e.g. '06').
	 */
	public function set_expiry_month( $expiry_month ) {
		$this->set_prop( 'expiry_month', $expiry_month );
	}

	/**
	 * Returns the expiry year of the Amazon Pay funding card, if shared.
	 *
	 * @param string $context What the value is for. Valid values are view and edit.
	 * @return string Four-digit expiry year, or empty string if not available.
	 */
	public function get_expiry_year( $context = 'view' ) {
		return (string) ( $this->get_prop( 'expiry_year', $context ) ?? '' );
	}

	/**
	 * Set the expiry year of the Amazon Pay funding card.
	 *
	 * @param string $expiry_year Four-digit expiry year (e.g. '2030').
	 */
	public function set_expiry_year( $expiry_year ) {
		$this->set_prop( 'expiry_year', $expiry_year );
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
	 * Hook prefix.
	 */
	protected function get_hook_prefix() {
		return 'woocommerce_payments_token_wcpay_amazon_pay_get_';
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
