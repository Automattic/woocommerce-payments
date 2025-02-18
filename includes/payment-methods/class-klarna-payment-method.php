<?php
/**
 * Class Klarna_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WC_Payments_Utils;
use WCPay\Constants\Country_Code;
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

	/**
	 * Returns payment method supported countries.
	 *
	 * For Klarna we need to include additional logic to support transactions between countries in the EEA,
	 * UK, and Switzerland.
	 *
	 * @return array
	 */
	public function get_countries() {
		$account         = \WC_Payments::get_account_service()->get_cached_account_data();
		$account_country = isset( $account['country'] ) ? strtoupper( $account['country'] ) : '';

		// Countries in the EEA can transact across all other EEA countries. This includes Switzerland and the UK who aren't strictly in the EU.
		$eea_countries = array_merge(
			WC_Payments_Utils::get_european_economic_area_countries(),
			[ Country_Code::SWITZERLAND, Country_Code::UNITED_KINGDOM ]
		);

		// If the merchant is in the EEA, UK, or Switzerland, only the countries that have the same domestic currency as the store currency will be supported.
		if ( in_array( $account_country, $eea_countries, true ) ) {
			$store_currency = strtoupper( get_woocommerce_currency() );

			$countries_that_support_store_currency = array_keys( KlarnaDefinition::get_limits_per_currency()[ $store_currency ] ?? [] );

			return array_values( array_intersect( $eea_countries, $countries_that_support_store_currency ) );
		}

		// For non-EEA countries, only return the merchant's country if it's supported.
		$supported_countries = KlarnaDefinition::get_supported_countries();
		return in_array( $account_country, $supported_countries, true ) ? [ $account_country ] : [];
	}
}
