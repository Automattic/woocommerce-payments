<?php
/**
 * Class File_Storage
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\Storage;

use WC_Order;
use WCPay\Payment\Payment;

/**
 * Filesystem storage mechanism for payments.
 */
class File_Storage implements Storage_Interface {
	/**
	 * Main path for storing payments.
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * Main path for storing order payments.
	 *
	 * @var string
	 */
	protected $order_path;

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

		$this->order_path = $this->path . 'order/';

		$this->maybe_create_directory();
	}


	/**
	 * Loads the payment for an order.
	 *
	 * @param string  $id      ID of the payment.
	 * @param Payment $payment Payment to load data into.
	 */
	public function load_by_id( string $id, Payment $payment ) {
		$path = $this->path . $id . '.json';
		$data = $this->read_json_from_file( $path );
		$payment->load_data( $data );
	}

	/**
	 * Loads the payment for an order.
	 *
	 * @param WC_Order $order   The order to laod from.
	 * @param Payment  $payment Payment to load data into.
	 */
	public function load_from_order( WC_Order $order, Payment $payment ) {
		$path = $this->order_path . $order->get_id() . '.json';
		$data = $this->read_json_from_file( $path );
		$payment->load_data( $data );
	}

	/**
	 * Checks if an order has a payment.
	 *
	 * @param WC_Order $order The order to laod from.
	 * @return bool
	 */
	public function order_has_payment( WC_Order $order ) {
		$path = $this->order_path . $order->get_id() . '.json';
		return $this->get_filesystem()->file_exists( $path );
	}

	/**
	 * Saves a payment without an order.
	 *
	 * @param Payment $payment Payment to save.
	 * @return string          The ID for the payment. Generated if the payment does not have one.
	 */
	public function save( Payment $payment ): string {
		$id = $this->ensure_id( $payment );

		// Store the payment.
		$fs   = $this->get_filesystem();
		$json = wp_json_encode( $payment->get_data(), WP_DEBUG ? JSON_PRETTY_PRINT : 0 );
		$fs->put_contents( $this->path . $id . '.json', $json );

		return $id;
	}

	/**
	 * Saves a payment for an order.
	 *
	 * @param Payment  $payment Payment to save.
	 * @param WC_Order $order   Associated order.
	 */
	public function save_to_order( Payment $payment, WC_Order $order ) {
		$fs   = $this->get_filesystem();
		$json = wp_json_encode( $payment->get_data(), WP_DEBUG ? JSON_PRETTY_PRINT : 0 );
		$path = $this->order_path . $order->get_id() . '.json';
		$fs->put_contents( $path, $json );
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

	/**
	 * Creates a directory for storage if it does not exist.
	 */
	protected function maybe_create_directory() {
		if ( file_exists( $this->order_path ) ) {
			return;
		}

		// ToDo: Change permissions here, and throw an exception on failure.
		$this->get_filesystem()->mkdir( $this->path, 0777 );
		$this->get_filesystem()->mkdir( $this->order_path, 0777 );
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
	 * Ensures that a payment has an ID, and returns it.
	 *
	 * @param Payment $payment The payment object.
	 * @return string          ID of the payment, even if it did not exist before.
	 */
	protected function ensure_id( Payment $payment ) {
		// Make sure there's an ID to use.
		$id = $payment->get_id();
		if ( ! $id ) {
			$id = time() . md5( time() );
			$payment->set_id( $id );
		}
		return $id;
	}
}
