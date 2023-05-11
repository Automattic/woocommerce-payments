<?php
/**
 * Interface Storage
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\Storage;

use WC_Order;
use WCPay\Payment\Payment;

/**
 * Reprensents a storage mechanism for payments.
 */
interface Storage_Interface {
	/**
	 * Loads the payment for an order.
	 *
	 * @param string  $id      ID of the payment.
	 * @param Payment $payment Payment to load data into.
	 */
	public function load_by_id( string $id, Payment $payment );

	/**
	 * Loads the payment for an order.
	 *
	 * @param WC_Order $order   The order to laod from.
	 * @param Payment  $payment Payment to load data into.
	 */
	public function load_from_order( WC_Order $order, Payment $payment );

	/**
	 * Saves a payment without an order.
	 *
	 * @param Payment $payment Payment to save.
	 * @return string          The ID for the payment. Generated if the payment does not have one.
	 */
	public function save( Payment $payment ): string;

	/**
	 * Saves a payment for an order.
	 *
	 * @param Payment  $payment Payment to save.
	 * @param WC_Order $order   Associated order.
	 */
	public function save_to_order( Payment $payment, WC_Order $order );
}
