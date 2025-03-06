<?php
/**
 * Class Alipay_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WCPay\Constants\Currency_Code;
use WCPay\PaymentMethods\Configs\Definitions\AlipayDefinition;

/**
 * Alipay Payment Method class extending UPE base class
 */
class Alipay_Payment_Method extends UPE_Payment_Method {
	/**
	 * Constructor for Alipay payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id   = AlipayDefinition::get_id();
		$this->is_reusable = AlipayDefinition::is_reusable();
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
		$this->icon_url    = AlipayDefinition::get_settings_icon_url();
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
		return AlipayDefinition::get_title( $account_country );
	}

	/**
	 * Returns testing credentials to be printed at checkout in test mode.
	 *
	 * @param string $account_country The country of the account.
	 * @return string
	 */
	public function get_testing_instructions( string $account_country ) {
		return AlipayDefinition::get_testing_instructions();
	}

	/**
	 * Returns payment method supported countries for the merchant's account
	 * (ensuring it's part of the contracted Alipay countries).
	 *
	 * @return array
	 */
	public function get_currencies() {
		return AlipayDefinition::get_supported_currencies();
	}
}
