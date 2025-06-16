<?php
/**
 * Class Sepa_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;

/**
 * Sepa Payment Method class extending UPE base class
 */
class Sepa_Payment_Method extends UPE_Payment_Method {

	const PAYMENT_METHOD_STRIPE_ID = 'sepa_debit';

	/**
	 * Constructor for Sepa payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id   = self::PAYMENT_METHOD_STRIPE_ID;
		$this->title       = 'SEPA Direct Debit';
		$this->is_reusable = false;
		$this->currencies  = [ Currency_Code::EURO ];
		$this->icon_url    = plugins_url( 'assets/images/payment-methods/sepa-debit.svg', WCPAY_PLUGIN_FILE );

		// https://stripe.com/en-br/resources/more/sepa-country-list#list-of-sepa-countries.
		$eu_countries              = [ Country_Code::AUSTRIA, Country_Code::BELGIUM, Country_Code::BULGARIA, Country_Code::CROATIA, Country_Code::CYPRUS, Country_Code::CZECHIA, Country_Code::DENMARK, Country_Code::ESTONIA, Country_Code::FINLAND, Country_Code::FRANCE, Country_Code::GERMANY, Country_Code::GREECE, Country_Code::HUNGARY, Country_Code::IRELAND, Country_Code::ITALY, Country_Code::LATVIA, Country_Code::LITHUANIA, Country_Code::LUXEMBOURG, Country_Code::MALTA, Country_Code::NETHERLANDS, Country_Code::POLAND, Country_Code::PORTUGAL, Country_Code::ROMANIA, Country_Code::SLOVAKIA, Country_Code::SLOVENIA, Country_Code::SPAIN, Country_Code::SWEDEN ];
		$additional_sepa_countries = [ Country_Code::SWITZERLAND, Country_Code::UNITED_KINGDOM, Country_Code::SAN_MARINO, Country_Code::VATICAN_CITY, Country_Code::ANDORRA, Country_Code::MONACO, Country_Code::LIECHTENSTEIN, Country_Code::NORWAY, Country_Code::ICELAND ];
		$this->countries           = array_merge( $eu_countries, $additional_sepa_countries );
	}

	/**
	 * Returns testing credentials to be printed at checkout in test mode.
	 *
	 * @param string $account_country The country of the account.
	 * @return string
	 */
	public function get_testing_instructions( string $account_country ) {
		return __( '<strong>Test mode:</strong> use the test account number <number>AT611904300234573201</number>. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed <a>here</a>.', 'woocommerce-payments' );
	}

	/**
	 * Returns payment method description for the settings page.
	 *
	 * @param string|null $account_country Country of merchants account.
	 *
	 * @return string
	 */
	public function get_description( ?string $account_country = null ) {
		return __(
			'Reach 500 million customers and over 20 million businesses across the European Union.',
			'woocommerce-payments'
		);
	}
}
