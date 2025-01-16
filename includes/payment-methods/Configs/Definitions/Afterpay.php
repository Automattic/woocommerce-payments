<?php
/**
 * Afterpay Payment Method Definition
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
 * Class implementing the Afterpay payment method definition.
 */
class Afterpay implements BNPLPaymentMethodDefinition {
	use Base_Payment_Method;
	use BNPL_Payment_Method;
	use Payment_Method_Icons;

	/**
	 * Get the internal ID for the payment method
	 *
	 * @return string
	 */
	public function get_id(): string {
		return Payment_Method::AFTERPAY;
	}

	/**
	 * Get the customer-facing title of the payment method
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string
	 */
	public function get_title( ?string $account_country = null ): string {
		if ( 'GB' === $account_country ) {
			return __( 'Clearpay', 'woocommerce-payments' );
		}

		return __( 'Afterpay', 'woocommerce-payments' );
	}

	/**
	 * Get the customer-facing description of the payment method
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string
	 */
	public function get_description( ?string $account_country = null ): string {
		// translators: %s is the payment method title.
		return sprintf( __( 'Allow customers to pay over time with %s.', 'woocommerce-payments' ), $this->get_title( $account_country ) );
	}

	/**
	 * Override the icon filename base to match the actual icon filenames.
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string
	 */
	public function get_icon_filename_base( ?string $account_country = null ): string {
		if ( 'GB' === $account_country ) {
			return 'clearpay';
		}

		return 'afterpay-badge';
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
			Currency_Code::AUSTRALIAN_DOLLAR,
			Currency_Code::NEW_ZEALAND_DOLLAR,
			Currency_Code::POUND_STERLING,
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
			Country_Code::AUSTRALIA,
			Country_Code::NEW_ZEALAND,
			Country_Code::UNITED_KINGDOM,
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
			Currency_Code::AUSTRALIAN_DOLLAR    => [
				Country_Code::AUSTRALIA => [
					'min' => 100,
					'max' => 200000,
				], // Represents AUD 1 - 2,000 AUD.
			],
			Currency_Code::CANADIAN_DOLLAR      => [
				Country_Code::CANADA => [
					'min' => 100,
					'max' => 200000,
				], // Represents CAD 1 - 2,000 CAD.
			],
			Currency_Code::NEW_ZEALAND_DOLLAR   => [
				Country_Code::NEW_ZEALAND => [
					'min' => 100,
					'max' => 200000,
				], // Represents NZD 1 - 2,000 NZD.
			],
			Currency_Code::POUND_STERLING       => [
				Country_Code::UNITED_KINGDOM => [
					'min' => 100,
					'max' => 120000,
				], // Represents GBP 1 - 1,200 GBP.
			],
			Currency_Code::UNITED_STATES_DOLLAR => [
				Country_Code::UNITED_STATES => [
					'min' => 100,
					'max' => 400000,
				], // Represents USD 1 - 4,000 USD.
			],
		];
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
