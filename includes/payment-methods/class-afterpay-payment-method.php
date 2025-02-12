<?php
/**
 * Class Afterpay_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WCPay\PaymentMethods\Configs\Definitions\AfterpayDefinition;
use WCPay\PaymentMethods\Configs\Constants\Payment_Method_Capability;
use WCPay\PaymentMethods\Configs\Interfaces\PaymentMethodDefinitionInterface;
use WCPay\PaymentMethods\Configs\Interfaces\BNPLPaymentMethodDefinition;
use WCPay\PaymentMethods\Payment_Method_Definition_Registry;


/**
 * Afterpay Payment Method class extending UPE base class
 */
class Afterpay_Payment_Method extends UPE_Payment_Method {

	/**
	 * Constructor for Afterpay payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );

		// Register the payment method definition so it's exported for the client.
		$this->register_payment_method( AfterpayDefinition::class );

		$capabilities = AfterpayDefinition::get_capabilities();

		$this->stripe_id                    = AfterpayDefinition::get_id();
		$this->is_reusable                  = in_array( Payment_Method_Capability::TOKENIZATION, $capabilities, true );
		$this->is_bnpl                      = in_array( Payment_Method_Capability::BUY_NOW_PAY_LATER, $capabilities, true );
		$this->icon_url                     = AfterpayDefinition::get_icon_url();
		$this->dark_icon_url                = AfterpayDefinition::get_dark_icon_url();
		$this->currencies                   = AfterpayDefinition::get_supported_currencies();
		$this->countries                    = AfterpayDefinition::get_supported_countries();
		$this->accept_only_domestic_payment = in_array( Payment_Method_Capability::DOMESTIC_TRANSACTIONS_ONLY, $capabilities, true );
		$this->limits_per_currency          = AfterpayDefinition::get_limits_per_currency();
	}

	/**
	 * Register the payment method definition.
	 *
	 * @param string $definition_class The payment method definition class to register.
	 */
	public function register_payment_method( string $definition_class ): void {
		$registry = Payment_Method_Definition_Registry::instance();
		$registry->register_payment_method( $definition_class );
	}

	/**
	 * Returns payment method title.
	 *
	 * @param string|null $account_country Country of merchants account.
	 * @param array|false $payment_details Payment details from charge object. Not used by this class.
	 * @return string|null
	 *
	 * @phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	 */
	public function get_title( ?string $account_country = null, $payment_details = false ) {
		return AfterpayDefinition::get_title( $account_country );
	}

	/**
	 * Returns payment method icon.
	 *
	 * @param string|null $account_country Country of merchants account.
	 * @return string|null
	 */
	public function get_icon( ?string $account_country = null ) {
		return AfterpayDefinition::get_icon_url( $account_country );
	}

	/**
	 * Returns testing credentials to be printed at checkout in test mode.
	 *
	 * @param string $account_country The country of the account.
	 * @return string
	 */
	public function get_testing_instructions( string $account_country ) {
		return AfterpayDefinition::get_testing_instructions();
	}
}
