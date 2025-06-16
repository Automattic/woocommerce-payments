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
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * WC_Payments_Payment_Method_Messaging_Element constructor
	 *
	 * @param  WC_Payments_Account      $account Account instance.
	 * @param  WC_Payment_Gateway_WCPay $gateway Gateway instance.
	 * @return void
	 */
	public function __construct( WC_Payments_Account $account, WC_Payment_Gateway_WCPay $gateway ) {
		$this->account = $account;
		$this->gateway = $gateway;
	}

	/**
	 * Initializes the payment method messaging element.
	 *
	 * @return string|void The HTML markup for the payment method message container.
	 */
	public function init() {

		$is_cart_block = WC_Payments_Utils::is_cart_block();

		if ( ! is_product() && ! is_cart() && ! $is_cart_block ) {
			return;
		}

		global $product;
		$currency_code      = get_woocommerce_currency();
		$store_country      = WC()->countries->get_base_country();
		$billing_country    = WC()->customer->get_billing_country();
		$cart_total         = WC()->cart->total;
		$product_variations = [];

		if ( $product ) {
			$get_price_fn = function ( $product ) {
				return $product->get_price();
			};
			if ( wc_tax_enabled() && $product->is_taxable() ) {
				if (
					wc_prices_include_tax() &&
					(
						get_option( 'woocommerce_tax_display_shop' ) !== 'incl' ||
						WC()->customer->get_is_vat_exempt()
					)
				) {
					$get_price_fn = function ( $product ) {
						return wc_get_price_excluding_tax( $product );
					};
				} elseif (
					get_option( 'woocommerce_tax_display_shop' ) === 'incl'
					&& ! WC()->customer->get_is_vat_exempt()
				) {
					$get_price_fn = function ( $product ) {
						return wc_get_price_including_tax( $product );
					};
				}
			}
			$price              = $get_price_fn( $product );
			$product_variations = [
				'base_product' => [
					'amount'   => WC_Payments_Utils::prepare_amount( $price, $currency_code ),
					'currency' => $currency_code,
				],
			];

			$product_price = $product_variations['base_product']['amount'];

			foreach ( $product->get_children() as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( $variation ) {
					$price                               = $get_price_fn( $variation );
					$product_variations[ $variation_id ] = [
						'amount'   => WC_Payments_Utils::prepare_amount( $price, $currency_code ),
						'currency' => $currency_code,
					];

					$product_price = $product_variations['base_product']['amount'];
				}
			}
		}

		$enabled_upe_payment_methods = $this->gateway->get_upe_enabled_payment_method_ids();
		// Filter non BNPL out of the list of payment methods.
		$bnpl_payment_methods = array_intersect( $enabled_upe_payment_methods, Payment_Method::BNPL_PAYMENT_METHODS );

		// register the script.
		WC_Payments::register_script_with_dependencies( 'WCPAY_PRODUCT_DETAILS', 'dist/product-details', [ 'stripe' ] );
		wp_enqueue_script( 'WCPAY_PRODUCT_DETAILS' );

		// Enqueue the styles.
		wp_enqueue_style(
			'wcpay-product-details',
			plugins_url( 'dist/product-details.css', WCPAY_PLUGIN_FILE ),
			[],
			WC_Payments::get_file_version( 'dist/product-details.css' ),
		);

		$country = empty( $billing_country ) ? $store_country : $billing_country;

		$script_data = [
			'productId'            => 'base_product',
			'productVariations'    => $product_variations,
			'country'              => $country,
			'locale'               => WC_Payments_Utils::convert_to_stripe_locale( get_locale() ),
			'accountId'            => $this->account->get_stripe_account_id(),
			'publishableKey'       => $this->account->get_publishable_key( WC_Payments::mode()->is_test() ),
			'paymentMethods'       => array_values( $bnpl_payment_methods ),
			'currencyCode'         => $currency_code,
			'isCart'               => is_cart(),
			'isCartBlock'          => $is_cart_block,
			'cartTotal'            => WC_Payments_Utils::prepare_amount( $cart_total, $currency_code ),
			'nonce'                => [
				'get_cart_total'    => wp_create_nonce( 'wcpay-get-cart-total' ),
				'is_bnpl_available' => wp_create_nonce( 'wcpay-is-bnpl-available' ),
			],
			'wcAjaxUrl'            => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'shouldInitializePMME' => WC_Payments_Utils::is_any_bnpl_supporting_country( array_values( $bnpl_payment_methods ), $country, $currency_code ),
		];

		if ( $product ) {
			$script_data['shouldShowPMME'] = WC_Payments_Utils::is_any_bnpl_method_available( array_values( $bnpl_payment_methods ), $country, $currency_code, $product_price );
		}

		// Create script tag with config.
		wp_localize_script(
			'WCPAY_PRODUCT_DETAILS',
			'wcpayStripeSiteMessaging',
			$script_data
		);

		// Ensure wcpayConfig is available in the page.
		$wcpay_config = rawurlencode( wp_json_encode( WC_Payments::get_wc_payments_checkout()->get_payment_fields_js_config() ) );
		wp_add_inline_script(
			'WCPAY_PRODUCT_DETAILS',
			"
			var wcpayConfig = wcpayConfig || JSON.parse( decodeURIComponent( '" . esc_js( $wcpay_config ) . "' ) );
			",
			'before'
		);

		if ( ! $is_cart_block ) {
			return '<div id="payment-method-message"></div>';
		}
	}
}
