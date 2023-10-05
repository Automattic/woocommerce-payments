<?php
/**
 * Class PaymentContext
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

use WCPay\Internal\Payment\PaymentMethod\PaymentMethodInterface;

/**
 * A context object, which is shared between payment states.
 */
class PaymentContext {
	/**
	 * ID of the order, receiving a payment.
	 *
	 * @var int
	 */
	private $order_id;

	/**
	 * Contains all the context's data.
	 *
	 * @var array
	 */
	private $data = [];

	/**
	 * Constructs the class, receiving an order ID.
	 *
	 * @param int $order_id ID of the order, receiving a payment.
	 */
	public function __construct( int $order_id ) {
		$this->order_id = $order_id;
	}

	/**
	 * Returns the ID of the order requiring payment.
	 *
	 * @return int
	 */
	public function get_order_id(): ?int {
		return $this->order_id;
	}

	/**
	 * Stores an internal value.
	 * Use this method for changes to allow logging in the future.
	 *
	 * @param string $key   Property name.
	 * @param mixed  $value Value to store.
	 */
	private function set( string $key, $value ) {
		$this->data[ $key ] = $value;
	}

	/**
	 * Retrieves an internal value, if any.
	 *
	 * @param string $key Key of the property.
	 * @return mixed|null Either the stored value, or null if not set.
	 */
	private function get( string $key ) {
		return $this->data[ $key ] ?? null;
	}

	/**
	 * Stores the payment amount.
	 *
	 * @param int $amount Payment amount in cents.
	 */
	public function set_amount( int $amount ) {
		$this->set( 'amount', $amount );
	}

	/**
	 * Returns the payment amount.
	 *
	 * @return int|null Amount in cents.
	 */
	public function get_amount(): ?int {
		return $this->get( 'amount' );
	}

	/**
	 * Stores a payment method within the context.
	 *
	 * @param PaymentMethodInterface $payment_method The payment method to use.
	 */
	public function set_payment_method( PaymentMethodInterface $payment_method ) {
		$this->set( 'payment_method', $payment_method );
	}

	/**
	 * Returns the stored payment method object, if any.
	 *
	 * @return PaymentMethodInterface|null
	 */
	public function get_payment_method(): ?PaymentMethodInterface {
		return $this->get( 'payment_method' );
	}
}
