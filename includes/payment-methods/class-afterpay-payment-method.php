<?php
/**
 * Class Afterpay_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WCPay\PaymentMethods\Configs\Definitions\Afterpay as AfterpayDefinition;
use WCPay\PaymentMethods\Configs\Constants\Payment_Method_Capability;
use WCPay\PaymentMethods\Configs\Interfaces\PaymentMethodDefinition;
use WCPay\PaymentMethods\Payment_Method_Definition_Registry;

/**
 * Afterpay Payment Method class extending UPE base class
 */
class Afterpay_Payment_Method extends UPE_Payment_Method {

	/**
	 * The payment method definition.
	 *
	 * @var PaymentMethodDefinition
	 */
	protected $definition;

	/**
	 * Constructor for Afterpay payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->definition = new AfterpayDefinition();

		// Register the payment method definition so it's exported for the client.
		$this->register_payment_method( $this->definition );

		$capabilities = $this->definition->get_capabilities();

		$this->stripe_id                    = $this->get_stripe_id();
		$this->is_reusable                  = in_array( Payment_Method_Capability::TOKENIZATION, $capabilities, true );
		$this->is_bnpl                      = in_array( Payment_Method_Capability::BUY_NOW_PAY_LATER, $capabilities, true );
		$this->icon_url                     = $this->definition->get_icon_url();
		$this->dark_icon_url                = $this->definition->get_dark_icon_url();
		$this->currencies                   = $this->definition->get_supported_currencies();
		$this->countries                    = $this->definition->get_supported_countries();
		$this->accept_only_domestic_payment = in_array( Payment_Method_Capability::DOMESTIC_TRANSACTIONS_ONLY, $capabilities, true );
		$this->limits_per_currency          = $this->definition->get_currency_limits();
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
	 * Returns payment method title.
	 *
	 * @param string|null $account_country Country of merchants account.
	 * @param array|false $payment_details Payment details from charge object. Not used by this class.
	 * @return string|null
	 */
	public function get_title( ?string $account_country = null, $payment_details = false ) {
		return $this->definition->get_title( $account_country );
	}

	/**
	 * Returns payment method icon.
	 *
	 * @param string|null $account_country Country of merchants account.
	 * @return string|null
	 */
	public function get_icon( ?string $account_country = null ) {
		return $this->definition->get_icon_url( $account_country );
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

	/**
	 * Get the Stripe payment method ID (e.g. 'afterpay_clearpay_payments')
	 * By default, this appends '_payments' to the payment method ID.
	 *
	 * @return string
	 */
	protected function get_stripe_id(): string {
		return AfterpayDefinition::get_id() . '_payments';
	}

	/**
	 * Check if the payment method is available for the given currency and country.
	 *
	 * @todo This method is not currently being used. It will be integrated when implementing the full payment method availability checks and may be moved to the UPE_Payment_Method class.
	 *
	 * @param string $currency        The currency code to check.
	 * @param string $account_country The merchant's account country.
	 * @return bool
	 */
	public function is_available_for( string $currency, string $account_country ): bool {
		// Check if currency is supported.
		$supported_currencies = $this->definition->get_supported_currencies();
		if ( ! empty( $supported_currencies ) && ! in_array( $currency, $supported_currencies, true ) ) {
			return false;
		}

		// Check if country is supported.
		$supported_countries = $this->definition->get_supported_countries();
		if ( ! empty( $supported_countries ) && ! in_array( $account_country, $supported_countries, true ) ) {
			return false;
		}

		// Check domestic transaction requirement.
		if ( in_array( Payment_Method_Capability::DOMESTIC_TRANSACTIONS_ONLY, $this->definition->get_capabilities(), true ) ) {
			$mapping = $this->definition->get_domestic_currency_mapping();
			return isset( $mapping[ $currency ] ) && $mapping[ $currency ] === $account_country;
		}

		return true;
	}

	/**
	 * Check if the payment amount is within the allowed limits for the currency and country.
	 *
	 * @todo This method is not currently being used. It will be integrated when implementing the full payment method amount validation and may be moved to the UPE_Payment_Method class.
	 *
	 * @param int    $amount          The payment amount in cents.
	 * @param string $currency        The currency code.
	 * @param string $account_country The merchant's account country.
	 * @return bool
	 */
	public function is_amount_within_limits( int $amount, string $currency, string $account_country ): bool {
		// First check if this currency/country combination is valid.
		if ( ! $this->is_available_for( $currency, $account_country ) ) {
			return false;
		}

		$limits = $this->definition->get_currency_limits();

		if ( ! isset( $limits[ $currency ][ $account_country ] ) ) {
			return false;
		}

		$currency_limits = $limits[ $currency ][ $account_country ];
		return $amount >= $currency_limits['min'] && $amount <= $currency_limits['max'];
	}
}
