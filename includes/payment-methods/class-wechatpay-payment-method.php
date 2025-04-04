<?php
/**
 * Class Wechatpay_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;

/**
 * WeChatPay Payment Method class extending UPE base class
 */
class Wechatpay_Payment_Method extends UPE_Payment_Method {

	const PAYMENT_METHOD_STRIPE_ID = 'wechat_pay';

	/**
	 * Constructor for WeChatPay payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id                    = self::PAYMENT_METHOD_STRIPE_ID;
		$this->is_reusable                  = false;
		$this->is_bnpl                      = false;
		$this->icon_url                     = plugins_url( 'assets/images/payment-methods/wechat-pay.svg', WCPAY_PLUGIN_FILE );
		$this->currencies                   = [
			Currency_Code::UNITED_STATES_DOLLAR,
			Currency_Code::AUSTRALIAN_DOLLAR,
			Currency_Code::CANADIAN_DOLLAR,
			Currency_Code::EURO,
			Currency_Code::POUND_STERLING,
			Currency_Code::HONG_KONG_DOLLAR,
			Currency_Code::JAPANESE_YEN,
			Currency_Code::SINGAPORE_DOLLAR,
			Currency_Code::DANISH_KRONE,
			Currency_Code::NORWEGIAN_KRONE,
			Currency_Code::SWEDISH_KRONA,
			Currency_Code::SWISS_FRANC,
		];
		$this->accept_only_domestic_payment = false;
		$this->countries                    = [
			Country_Code::UNITED_STATES,
			Country_Code::AUSTRALIA,
			Country_Code::CANADA,
			Country_Code::AUSTRIA,
			Country_Code::BELGIUM,
			Country_Code::DENMARK,
			Country_Code::FINLAND,
			Country_Code::FRANCE,
			Country_Code::GERMANY,
			Country_Code::IRELAND,
			Country_Code::ITALY,
			Country_Code::LUXEMBOURG,
			Country_Code::NETHERLANDS,
			Country_Code::NORWAY,
			Country_Code::PORTUGAL,
			Country_Code::SPAIN,
			Country_Code::SWEDEN,
			Country_Code::SWITZERLAND,
			Country_Code::UNITED_KINGDOM,
			Country_Code::HONG_KONG,
			Country_Code::JAPAN,
			Country_Code::SINGAPORE,
		];
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
		return __( 'WeChat Pay', 'woocommerce-payments' );
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
	 * Returns payment method supported currencies for the merchant's account.
	 *
	 * @return array
	 */
	public function get_currencies() {
		$account         = \WC_Payments::get_account_service()->get_cached_account_data();
		$account_country = isset( $account['country'] ) ? strtoupper( $account['country'] ) : '';

		// Map countries to their primary currencies.
		switch ( $account_country ) {
			case Country_Code::AUSTRALIA:
				return [ Currency_Code::AUSTRALIAN_DOLLAR ];
			case Country_Code::CANADA:
				return [ Currency_Code::CANADIAN_DOLLAR ];
			case Country_Code::DENMARK:
				return [ Currency_Code::DANISH_KRONE ];
			case Country_Code::HONG_KONG:
				return [ Currency_Code::HONG_KONG_DOLLAR ];
			case Country_Code::JAPAN:
				return [ Currency_Code::JAPANESE_YEN ];
			case Country_Code::NORWAY:
				return [ Currency_Code::NORWEGIAN_KRONE ];
			case Country_Code::SINGAPORE:
				return [ Currency_Code::SINGAPORE_DOLLAR ];
			case Country_Code::SWEDEN:
				return [ Currency_Code::SWEDISH_KRONA ];
			case Country_Code::SWITZERLAND:
				return [ Currency_Code::SWISS_FRANC ];
			case Country_Code::UNITED_KINGDOM:
				return [ Currency_Code::POUND_STERLING ];
			case Country_Code::UNITED_STATES:
				return [ Currency_Code::UNITED_STATES_DOLLAR ];
			default:
				// For all European countries in the supported list, return EUR.
				if ( in_array(
					$account_country,
					[
						Country_Code::AUSTRIA,
						Country_Code::BELGIUM,
						Country_Code::FINLAND,
						Country_Code::FRANCE,
						Country_Code::GERMANY,
						Country_Code::IRELAND,
						Country_Code::ITALY,
						Country_Code::LUXEMBOURG,
						Country_Code::NETHERLANDS,
						Country_Code::PORTUGAL,
						Country_Code::SPAIN,
					],
					true
				) ) {
					return [ Currency_Code::EURO ];
				}

				// Defaulted to unsupported currency.
				return [ 'UNSUPPORTED' ];
		}
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
			'A digital wallet popular with customers from China.',
			'woocommerce-payments'
		);
	}
}
