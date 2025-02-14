<?php
/**
 * Class Affirm_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WCPay\PaymentMethods\Configs\Definitions\AffirmDefinition;
use WCPay\PaymentMethods\Configs\Constants\PaymentMethodCapability;

/**
 * Affirm Payment Method class extending UPE base class
 */
class Affirm_Payment_Method extends UPE_Payment_Method {

	/**
	 * Constructor for Affirm payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );

		$capabilities = AffirmDefinition::get_capabilities();

		$this->stripe_id                    = AffirmDefinition::get_id(); // TODO: I know this is confusing - just roll with it. I'll try and untangle stripe_id vs id soon.
		$this->is_reusable                  = AffirmDefinition::is_reusable();
		$this->is_bnpl                      = AffirmDefinition::is_bnpl();
		$this->icon_url                     = AffirmDefinition::get_icon_url();
		$this->dark_icon_url                = AffirmDefinition::get_dark_icon_url();
		$this->currencies                   = AffirmDefinition::get_supported_currencies();
		$this->accept_only_domestic_payment = AffirmDefinition::does_accept_only_domestic_payments();
		$this->limits_per_currency          = AffirmDefinition::get_limits_per_currency();
		$this->countries                    = AffirmDefinition::get_supported_countries();
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
		return AffirmDefinition::get_title( $account_country );
	}

	/**
	 * Returns testing credentials to be printed at checkout in test mode.
	 *
	 * @param string $account_country The country of the account.
	 * @return string
	 */
	public function get_testing_instructions( string $account_country ) {
		return AffirmDefinition::get_testing_instructions();
	}
}
