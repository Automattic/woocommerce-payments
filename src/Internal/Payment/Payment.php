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
	 * Holds all data, related to the payment.
	 *
	 * @var array
	 */
	private $data = [];

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
	 * Stores data within the payment.
	 *
	 * @param string $key   Key of the property to store.
	 * @param mixed  $value Value to store.
	 */
	private function set( string $key, $value ) {
		$this->data[ $key ] = $value;
	}

	/**
	 * Reads from the internal data array.
	 *
	 * @param string $key Key to read.
	 * @return mixed|null
	 */
	private function get( string $key ) {
		return $this->data[ $key ] ?? null;
	}

	/**
	 * Adds a flag to the payment.
	 *
	 * Flags are used to control small nuances in the payment
	 * processing, rather than controlling the flow.
	 *
	 * @param string $flag The flag to add.
	 */
	public function add_flag( string $flag ) {
		$flags = $this->get( 'flags' ) ?? [];

		if ( ! in_array( $flag, $flags, true ) ) {
			$flags[] = $flag;
			$this->set( 'flags', $flags );
		}
	}

	/**
	 * Checks if the payment has a certain flag.
	 *
	 * @param string $flag The flag to check.
	 * @return bool
	 */
	public function has_flag( string $flag ) {
		return in_array( $flag, $this->get( 'flags' ) ?? [], true );
	}

	/**
	 * Removes a flag from the payment, if it was set.
	 *
	 * @param string $flag The flag to remove.
	 */
	public function remove_flag( string $flag ) {
		$flags = $this->get( 'flags' );
		$index = array_search( $flag, $flags, true );

		if ( false !== $index ) {
			unset( $flags['index'] );
		}

		$this->set( 'flags', $flags );
	}

	/**
	 * Returns all flags of the payment.
	 *
	 * @return string[]
	 */
	public function get_flags() {
		return $this->get( 'flags' ) ?? [];
	}

	/**
	 * Sets the used payment method.
	 *
	 * @param PaymentMethodInterface $payment_method The payment method to use.
	 */
	public function set_payment_method( PaymentMethodInterface $payment_method ) {
		$this->set( 'payment_method', $payment_method );
	}

	/**
	 * Returns the used payment method, if any.
	 *
	 * @return PaymentMethodInterface|null
	 */
	public function get_payment_method(): ?PaymentMethodInterface {
		return $this->get( 'payment_method' );
	}
}
