<?php
/**
 * Class CC_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

/**
 * Credit card Payment Method class extending UPE base class
 */
class CC_Payment_Method extends UPE_Payment_Method {

	/**
	 * Constructor for card payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct( $token_service ) {
		parent::__construct( $token_service );
		$this->stripe_id                = 'card';
		$this->title                    = 'Credit card / debit card';
		$this->can_reuse_payment_method = true;
	}

	/**
	 * Returns payment method title
	 *
	 * @param array $payment_details Optional payment details from charge object.
	 *
	 * @return string
	 */
	public function get_title( $payment_details = false ) {
		if ( ! $payment_details ) {
			return self::TITLE;
		}

		$details       = $payment_details[ self::STRIPE_ID ];
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
