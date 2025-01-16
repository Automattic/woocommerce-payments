<?php
/**
 * Affirm Payment Method Definition
 *
 * @package WCPay\PaymentMethods\Configs\Definitions
 */

namespace WCPay\PaymentMethods\Configs\Definitions;

use WCPay\PaymentMethods\Configs\Interfaces\BNPLPaymentMethodDefinition;
use WCPay\PaymentMethods\Configs\Traits\Base_Payment_Method;
use WCPay\PaymentMethods\Configs\Traits\BNPL_Payment_Method;
use WCPay\PaymentMethods\Configs\Traits\Payment_Method_Icons;
use WCPay\PaymentMethods\Configs\Constants\Payment_Method_Capability;
use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;
use WCPay\Constants\Payment_Method;
/**
 * Class implementing the Affirm payment method definition.
 */
class Affirm implements BNPLPaymentMethodDefinition {
	use Base_Payment_Method;
	use BNPL_Payment_Method;
	use Payment_Method_Icons;

	/**
	 * Get the internal ID for the payment method
	 *
	 * @return string
	 */
	public function get_id(): string {
		return Payment_Method::AFFIRM;
	}

	/**
	 * Get the customer-facing title of the payment method
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string
	 */
	public function get_title( ?string $account_country = null ): string {
		return __( 'Affirm', 'woocommerce-payments' );
	}

	/**
	 * Get the customer-facing description of the payment method
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Allow customers to pay over time with Affirm.', 'woocommerce-payments' );
	}

	/**
	 * Get the list of supported currencies
	 *
	 * @return string[] Array of currency codes
	 */
	public function get_supported_currencies(): array {
		return [
			Currency_Code::UNITED_STATES_DOLLAR,
			Currency_Code::CANADIAN_DOLLAR,
		];
	}

	/**
	 * Get the list of supported countries
	 *
	 * @return string[] Array of country codes
	 */
	public function get_supported_countries(): array {
		return [
			Country_Code::UNITED_STATES,
			Country_Code::CANADA,
		];
	}

	/**
	 * Get the payment method capabilities
	 *
	 * @return string[]
	 */
	public function get_capabilities(): array {
		return [
			Payment_Method_Capability::REFUNDS,
			Payment_Method_Capability::BUY_NOW_PAY_LATER,
			Payment_Method_Capability::MULTI_CURRENCY,
			Payment_Method_Capability::DOMESTIC_TRANSACTIONS_ONLY,
		];
	}

	/**
	 * Override the icon filename base to match the actual icon filenames.
	 *
	 * @return string
	 */
	protected function get_icon_filename_base(): string {
		return $this->get_id() . '-logo';
	}

	/**
	 * Get the testing instructions for the payment method
	 *
	 * @return string HTML string containing testing instructions
	 */
	public function get_testing_instructions(): string {
		return '';
	}

	/**
	 * Get the currency limits for the payment method
	 *
	 * @return array<string,array<string,array{min:int,max:int}>>
	 */
	public function get_limits_per_currency(): array {
		return [
			Currency_Code::CANADIAN_DOLLAR      => [
				Country_Code::CANADA => [
					// min C$50.00.
					'min' => 5000,
					// max C$30,000.00.
					'max' => 3000000,
				],
			],
			Currency_Code::UNITED_STATES_DOLLAR => [
				Country_Code::UNITED_STATES => [
					// min $50.00.
					'min' => 5000,
					// max $30,000.00.
					'max' => 3000000,
				],
			],
		];
	}

	/**
	 * Check if the payment method meets additional availability constraints beyond currency and country support.
	 * For Affirm, the currency must match the country (USD for US, CAD for Canada).
	 *
	 * @param string $currency        The currency code to check.
	 * @param string $account_country The merchant's account country.
	 * @return bool True if the payment method meets all additional availability constraints.
	 */
	protected function meets_availability_constraints( string $currency, string $account_country ): bool {
		return ( Currency_Code::UNITED_STATES_DOLLAR === $currency && Country_Code::UNITED_STATES === $account_country ) ||
				( Currency_Code::CANADIAN_DOLLAR === $currency && Country_Code::CANADA === $account_country );
	}

	/**
	 * Whether this payment method is enabled by default
	 *
	 * @return bool
	 */
	public function is_enabled_by_default(): bool {
		return false;
	}
}
