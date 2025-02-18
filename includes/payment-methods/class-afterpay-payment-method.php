<?php
/**
 * Class Afterpay_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Account;
use WC_Payments_Token_Service;
use WC_Payments_Utils;
use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;

/**
 * Afterpay Payment Method class extending UPE base class
 */
class Afterpay_Payment_Method extends UPE_Payment_Method {

	const PAYMENT_METHOD_STRIPE_ID = 'afterpay_clearpay';

	/**
	 * Constructor for Afterpay payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 * @param WC_Payments_Account       $account_service account service instance.
	 */
	public function __construct( $token_service, $account_service ) {
		parent::__construct( $token_service, $account_service );
		$this->stripe_id           = self::PAYMENT_METHOD_STRIPE_ID;
		$this->is_reusable         = false;
		$this->is_bnpl             = true;
		$this->icon_url            = plugins_url( 'assets/images/payment-methods/afterpay-logo.svg', WCPAY_PLUGIN_FILE );
		$this->currencies          = [ Currency_Code::UNITED_STATES_DOLLAR, Currency_Code::CANADIAN_DOLLAR, Currency_Code::AUSTRALIAN_DOLLAR, Currency_Code::NEW_ZEALAND_DOLLAR, Currency_Code::POUND_STERLING ];
		$this->limits_per_currency = WC_Payments_Utils::get_bnpl_limits_per_currency( self::PAYMENT_METHOD_STRIPE_ID );
		// In this scenario, this is the list of the contracted countries with Stripe.
		$this->countries = [ Country_Code::UNITED_STATES, Country_Code::CANADA, Country_Code::AUSTRALIA, Country_Code::NEW_ZEALAND, Country_Code::UNITED_KINGDOM ];
	}

	/**
	 * Returns payment method title.
	 *
	 * @param string|null $account_country Country of merchants account.
	 * @param array|false $payment_details Payment details from charge object. Not used by this class.
	 * @return string|null
	 *
	 * @phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	 */
	public function get_title( ?string $account_country = null, $payment_details = false ) {
		if ( 'GB' === $account_country ) {
			return __( 'Clearpay', 'woocommerce-payments' );
		}

		return __( 'Afterpay', 'woocommerce-payments' );
	}

	/**
	 * Returns payment method icon.
	 *
	 * @param string|null $account_country Country of merchants account.
	 * @return string|null
	 */
	public function get_icon( ?string $account_country = null ) {
		if ( 'GB' === $account_country ) {
			return plugins_url( 'assets/images/payment-methods/clearpay.svg', WCPAY_PLUGIN_FILE );
		}

		return plugins_url( 'assets/images/payment-methods/afterpay-badge.svg', WCPAY_PLUGIN_FILE );
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
	 * Returns the payment method's supported currencies at checkout.
	 *
	 * @return array
	 */
	public function get_currencies() {
		$account          = $this->account_service->get_cached_account_data();
		$account_currency = isset( $account['currency'] ) ? strtoupper( $account['currency'] ) : '';

		return in_array( $account_currency, $this->currencies, true ) ? [ $account_currency ] : $this->currencies;
	}

	/**
	 * Returns the payment method's supported countries at checkout.
	 *
	 * @return array
	 */
	public function get_countries() {
		$account         = $this->account_service->get_cached_account_data();
		$account_country = isset( $account['country'] ) ? strtoupper( $account['country'] ) : '';

		return in_array( $account_country, $this->countries, true ) ? [ $account_country ] : $this->countries;
	}
}
