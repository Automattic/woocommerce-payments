<?php
/**
 * Abstract UPE Payment Method class
 *
 * Handles general functionality for UPE payment methods
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

/**
 * Extendable abstract class for payment methods.
 */
abstract class UPE_Payment_Method {

	/**
	 * Payment method ID and stripe key to be used within UPE PaymentIntent
	 *
	 * @var string
	 */
	protected $stripe_id;

	/**
	 * Payment method display title
	 *
	 * @var string
	 */
	protected $title;

	/**
	 * Can payment method be saved or reused?
	 *
	 * @var bool
	 */
	protected $can_reuse_payment_method;

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
	 * @return string
	 */
	public function get_title() {
		return $this->title;
	}

	/**
	 * Returns boolean dependent on whether payment method
	 * will support saved payments/subscription payments
	 *
	 * @return bool
	 */
	public function is_payment_method_reusable() {
		return $this->can_reuse_payment_method;
	}
}
