<?php
/**
 * Class Payment
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

use WCPay\Internal\Payment\PaymentMethod\PaymentMethodInterface;

/**
 * Payment object.
 *
 * This is a data object, used as context and storage for payment states.
 */
class Payment {
	/**
	 * ID of the order requiring/having payment.
	 *
	 * @var int
	 */
	private $order_id;

	/**
	 * Holds all data, related to the payment.
	 *
	 * @var array
	 */
	private $data = [];

	/**
	 * Payment constructor.
	 *
	 * @param int $order_id Order requiring payment.
	 */
	public function __construct( int $order_id ) {
		$this->order_id = $order_id;
	}

	/**
	 * Returns the payment's order ID.
	 *
	 * @return int
	 */
	public function get_order_id() {
		return $this->order_id;
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
