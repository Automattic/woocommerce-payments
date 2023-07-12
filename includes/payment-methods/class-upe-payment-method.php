<?php
/**
 * Abstract UPE Payment Method class
 *
 * Handles general functionality for UPE payment methods
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WC_Payments_Utils;
use WP_User;
use WC_Payments_Token_Service;
use WC_Payment_Token_CC;
use WC_Payment_Token_WCPay_SEPA;
use WC_Payments_Subscriptions_Utilities;
use WCPay\Logger;

/**
 * Extendable abstract class for payment methods.
 */
abstract class UPE_Payment_Method {

	use WC_Payments_Subscriptions_Utilities;

	/**
	 * Stripe key name
	 *
	 * @var string
	 */
	protected $stripe_id;

	/**
	 * Display title
	 *
	 * @var string
	 */
	protected $title;

	/**
	 * Can payment method be saved or reused?
	 *
	 * @var bool
	 */
	protected $is_reusable;

	/**
	 * Instance of WC Payments Token Service to save payment method
	 *
	 * @var WC_Payments_Token_Service
	 */
	protected $token_service;

	/**
	 * Supported presentment currencies for which charges for a payment method can be processed
	 * Empty if all currencies are supported
	 *
	 * @var string[]
	 */
	protected $currencies;

	/**
	 * Represent payment total limitations for the payment method (per-currency).
	 *
	 * @var array<string,array<string,int>>
	 */
	protected $limits_per_currency = [];

	/**
	 * Payment method icon URL
	 *
	 * @var string
	 */
	protected $icon_url;

	/**
	 * Supported customer locations for which charges for a payment method can be processed
	 * Empty if all customer locations are supported
	 *
	 * @var string[]
	 */
	protected $countries = [];

	/**
	 * Create instance of payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Instance of WC_Payments_Token_Service.
	 */
	public function __construct( $token_service ) {
		$this->token_service = $token_service;
	}

	/**
	 * Returns payment method ID
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->stripe_id;
	}

	/**
	 * Returns payment method title
	 *
	 * @param array|bool $payment_details Optional payment details from charge object.
	 *
	 * @return string
	 */
	public function get_title( $payment_details = false ) {
		return $this->title;
	}

	/**
	 * Returns payment method currencies
	 *
	 * @return array
	 */
	public function get_currencies() {
		return $this->currencies;
	}

	/**
	 * Returns boolean dependent on whether payment method
	 * can be used at checkout
	 *
	 * @return bool
	 */
	public function is_enabled_at_checkout() {
		if ( $this->is_subscription_item_in_cart() || $this->is_changing_payment_method_for_subscription() ) {
			return $this->is_reusable();
		}

		// This part ensures that when payment limits for the currency declared, those will be respected (e.g. BNPLs).
		if ( [] !== $this->limits_per_currency ) {
			$currency = get_woocommerce_currency();
			// If the currency limits are not defined, we allow the PM for now (gateway has similar validation for limits).
			// Additionally, we don't engage with limits verification in no-checkout context (cart is not available or empty).
			if ( isset( $this->limits_per_currency[ $currency ], WC()->cart ) ) {
				$amount = WC_Payments_Utils::prepare_amount( WC()->cart->get_total( '' ), $currency );
				if ( $amount > 0 ) {
					$range = $this->limits_per_currency[ $currency ];
					return $amount >= $range['min'] && $amount <= $range['max'];
				}
			}
		}

		return true;
	}

	/**
	 * Returns boolean dependent on whether payment method
	 * will support saved payments/subscription payments
	 *
	 * @return bool
	 */
	public function is_reusable() {
		return $this->is_reusable;
	}

	/**
	 * Returns boolean dependent on whether payment method will accept charges
	 * with chosen currency
	 *
	 * @param int $order_id Optional order ID, if order currency should take precedence.
	 *
	 * @return bool
	 */
	public function is_currency_valid( $order_id = null ) {
		return empty( $this->currencies ) || in_array( $this->get_currency( $order_id ), $this->currencies, true );
	}

	/**
	 * Add payment method to user and return WC payment token
	 *
	 * @param WP_User $user User to get payment token from.
	 * @param string  $payment_method_id Stripe payment method ID string.
	 *
	 * @return WC_Payment_Token_CC|WC_Payment_Token_WCPay_SEPA WC object for payment token.
	 */
	public function get_payment_token_for_user( $user, $payment_method_id ) {
		return $this->token_service->add_payment_method_to_user( $payment_method_id, $user );
	}

	/**
	 * Returns testing credentials to be printed at checkout in test mode.
	 *
	 * @return string
	 */
	abstract public function get_testing_instructions();

	/**
	 * Returns the payment method icon URL or an empty string.
	 *
	 * @return string
	 */
	public function get_icon() {
		return isset( $this->icon_url ) ? $this->icon_url : '';
	}

	/**
	 * Returns payment method supported countries
	 *
	 * @return array
	 */
	public function get_countries() {
		return $this->countries;
	}

	/**
	 * Returns valid currency to use to filter payment methods.
	 *
	 * @param int $order_id Optional order ID, if order currency should take precedence.
	 *
	 * @return string
	 */
	private function get_currency( $order_id = null ) {
		if ( is_wc_endpoint_url( 'order-pay' ) || null !== $order_id ) {
			global $wp;
			if ( null === $order_id ) {
				$order_id = absint( $wp->query_vars['order-pay'] );
			}
			$order = wc_get_order( $order_id );
			return $order->get_currency();
		}
		return get_woocommerce_currency();
	}
}
