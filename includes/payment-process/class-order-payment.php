<?php
namespace WCPay\Payment_Process;

use WC_Order;

class Order_Payment extends Payment {
	/**
	 * Used to associate payments with orders through meta data.
	 *
	 * @var string
	 */
	const ORDER_META_KEY = '_wcpay_payment_key';

	/**
	 * Holds the order, which is/will be paid.
	 *
	 * @var WC_Order
	 */
	protected $order;

	/**
	 * Either loads the payment data from an order, or associates it.
	 *
	 * @param WC_Order $order The order that the payment is/should be associated with.
	 */
	public function load_from_order( WC_Order $order ) {
		// Store the order.
		$this->order = $order;
		$existing    = null;

		// Try retrieving the data of an existing payment.
		$stored_key = $order->get_meta( self::ORDER_META_KEY );
		if ( $stored_key ) {
			$existing = $this->payment_storage->load( $stored_key );
		}

		if ( $existing ) {
			$this->id = $stored_key;
			$this->load_data( $existing );
		} else {
			// Generate a key based on the order, but also one to indicate duplicates.
			$this->id = $order->get_id() . '-' . md5( time() );
			$this->save();

			// Update & save the order.
			$order->update_meta_data( self::ORDER_META_KEY, $this->id );
			$order->save();
		}
	}

	/**
	 * Saves the payment data.
	 */
	public function save() {
		$this->payment_storage->store( $this->id, $this->get_data() );
	}
}
