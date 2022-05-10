<?php
/**
 * Class CC_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;

/**
 * Credit card Payment Method class extending UPE base class
 */
class CC_Payment_Method extends UPE_Payment_Method {

	const PAYMENT_METHOD_STRIPE_ID = 'card';

	/**
	 * Constructor for card payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id   = self::PAYMENT_METHOD_STRIPE_ID;
		$this->title       = __( 'Credit card / debit card', 'woocommerce-payments' );
		$this->is_reusable = true;
		$this->currencies  = [];// All currencies are supported.
	}

	/**
	 * Returns payment method title
	 *
	 * @param array|bool $payment_details Optional payment details from charge object.
	 *
	 * @return string
	 */
	public function get_title( $payment_details = false ) {
		if ( ! $payment_details ) {
			return $this->title;
		}

		$details       = $payment_details[ $this->stripe_id ];
		$funding_types = [
			'credit'  => __( 'credit', 'woocommerce-payments' ),
			'debit'   => __( 'debit', 'woocommerce-payments' ),
			'prepaid' => __( 'prepaid', 'woocommerce-payments' ),
			'unknown' => __( 'unknown', 'woocommerce-payments' ),
		];

		$payment_method_title = sprintf(
			// Translators: %1$s card brand, %2$s card funding (prepaid, credit, etc.).
			__( '%1$s %2$s card', 'woocommerce-payments' ),
			ucfirst( $details['network'] ),
			$funding_types[ $details['funding'] ]
		);

		return $payment_method_title;
	}

}
