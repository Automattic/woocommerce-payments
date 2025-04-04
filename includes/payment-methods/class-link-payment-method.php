<?php
/**
 * Class CC_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WCPay\Constants\Currency_Code;

/**
 * Link Payment Method class extending UPE base class
 */
class Link_Payment_Method extends UPE_Payment_Method {

	const PAYMENT_METHOD_STRIPE_ID = 'link';

	/**
	 * Constructor for link payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id   = self::PAYMENT_METHOD_STRIPE_ID;
		$this->is_reusable = true;
		$this->currencies  = [ Currency_Code::UNITED_STATES_DOLLAR ];
		$this->icon_url    = plugins_url( 'assets/images/payment-methods/link.svg', WCPAY_PLUGIN_FILE );
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
		return __( 'Link', 'woocommerce-payments' );
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
		// Description is hardcoded in the react component.
		return '';
	}
}
