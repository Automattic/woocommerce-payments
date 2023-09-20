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
	private function store( string $key, $value ) {
		$this->data[ $key ] = $value;
	}

	/**
	 * Reads from the internal data array.
	 *
	 * @param string $key Key to read.
	 * @return mixed|null
	 */
	private function read( string $key ) {
		return $this->data[ $key ] ?? null;
	}

	/**
	 * Adds a flag to the payment.
	 *
	 * Flags are used to control small nuances in the payment
	 * processing, rather than controlling the flow.
	 *
	 * @param Flag $flag The flag to add.
	 */
	public function add_flag( Flag $flag ) {
		$flags = $this->read( 'flags' ) ?? [];

		if ( ! in_array( $flag, $flags, true ) ) {
			$flags[] = $flag;
			$this->store( 'flags', $flags );
		}
	}

	/**
	 * Checks if the payment has a certain flag.
	 *
	 * @param Flag $flag The flag to check.
	 * @return bool
	 */
	public function has_flag( Flag $flag ) {
		return in_array( $flag, $this->read( 'flags' ) ?? [], true );
	}

	/**
	 * Removes a flag from the payment, if it was set.
	 *
	 * @param Flag $flag The flag to remove.
	 */
	public function remove_flag( Flag $flag ) {
		if ( ! $this->has_flag( $flag ) ) {
			return;
		}

		$existing_flags = $this->read( 'flags' );
		$new_flags      = [];

		foreach ( $existing_flags as $existing_flag ) {
			if ( $existing_flag !== $flag ) {
				$new_flags[] = $existing_flag;
			}
		}

		$this->store( 'flags', $new_flags );
	}

	/**
	 * Sets the used payment method.
	 *
	 * @param PaymentMethodInterface $payment_method The payment method to use.
	 */
	public function set_payment_method( PaymentMethodInterface $payment_method ) {
		$this->store( 'payment_method', $payment_method );
	}

	/**
	 * Returns the used payment method, if any.
	 *
	 * @return PaymentMethodInterface|null
	 */
	public function get_payment_method(): ?PaymentMethodInterface {
		return $this->read( 'payment_method' );
	}
}
