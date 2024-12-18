<?php
/**
 * Class Affirm_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WC_Payments_Utils;
use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;

/**
 * Affirm Payment Method class extending UPE base class
 */
class Klarna_Payment_Method extends UPE_Payment_Method {

	const PAYMENT_METHOD_STRIPE_ID = 'klarna';

	/**
	 * Constructor for Klarna payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id                    = self::PAYMENT_METHOD_STRIPE_ID;
		$this->is_reusable                  = false;
		$this->is_bnpl                      = true;
		$this->icon_url                     = plugins_url( 'assets/images/payment-methods/klarna-pill.svg', WCPAY_PLUGIN_FILE );
		$this->currencies                   = [ Currency_Code::UNITED_STATES_DOLLAR, Currency_Code::POUND_STERLING, Currency_Code::EURO, Currency_Code::DANISH_KRONE, Currency_Code::NORWEGIAN_KRONE, Currency_Code::SWEDISH_KRONA ];
		$this->accept_only_domestic_payment = true;
		$this->countries                    = [ Country_Code::UNITED_STATES, Country_Code::UNITED_KINGDOM, Country_Code::AUSTRIA, Country_Code::GERMANY, Country_Code::NETHERLANDS, Country_Code::BELGIUM, Country_Code::SPAIN, Country_Code::ITALY, Country_Code::IRELAND, Country_Code::DENMARK, Country_Code::FINLAND, Country_Code::NORWAY, Country_Code::SWEDEN, Country_Code::FRANCE ];
		$this->limits_per_currency          = WC_Payments_Utils::get_bnpl_limits_per_currency( self::PAYMENT_METHOD_STRIPE_ID );
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
		return __( 'Klarna', 'woocommerce-payments' );
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

			$countries_that_support_store_currency = array_keys( $this->limits_per_currency[ $store_currency ] );

			return array_values( array_intersect( $eea_countries, $countries_that_support_store_currency ) );
		}

		return parent::get_countries();
	}

	/**
	 * Returns testing credentials to be printed at checkout in test mode.
	 *
	 * @param string $account_country The country of the account.
	 * @return string
	 */
	public function get_testing_instructions( string $account_country ) {
		return '';
	}
}
