<?php
/**
 * Class Filesystem_Storage
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\Storage;

use Exception;
use WCPay\Payment\Payment;

/**
 * A class for storing and loading order payments from the filesystem.
 */
class Filesystem_Order_Storage extends Filesystem_Storage {
	/**
	 * Loads a payment from the storage.
	 *
	 * @param Payment $payment The payment object.
	 * @throws \Exception      In case the payment could not be loaded.
	 */
	public function load( Payment $payment ) {
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
		return $this->path . $payment->get_order()->get_id() . '/' . $payment->get_id() . '.json';
	}
}
