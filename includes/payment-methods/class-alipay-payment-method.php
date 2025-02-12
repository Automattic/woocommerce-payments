<?php
/**
 * Class Alipay_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;

/**
 * Alipay Payment Method class extending UPE base class
 */
class Alipay_Payment_Method extends UPE_Payment_Method {

	const PAYMENT_METHOD_STRIPE_ID = 'alipay';

	/**
	 * Constructor for Alipay payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id   = self::PAYMENT_METHOD_STRIPE_ID;
		$this->is_reusable = false;
		$this->currencies  = [
			Currency_Code::AUSTRALIAN_DOLLAR,
			Currency_Code::CANADIAN_DOLLAR,
			Currency_Code::POUND_STERLING,
			Currency_Code::HONG_KONG_DOLLAR,
			Currency_Code::JAPANESE_YEN,
			Currency_Code::NEW_ZEALAND_DOLLAR,
			Currency_Code::SINGAPORE_DOLLAR,
			Currency_Code::UNITED_STATES_DOLLAR,
			Currency_Code::HUNGARIAN_FORINT,
			Currency_Code::EURO,
			Currency_Code::CHINESE_YUAN,
		];
		$this->icon_url    = plugins_url( 'assets/images/payment-methods/alipay-logo.svg', WCPAY_PLUGIN_FILE );
		$this->countries   = [];
	}

	/**
	 * Returns payment method title.
	 *
	 * @param string|null $account_country Country of merchants account.
	 * @param array|false $payment_details Optional payment details from charge object.
	 *
	 * @return string
	 */
	public function get_title( ?string $account_country = null, $payment_details = false ) {
		return __( 'Alipay', 'woocommerce-payments' );
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

	/**
	 * Returns payment method supported countries for the merchant's account
	 * (ensuring it's part of the contracted Alipay countries).
	 *
	 * @return array
	 */
	public function get_currencies() {
		$account         = \WC_Payments::get_account_service()->get_cached_account_data();
		$account_country = isset( $account['country'] ) ? strtoupper( $account['country'] ) : '';

		if ( Country_Code::AUSTRALIA === $account_country ) {
			return [ Currency_Code::AUSTRALIAN_DOLLAR ];
		}

		if ( Country_Code::CANADA === $account_country ) {
			return [ Currency_Code::CANADIAN_DOLLAR ];
		}

		if ( Country_Code::UNITED_KINGDOM === $account_country ) {
			return [ Currency_Code::POUND_STERLING ];
		}

		if ( Country_Code::HONG_KONG === $account_country ) {
			return [ Currency_Code::HONG_KONG_DOLLAR ];
		}

		if ( Country_Code::JAPAN === $account_country ) {
			return [ Currency_Code::JAPANESE_YEN ];
		}

		if ( Country_Code::NEW_ZEALAND === $account_country ) {
			return [ Currency_Code::NEW_ZEALAND_DOLLAR ];
		}

		if ( Country_Code::SINGAPORE === $account_country ) {
			return [ Currency_Code::SINGAPORE_DOLLAR ];
		}

		if ( Country_Code::UNITED_STATES === $account_country ) {
			return [ Currency_Code::UNITED_STATES_DOLLAR ];
		}

		if ( Country_Code::HUNGARY === $account_country ) {
			return [ Currency_Code::HUNGARIAN_FORINT ];
		}

		if ( in_array(
			$account_country,
			[
				Country_Code::AUSTRIA,
				Country_Code::BELGIUM,
				Country_Code::BULGARIA,
				Country_Code::CYPRUS,
				Country_Code::CZECHIA,
				Country_Code::DENMARK,
				Country_Code::ESTONIA,
				Country_Code::FINLAND,
				Country_Code::FRANCE,
				Country_Code::GERMANY,
				Country_Code::GREECE,
				Country_Code::IRELAND,
				Country_Code::ITALY,
				Country_Code::LATVIA,
				Country_Code::LITHUANIA,
				Country_Code::LUXEMBOURG,
				Country_Code::MALTA,
				Country_Code::NETHERLANDS,
				Country_Code::NORWAY,
				Country_Code::PORTUGAL,
				Country_Code::ROMANIA,
				Country_Code::SLOVAKIA,
				Country_Code::SLOVENIA,
				Country_Code::SPAIN,
				Country_Code::SWEDEN,
				Country_Code::SWITZERLAND,
				Country_Code::CROATIA,
			],
			true
		) ) {
			return [ Currency_Code::EURO ];
		}

		// fallback currency code, just in case.
		return [ Currency_Code::CHINESE_YUAN ];
	}
}
