<?php
/**
 * Afterpay Payment Method Definition
 *
 * @package WCPay\PaymentMethods\Configs\Definitions
 */

namespace WCPay\PaymentMethods\Configs\Definitions;

use WCPay\PaymentMethods\Configs\Interfaces\PaymentMethodDefinition;
use WCPay\PaymentMethods\Configs\Constants\Payment_Method_Capability;
use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;

/**
 * Class implementing the Afterpay payment method definition.
 */
class Afterpay implements PaymentMethodDefinition {
	/**
	 * Get the payment method ID
	 *
	 * @return string
	 */
	public static function get_id(): string {
		return 'afterpay_clearpay';
	}

	/**
	 * Get the customer-facing title of the payment method
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string
	 */
	public function get_title( ?string $account_country = null ): string {
		if ( Country_Code::UNITED_KINGDOM === $account_country ) {
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
	 * Get the list of supported currencies
	 *
	 * @return string[]
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
	 * @return string[]
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
	 * Get the URL for the payment method's icon
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string
	 */
	public function get_icon_url( ?string $account_country = null ): string {
		$filename = Country_Code::UNITED_KINGDOM === $account_country ? 'clearpay' : 'afterpay-badge';
		return plugin_dir_url( WCPAY_PLUGIN_FILE ) . 'assets/images/payment-methods/' . $filename . '.svg';
	}

	/**
	 * Get the URL for the payment method's dark mode icon
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string|null
	 */
	public function get_dark_icon_url( ?string $account_country = null ): ?string {
		$filename       = Country_Code::UNITED_KINGDOM === $account_country ? 'clearpay' : 'afterpay-badge';
		$dark_icon_path = plugin_dir_url( WCPAY_PLUGIN_FILE ) . 'assets/images/payment-methods/' . $filename . '-dark.svg';
		return file_exists( str_replace( plugin_dir_url( WCPAY_PLUGIN_FILE ), plugin_dir_path( WCPAY_PLUGIN_FILE ), $dark_icon_path ) ) ? $dark_icon_path : null;
	}

	/**
	 * Get the testing instructions for the payment method
	 *
	 * @return string
	 */
	public function get_testing_instructions(): string {
		return '';
	}

	/**
	 * Get the currency limits for the payment method
	 *
	 * @return array<string,array<string,array{min:int,max:int}>>
	 */
	public function get_currency_limits(): array {
		return [
			Currency_Code::AUSTRALIAN_DOLLAR    => [
				Country_Code::AUSTRALIA => [
					'min' => 100,
					'max' => 200000,
				],
			],
			Currency_Code::CANADIAN_DOLLAR      => [
				Country_Code::CANADA => [
					'min' => 100,
					'max' => 200000,
				],
			],
			Currency_Code::NEW_ZEALAND_DOLLAR   => [
				Country_Code::NEW_ZEALAND => [
					'min' => 100,
					'max' => 200000,
				],
			],
			Currency_Code::POUND_STERLING       => [
				Country_Code::UNITED_KINGDOM => [
					'min' => 100,
					'max' => 120000,
				],
			],
			Currency_Code::UNITED_STATES_DOLLAR => [
				Country_Code::UNITED_STATES => [
					'min' => 100,
					'max' => 400000,
				],
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

	/**
	 * Get the mapping of currencies to their domestic countries.
	 *
	 * @return array<string,string>
	 */
	public function get_domestic_currency_mapping(): array {
		return [
			Currency_Code::AUSTRALIAN_DOLLAR    => Country_Code::AUSTRALIA,
			Currency_Code::CANADIAN_DOLLAR      => Country_Code::CANADA,
			Currency_Code::NEW_ZEALAND_DOLLAR   => Country_Code::NEW_ZEALAND,
			Currency_Code::POUND_STERLING       => Country_Code::UNITED_KINGDOM,
			Currency_Code::UNITED_STATES_DOLLAR => Country_Code::UNITED_STATES,
		];
	}
}
