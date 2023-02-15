<?php
/**
 * Class Filesystem_Storage
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Storage;

use WCPay\Payment_Process\Payment;
use WP_Filesystem_Base;
use Exception;

/**
 * A class for storing and loading payments from the filesystem.
 */
class Filesystem_Storage implements Payment_Storage {
	/**
	 * Main path for storing payments.
	 *
	 * @var string
	 */
	private $path;

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
	 * @param string $key     The key of the payment.
	 * @param array  $payment The payment data.
	 *
	 * @throws \Exception In case the payment could not be stored.
	 */
	public function store( string $key, array $payment ) {
		$full_path = $this->generate_path( $key );
		$json      = wp_json_encode( $payment, WP_DEBUG ? JSON_PRETTY_PRINT : 0 );

		$this->get_filesystem()->put_contents( $full_path, $json );
	}

	/**
	 * Loads a payment from the storage.
	 *
	 * @param string $key The key of the payment.
	 * @return array      Payment data.
	 *
	 * @throws \Exception In case the payment could not be loaded.
	 */
	public function load( string $key ) {
		$fs        = $this->get_filesystem();
		$full_path = $this->generate_path( $key );

		if ( $fs->exists( $full_path ) ) {
			$options = [
				'associative' => true,
			];
			$payment = wp_json_file_decode( $full_path, $options );
			if ( ! empty( $payment ) ) {
				return $payment;
			}
		}

		throw new Exception( 'Could not load the existing payment!' );
	}

	/**
	 * Generates the path for a given payment.
	 *
	 * @param string $key The key for the payment.
	 * @return string     The full path for the file.
	 */
	protected function generate_path( $key ) {
		return $this->path . $key . '.json';
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
