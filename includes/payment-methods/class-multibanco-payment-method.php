<?php
/**
 * Class Multibanco_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;

/**
 * Multibanco Payment Method class extending UPE base class
 */
class Multibanco_Payment_Method extends UPE_Payment_Method {

	const PAYMENT_METHOD_STRIPE_ID = 'multibanco';

	/**
	 * Constructor for Multibanco payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id     = self::PAYMENT_METHOD_STRIPE_ID;
		$this->title         = 'Multibanco';
		$this->is_reusable   = false;
		$this->icon_url      = plugins_url( 'assets/images/payment-methods/multibanco-logo.svg', WCPAY_PLUGIN_FILE );
		$this->dark_icon_url = plugins_url( 'assets/images/payment-methods/multibanco-logo-dark.svg', WCPAY_PLUGIN_FILE );
		$this->currencies    = [ Currency_Code::EURO ];
		$this->countries     = [ Country_Code::PORTUGAL ];
	}

	/**
	 * Returns payment method title
	 *
	 * @param string|null $account_country Country of merchants account.
	 * @param array|false $payment_details Optional payment details from charge object.
	 *
	 * @return string
	 */
	public function get_title( ?string $account_country = null, $payment_details = false ) {
		return __( 'Multibanco', 'woocommerce-payments' );
	}

	/**
	 * Returns testing credentials to be printed at checkout in test mode.
	 *
	 * @param string $account_country The country of the account.
	 * @return string
	 */
	public function get_testing_instructions( string $account_country ) {
		return '';
	}

	/**
	 * Returns payment method description for the settings page.
	 *
	 * @param string|null $account_country Country of merchants account.
	 *
	 * @return string
	 */
	public function get_description( ?string $account_country = null ) {
		return __(
			'A voucher based payment method for your customers in Portugal.',
			'woocommerce-payments'
		);
	}

	/**
	 * Returns payment method settings icon.
	 *
	 * @param string|null $account_country Country of merchants account.
	 * @return string
	 */
	public function get_settings_icon_url( ?string $account_country = null ) {
		return plugins_url( 'assets/images/payment-methods/multibanco.svg', WCPAY_PLUGIN_FILE );
	}
}
