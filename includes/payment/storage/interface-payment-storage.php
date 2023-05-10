<?php
/**
 * Class Filesystem_Storage
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\Storage;

use WCPay\Payment\Payment;

/**
 * An interface for storing payments.
 *
 * This would allow us to pick the best storage option based
 * on the particular site.
 */
interface Payment_Storage {
	/**
	 * Stores the payment.
	 *
	 * @param Payment $payment The payment object.
	 * @throws \Exception      In case the payment could not be stored.
	 */
	public function store( Payment $payment );

	/**
	 * Loads a payment from the storage.
	 *
	 * @param Payment $payment The payment object.
	 * @return void
	 * @throws \Exception      In case the payment could not be loaded.
	 */
	public function load( Payment $payment );

	/**
	 * Deletes a payment from storage.
	 *
	 * @param Payment $payment The payment object.
	 * @return bool Whether it was possible to delete the payment.
	 */
	public function delete( Payment $payment );
}
