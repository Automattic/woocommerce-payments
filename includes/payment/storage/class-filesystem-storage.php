<?php
/**
 * Class Filesystem_Storage
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\Storage;

use WP_Filesystem_Base;
use Exception;
use WCPay\Payment\Payment;

/**
 * A class for storing and loading payments from the filesystem.
 */
class Filesystem_Storage implements Payment_Storage {
	/**
	 * Main path for storing payments.
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * Initializes the storage and its directory.
	 */
	public function __construct() {
		$uploads_dir = wp_upload_dir();

		/**
		 * Allows the directory where WCPay stores payments.
		 *
		 * @param string $path The path to be used.
		 */
		$this->path = apply_filters( 'wcpay_payments_storage_dir', $uploads_dir['basedir'] . '/wcpay-payments/' );

		$this->maybe_create_directory();
	}

	/**
	 * Stores the payment.
	 *
	 * @param Payment $payment The payment object.
	 * @throws \Exception      In case the payment could not be stored.
	 */
	public function store( Payment $payment ) {
		$fs   = $this->get_filesystem();
		$data = $payment->get_data();

		// Load the ID or generate a new one if needed.
		$id = $payment->get_id();
		if ( ! $id ) {
			$id = time() . md5( time() );
			$payment->set_id( $id );
		}

		// Add the ID to the data.
		$data['id'] = $id;

		// Finally, store it.
		$path = $this->generate_path( $payment );

		// Make sure there's a directory.
		$dir = dirname( $path );
		if ( ! $fs->exists( $dir ) ) {
			$fs->mkdir( $dir );
		}

		$json = wp_json_encode( $data, WP_DEBUG ? JSON_PRETTY_PRINT : 0 );
		$fs->put_contents( $path, $json );
	}

	/**
	 * Loads a payment from the storage. The mayment must have an ID if its being loaded.
	 *
	 * @param Payment $payment The payment object.
	 * @throws \Exception      In case the payment could not be loaded.
	 */
	public function load( Payment $payment ) {
		if ( ! $payment->get_id() ) {
			throw new Exception( 'Cannot load a payment without an ID from the filesystem.' );
		}

		$data = $this->read_json_from_file( $this->generate_path( $payment ) );

		if ( ! isset( $data['id'] ) ) {
			throw new Exception( 'The payment file does not contain an identifier.' );
		}

		$id = $data['id'];
		unset( $data['id'] );

		$payment->set_id( $id );
		$payment->load_data( $data );
	}

	/**
	 * Deletes a payment from storage.
	 *
	 * @param Payment $payment The payment object.
	 * @return bool Whether it was possible to delete the payment.
	 */
	public function delete( Payment $payment ) {
		$path = $this->generate_path( $payment );
		$fs   = $this->get_filesystem();

		return $fs->delete( $path );
	}

	/**
	 * Attempts to read a file, and parse its content to JSON.
	 *
	 * @param string $path The path to the file.
	 * @return array       Parsed JSON data as an array.
	 * @throws \Exception  Whenever the file could not be read.
	 */
	protected function read_json_from_file( string $path ) {
		$fs = $this->get_filesystem();

		$options = [
			'associative' => true,
		];

		if ( $fs->exists( $path ) ) {
			$data = wp_json_file_decode( $path, $options );

			if ( ! empty( $data ) ) {
				return $data;
			}
		}

		throw new \Exception( 'Payment file does not exist, cannot be opened, or does not contain valid data.' );
	}

	/**
	 * Generates the path for a given payment.
	 *
	 * @param Payment $payment The payment object.
	 * @return string          The full path for the file.
	 */
	protected function generate_path( Payment $payment ) {
		return $this->path . $payment->get_id() . '.json';
	}

	/**
	 * Creates a directory for storage if it does not exist.
	 */
	protected function maybe_create_directory() {
		if ( file_exists( $this->path ) ) {
			return;
		}

		// ToDo: Change permissions here, and throw an exception on failure.
		$this->get_filesystem()->mkdir( $this->path, 0777 );
	}

	/**
	 * Returns a new instance of the filesystem.
	 *
	 * @return WP_Filesystem_Base
	 */
	protected function get_filesystem() {
		require_once ABSPATH . '/wp-admin/includes/file.php';
		\WP_Filesystem();
		return $GLOBALS['wp_filesystem'];
	}
}
