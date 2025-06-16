<?php
/**
 * Class Affirm_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WC_Payments_Utils;
use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;

/**
 * Affirm Payment Method class extending UPE base class
 */
class Affirm_Payment_Method extends UPE_Payment_Method {

	const PAYMENT_METHOD_STRIPE_ID = 'affirm';

	/**
	 * Constructor for Affirm payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id                    = self::PAYMENT_METHOD_STRIPE_ID;
		$this->is_reusable                  = false;
		$this->is_bnpl                      = true;
		$this->icon_url                     = plugins_url( 'assets/images/payment-methods/affirm-logo.svg', WCPAY_PLUGIN_FILE );
		$this->dark_icon_url                = plugins_url( 'assets/images/payment-methods/affirm-logo-dark.svg', WCPAY_PLUGIN_FILE );
		$this->currencies                   = [ Currency_Code::UNITED_STATES_DOLLAR, Currency_Code::CANADIAN_DOLLAR ];
		$this->accept_only_domestic_payment = true;
		$this->limits_per_currency          = WC_Payments_Utils::get_bnpl_limits_per_currency( self::PAYMENT_METHOD_STRIPE_ID );
		$this->countries                    = [ Country_Code::UNITED_STATES, Country_Code::CANADA ];
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
		return __( 'Affirm', 'woocommerce-payments' );
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
			'Allow customers to pay over time with Affirm.',
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
		return plugins_url( 'assets/images/payment-methods/affirm-badge.svg', WCPAY_PLUGIN_FILE );
	}
}
