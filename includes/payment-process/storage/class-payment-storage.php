<?php
/**
 * Class Filesystem_Storage
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Storage;

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
	 * @param string $key     The key of the payment.
	 * @param array  $payment The payment data.
	 *
	 * @throws \Exception In case the payment could not be stored.
	 */
	public function store( string $key, array $payment );

	/**
	 * Loads a payment from the storage.
	 *
	 * @param string $key The key of the payment.
	 * @return array      Payment data.
	 *
	 * @throws \Exception In case the payment could not be loaded.
	 */
	public function load( string $key );
}
