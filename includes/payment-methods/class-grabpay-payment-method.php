<?php
/**
 * Class Grabpay_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;

/**
 * GrabPay Payment Method class extending UPE base class
 */
class Grabpay_Payment_Method extends UPE_Payment_Method {

	const PAYMENT_METHOD_STRIPE_ID = 'grabpay';

	/**
	 * Constructor for GrabPay payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		// Note: If WooPayments becomes available to merchants from Malaysia in the future, we'll need to not only add MYR here, but also implement
		// logic to limit the currency based on the Stripe account country, so SG accounts only accept SGD, and MY accounts only accept MYR.
		$this->currencies                   = [ Currency_Code::SINGAPORE_DOLLAR ];
		$this->stripe_id                    = self::PAYMENT_METHOD_STRIPE_ID;
		$this->is_reusable                  = false;
		$this->is_bnpl                      = false;
		$this->icon_url                     = plugins_url( 'assets/images/payment-methods/grabpay.svg', WCPAY_PLUGIN_FILE );
		$this->accept_only_domestic_payment = true;
		$this->countries                    = [ Country_Code::SINGAPORE ];
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
		return __( 'GrabPay', 'woocommerce-payments' );
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
	 * Returns payment method description for the settings page.
	 *
	 * @param string|null $account_country Country of merchants account.
	 *
	 * @return string
	 */
	public function get_description( ?string $account_country = null ) {
		return __(
			'A popular digital wallet for cashless payments in Singapore.',
			'woocommerce-payments'
		);
	}
}
