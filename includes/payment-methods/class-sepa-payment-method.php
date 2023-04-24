<?php
/**
 * Class Sepa_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;

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
		$this->is_reusable = true;
		$this->currencies  = [ 'EUR' ];
		$this->icon_url    = plugins_url( 'assets/images/payment-methods/sepa-debit.svg', WCPAY_PLUGIN_FILE );
	}

	/**
	 * Returns testing credentials to be printed at checkout in test mode.
	 *
	 * @return string
	 */
	public function get_testing_instructions() {
		return __( '<strong>Test mode:</strong> use the test account number AT611904300234573201. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed <a>here</a>.', 'woocommerce-payments' );
	}
}
