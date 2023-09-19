<?php
/**
 * Class Payment
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

use WC_Order;
use WCPay\Internal\Payment\PaymentMethod\PaymentMethodInterface;

/**
 * Payment object.
 *
 * This is a data object, used as context and storage for payment states.
 */
class Payment {
	/**
	 * Order requiring/having payment.
	 *
	 * @var WC_Order
	 */
	private $order;

	/**
	 * Payment method.
	 *
	 * @var PaymentMethodInterface
	 */
	private $payment_method;

	/**
	 * Payment constructor.
	 *
	 * @param WC_Order $order Order requiring payment.
	 */
	public function __construct( WC_Order $order ) {
		$this->order = $order;
	}

	/**
	 * Returns the payment's order.
	 *
	 * @return WC_Order
	 */
	public function get_order() {
		return $this->order;
	}

	/**
	 * Sets the used payment method.
	 *
	 * @param PaymentMethodInterface $payment_method The payment method to use.
	 */
	public function set_payment_method( PaymentMethodInterface $payment_method ) {
		$this->payment_method = $payment_method;
	}

	/**
	 * Returns the used payment method, if any.
	 *
	 * @return PaymentMethodInterface|null
	 */
	public function get_payment_method(): ?PaymentMethodInterface {
		return $this->payment_method;
	}
}
