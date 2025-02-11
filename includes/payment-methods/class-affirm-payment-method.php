<?php
/**
 * Class Affirm_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WCPay\PaymentMethods\Configs\Definitions\Affirm as AffirmDefinition;
use WCPay\PaymentMethods\Configs\Constants\Payment_Method_Capability;
use WCPay\PaymentMethods\Configs\Interfaces\PaymentMethodDefinition;
use WCPay\PaymentMethods\Configs\Interfaces\BNPLPaymentMethodDefinition;
use WCPay\PaymentMethods\Payment_Method_Definition_Registry;
/**
 * Affirm Payment Method class extending UPE base class
 */
class Affirm_Payment_Method extends UPE_Payment_Method {

	const PAYMENT_METHOD_STRIPE_ID = 'affirm';

	/**
	 * The payment method definition.
	 *
	 * @var BNPLPaymentMethodDefinition
	 */
	protected $definition;

	/**
	 * Constructor for Affirm payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->definition = new AffirmDefinition();

		// Register the payment method definition so it's exported for the client.
		$this->register_payment_method( $this->definition );

		$capabilities = $this->definition->get_capabilities();
		$icons        = $this->definition->get_icons();

		$this->stripe_id                    = $this->definition->get_id();
		$this->is_reusable                  = in_array( Payment_Method_Capability::TOKENIZATION, $capabilities, true );
		$this->is_bnpl                      = in_array( Payment_Method_Capability::BUY_NOW_PAY_LATER, $capabilities, true );
		$this->icon_url                     = $icons['default']['path'];
		$this->dark_icon_url                = $icons['dark']['path'];
		$this->currencies                   = $this->definition->get_supported_currencies();
		$this->accept_only_domestic_payment = in_array( Payment_Method_Capability::DOMESTIC_TRANSACTIONS_ONLY, $capabilities, true );
		$this->limits_per_currency          = $this->definition->get_limits_per_currency();
		$this->countries                    = $this->definition->get_supported_countries();
	}

	/**
	 * Register the payment method definition.
	 *
	 * @param PaymentMethodDefinition $definition The payment method definition to register.
	 */
	public function register_payment_method( PaymentMethodDefinition $definition ): void {
		$registry = Payment_Method_Definition_Registry::instance();
		$registry->register_payment_method( $definition );
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
		return $this->definition->get_title( $account_country );
	}

	/**
	 * Returns testing credentials to be printed at checkout in test mode.
	 *
	 * @param string $account_country The country of the account.
	 * @return string
	 */
	public function get_testing_instructions( string $account_country ) {
		return $this->definition->get_testing_instructions();
	}
}
