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
		$currency_code      = get_woocommerce_currency();
		$store_country      = WC()->countries->get_base_country();
		$billing_country    = WC()->customer->get_billing_country();
		$base_product_price = WC_Payments_Utils::prepare_amount( $product->get_price(), $currency_code );

		$product_prices = [ 'base_product' => WC_Payments_Utils::prepare_amount( $product->get_price(), $currency_code ) ];
		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation ) {
				$product_prices[ $variation_id ] = WC_Payments_Utils::prepare_amount( $variation->get_price(), get_woocommerce_currency() );
			}
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
				'productId'      => 'base_product',
				'currency'       => $currency_code,
				'productPrices'  => $product_prices,
				'country'        => empty( $billing_country ) ? $store_country : $billing_country,
				'publishableKey' => $this->account->get_publishable_key( WC_Payments::mode()->is_test() ),
				'paymentMethods' => array_values( $bnpl_payment_methods ),
			]
		);

		return '<div id="payment-method-message"></div>';
	}
}
