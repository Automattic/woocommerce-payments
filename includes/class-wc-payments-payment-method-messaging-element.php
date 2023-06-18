<?php
/**
 * Class WC_Payments_Payment_Method_Messaging_Element
 *
 * @package WooCommerce\Payments
 */

use WCPay\Constants\Payment_Method;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use WCPay\Payment_Methods\UPE_Payment_Gateway;
use WCPay\Payment_Methods\UPE_Split_Payment_Gateway;
/**
 * WC_Payments_Payment_Method_Messaging_Element class.
 */
class WC_Payments_Payment_Method_Messaging_Element {
	/**
	 * WC_Payments_Account instance to get information about the account.
	 *
	 * @var WC_Payments_Account
	 */
	private $account;
	/**
	 * WC_Payments_Gateway instance to get information about the enabled payment methods.
	 *
	 * @var UPE_Payment_Gateway|UPE_Split_Payment_Gateway
	 */
	private $gateway;

	/**
	 * WC_Payments_Payment_Method_Messaging_Element constructor
	 *
	 * @param  WC_Payments_Account                           $account Account instance.
	 * @param  UPE_Payment_Gateway|UPE_Split_Payment_Gateway $gateway Gateway instance.
	 * @return void
	 */
	public function __construct( WC_Payments_Account $account, $gateway ) {
		$this->account = $account;
		$this->gateway = $gateway;
	}

	/**
	 * Initializes the payment method messaging element.
	 *
	 * @return string The HTML markup for the payment method message container.
	 */
	public function init(): string {
		global $product;
		$price           = $product->get_price();
		$currency_code   = get_woocommerce_currency();
		$billing_country = WC()->countries->get_base_country();

		if ( WC()->customer ) {
			$billing_country = WC()->customer->get_billing_country(); // Use the customer's billing country if available.
		}

		$enabled_upe_payment_methods = $this->gateway->get_payment_method_ids_enabled_at_checkout();
		// Filter non BNPL out of the list of payment methods.
		$bnpl_payment_methods = array_intersect( $enabled_upe_payment_methods, [ Payment_Method::AFFIRM, Payment_Method::AFTERPAY ] );

		// register the script.
		WC_Payments::register_script_with_dependencies( 'WCPAY_PRODUCT_DETAILS', 'dist/product-details', [ 'stripe' ] );
		wp_enqueue_script( 'WCPAY_PRODUCT_DETAILS' );
		// Create script tag with config.
		wp_localize_script(
			'WCPAY_PRODUCT_DETAILS',
			'wcpayStripeSiteMessaging',
			[
				'price'          => WC_Payments_Utils::prepare_amount( $price, $currency_code ),
				'currency'       => $currency_code,
				'country'        => $billing_country,
				'publishableKey' => $this->account->get_publishable_key( WC_Payments::mode()->is_test() ),
				'paymentMethods' => array_values( $bnpl_payment_methods ),
			]
		);

		return '<div id="payment-method-message"></div>';
	}
}
