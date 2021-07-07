<?php
/**
 * Abstract UPE Payment Method class
 *
 * Handles general functionality for UPE payment methods
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

use WP_User;
use WC_Payments_Token_Service;
use WC_Payment_Token_CC;
use WC_Payment_Token_WCPay_SEPA;
use WC_Subscriptions;
use WC_Subscriptions_Cart;

/**
 * Extendable abstract class for payment methods.
 */
abstract class UPE_Payment_Method {

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
	 * Returns boolean dependent on whether payment method
	 * can be used at checkout
	 *
	 * @return bool
	 */
	public function is_enabled_at_checkout() {
		if ( class_exists( 'WC_Subscriptions' ) && version_compare( WC_Subscriptions::$version, '2.2.0', '>=' ) ) {
			// If cart contains subscription items or subscription renewal items.
			if ( WC_Subscriptions_Cart::cart_contains_subscription() ||
				0 < count( wcs_get_order_type_cart_items( 'renewal' ) ) ) {
				return $this->is_reusable();
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
}
