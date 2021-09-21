<?php
/**
 * Class WC_Payment_Token_WCPay_SEPA
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WooCommerce Stripe SEPA Direct Debit Payment Token.
 *
 * Representation of a payment token for SEPA.
 *
 * @class    WC_Payment_Token_WCPay_SEPA
 */
class WC_Payment_Token_WCPay_SEPA extends WC_Payment_Token {

	/**
	 * Class Constant so other code can be unambiguous.
	 *
	 * @type string
	 */
	const TYPE = 'wcpay_sepa';

	/**
	 * The payment method type of this token.
	 *
	 * @var string
	 */
	protected $type = self::TYPE;

	/**
	 * Stores SEPA payment token data.
	 *
	 * @var array
	 */
	protected $extra_data = [
		'last4' => '',
	];

	/**
	 * Get type to display to user.
	 *
	 * @param  string $deprecated Deprecated since WooCommerce 3.0.
	 * @return string
	 */
	public function get_display_name( $deprecated = '' ) {
		$display = sprintf(
			/* translators: last 4 digits of IBAN account */
			__( 'SEPA IBAN ending in %s', 'woocommerce-payments' ),
			$this->get_last4()
		);

		return $display;
	}

	/**
	 * Hook prefix.
	 */
	protected function get_hook_prefix() {
		return 'woocommerce_payments_token_wcpay_sepa_get_';
	}

	/**
	 * Validate SEPA payment tokens.
	 *
	 * These fields are required by all SEPA payment tokens:
	 * last4  - string Last 4 digits of the iBAN
	 *
	 * @return boolean True if the passed data is valid
	 */
	public function validate() {
		if ( false === parent::validate() ) {
			return false;
		}

		if ( ! $this->get_last4( 'edit' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns the last four digits.
	 *
	 * @param  string $context What the value is for. Valid values are view and edit.
	 * @return string Last 4 digits
	 */
	public function get_last4( $context = 'view' ) {
		return $this->get_prop( 'last4', $context );
	}

	/**
	 * Set the last four digits.
	 *
	 * @param string $last4 SEPA Debit last four digits.
	 */
	public function set_last4( $last4 ) {
		$this->set_prop( 'last4', $last4 );
	}
}
