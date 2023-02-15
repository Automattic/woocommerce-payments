<?php
/**
 * Class Filesystem_Storage
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Storage;

use Exception;
use WCPay\Payment_Process\Payment;
use WCPay\Payment_Process\Order_Payment;

/**
 * A class for storing and loading order payments from the filesystem.
 */
class Filesystem_Order_Storage extends Filesystem_Storage {
	/**
	 * Used to associate payments with orders through meta data.
	 *
	 * @var string
	 */
	const ORDER_META_KEY = '_wcpay_payment_key';

	/**
	 * Loads a payment from the storage.
	 *
	 * @param Payment $payment The payment object.
	 * @throws \Exception      In case the payment could not be loaded.
	 */
	public function load( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			throw new \Exception( 'The filesystem order storage requires an order payment.' );
		}

		// Load all files for the order ID.
		$fs    = $this->get_filesystem();
		$order = $payment->get_order()->get_id();
		$files = $fs->dirlist( $this->path . $order );

		if ( ! is_array( $files ) || empty( $files ) ) {
			// No dir for this order.
			return;
		}

		// Load the first available file.
		foreach ( $files as $name => $details ) {
			if ( ! preg_match( '/\.json$/', $name ) ) {
				continue;
			}

			$data = $this->read_json_from_file( $this->path . $order . '/' . $name );

			if ( ! isset( $data['id'] ) ) {
				throw new Exception( 'The payment file does not contain an identifier.' );
			}

			$id = $data['id'];
			unset( $data['id'] );

			$payment->set_id( $id );
			$payment->load_data( $data );
		}

		// Nothing to load after the foreach, until the payment is saved.
	}

	/**
	 * Generates the path for a given payment.
	 *
	 * @param Payment $payment The payment object.
	 * @return string          The full path for the file.
	 * @throws \Exception      In case the payment is not related to an order.
	 */
	protected function generate_path( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			throw new \Exception( 'The filesystem order storage requires an order payment.' );
		}

		return $this->path . $payment->get_order()->get_id() . '/' . $payment->get_id() . '.json';
	}
}
