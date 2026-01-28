<?php
/**
 * Class CC_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Token_Service;
use WCPay\Constants\Country_Test_Cards;

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
		$this->is_reusable = true;
		$this->currencies  = [];// All currencies are supported.
		$this->icon_url    = plugins_url( 'assets/images/payment-methods/generic-card.svg', WCPAY_PLUGIN_FILE );
	}

	/**
	 * Returns payment method title
	 *
	 * @param string|null $account_country Account country.
	 * @param array|false $payment_details Payment details.
	 * @return string
	 */
	public function get_title( ?string $account_country = null, $payment_details = false ) {
		if ( ! $payment_details ) {
			return __( 'Card', 'woocommerce-payments' );
		}

		$details       = $payment_details[ $this->stripe_id ];
		$funding_types = [
			'credit'  => __( 'credit', 'woocommerce-payments' ),
			'debit'   => __( 'debit', 'woocommerce-payments' ),
			'prepaid' => __( 'prepaid', 'woocommerce-payments' ),
			'unknown' => __( 'unknown', 'woocommerce-payments' ),
		];

		$card_network = $details['display_brand'] ?? $details['network'] ?? $details['networks']['preferred'] ?? $details['networks']['available'][0];
		// Networks like `cartes_bancaires` may use underscores, so we replace them with spaces.
		$card_network = str_replace( '_', ' ', $card_network );

		$payment_method_title = sprintf(
			// Translators: %1$s card brand, %2$s card funding (prepaid, credit, etc.).
			__( '%1$s %2$s card', 'woocommerce-payments' ),
			ucwords( $card_network ),
			$funding_types[ $details['funding'] ]
		);

		return $payment_method_title;
	}

	/**
	 * Returns testing credentials to be printed at checkout in test mode.
	 *
	 * @param string $account_country The country of the account.
	 * @return string
	 */
	public function get_testing_instructions( string $account_country ) {
		$test_card_number = Country_Test_Cards::get_test_card_for_country( $account_country );

		return sprintf(
			// Translators: %s is a test card number.
			__( 'Use test card <number>%s</number> or refer to our <a>testing guide</a>.', 'woocommerce-payments' ),
			$test_card_number
		);
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
			'Let your customers pay with major credit and debit cards without leaving your store.',
			'woocommerce-payments'
		);
	}

	/**
	 * Returns payment method settings label.
	 *
	 * @param string $account_country Country of merchants account.
	 * @return string
	 */
	public function get_settings_label( string $account_country ) {
		return __( 'Credit / Debit Cards', 'woocommerce-payments' );
	}

	/**
	 * Returns payment method settings icon.
	 *
	 * @param string|null $account_country Country of merchants account.
	 * @return string
	 */
	public function get_settings_icon_url( ?string $account_country = null ) {
		return plugins_url( 'assets/images/payment-methods/generic-card-black.svg', WCPAY_PLUGIN_FILE );
	}

	/**
	 * Returns boolean dependent on whether payment method allows manual capture.
	 *
	 * @return bool
	 */
	public function allows_manual_capture() {
		return true;
	}
}
