<?php
/**
 * Affirm Payment Method Definition
 *
 * @package WCPay\PaymentMethods\Configs\Definitions
 */

namespace WCPay\PaymentMethods\Configs\Definitions;

use WCPay\PaymentMethods\Configs\Interfaces\PaymentMethodDefinitionInterface;
use WCPay\PaymentMethods\Configs\Traits\Base_Payment_Method;
use WCPay\PaymentMethods\Configs\Constants\Payment_Method_Capability;
use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;
use WCPay\Constants\Payment_Method;
/**
 * Class implementing the Affirm payment method definition.
 */
class AffirmDefinition implements PaymentMethodDefinitionInterface {
	use Base_Payment_Method;

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
	 * Get the base filename for the payment method's icons.
	 *
	 * @return string
	 */
	private function get_icon_filename_base(): string {
		return $this->get_id() . '-logo';
	}

	/**
	 * Get the URL for the payment method's icon
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string
	 */
	public function get_icon_url( ?string $account_country = null ): string {
		return plugin_dir_url( WCPAY_PLUGIN_FILE ) . 'assets/images/payment-methods/' . $this->get_icon_filename_base() . '.svg';
	}

	/**
	 * Get the URL for the payment method's dark mode icon
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string Returns regular icon URL if no dark mode icon exists
	 */
	public function get_dark_icon_url( ?string $account_country = null ): string {
		$dark_icon_path = dirname( WCPAY_PLUGIN_FILE ) . '/assets/images/payment-methods/' . $this->get_icon_filename_base() . '-dark.svg';
		if ( file_exists( $dark_icon_path ) ) {
			return plugin_dir_url( WCPAY_PLUGIN_FILE ) . 'assets/images/payment-methods/' . $this->get_icon_filename_base() . '-dark.svg';
		}
		return $this->get_icon_url( $account_country );
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

	/**
	 * Get minimum amount for a currency and country
	 *
	 * @param string $currency The currency code.
	 * @param string $country  The country code.
	 * @return int|null
	 */
	public function get_minimum_amount( string $currency, string $country ): ?int {
		return $this->get_limits_per_currency()[ $currency ][ $country ]['min'] ?? null;
	}

	/**
	 * Get maximum amount for a currency and country
	 *
	 * @param string $currency The currency code.
	 * @param string $country  The country code.
	 * @return int|null
	 */
	public function get_maximum_amount( string $currency, string $country ): ?int {
		return $this->get_limits_per_currency()[ $currency ][ $country ]['max'] ?? null;
	}
}
