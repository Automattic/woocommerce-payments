<?php
/**
 * Class Klarna_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WCPay\PaymentMethods\Configs\Definitions\KlarnaDefinition;

/**
 * Klarna Payment Method class extending UPE base class
 */
class Klarna_Payment_Method extends UPE_Payment_Method {

	/**
	 * Constructor for Klarna payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );

		$this->stripe_id                    = KlarnaDefinition::get_id();
		$this->is_reusable                  = KlarnaDefinition::is_reusable();
		$this->is_bnpl                      = KlarnaDefinition::is_bnpl();
		$this->icon_url                     = KlarnaDefinition::get_icon_url();
		$this->dark_icon_url                = KlarnaDefinition::get_dark_icon_url();
		$this->currencies                   = KlarnaDefinition::get_supported_currencies();
		$this->countries                    = KlarnaDefinition::get_supported_countries();
		$this->accept_only_domestic_payment = KlarnaDefinition::accepts_only_domestic_payments();
		$this->limits_per_currency          = KlarnaDefinition::get_limits_per_currency();
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
		return KlarnaDefinition::get_title( $account_country );
	}

	/**
	 * Returns testing credentials to be printed at checkout in test mode.
	 *
	 * @param string $account_country The country of the account.
	 * @return string
	 */
	public function get_testing_instructions( string $account_country ) {
		return KlarnaDefinition::get_testing_instructions();
	}
}
