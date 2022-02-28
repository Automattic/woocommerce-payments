<?php
/**
 * WooCommerce inbox remote note service.
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Exceptions\Rest_Request_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Payments_Remote_Note_Service
 */
class WC_Payments_Remote_Note_Service {
	const NOTE_NAME_PREFIX = 'wc-payments-remote-notes-';

	/**
	 * Notes data store.
	 *
	 * @var WC_Data_Store
	 */
	private $note_data_store;

	/**
	 * Class constructor.
	 *
	 * @param WC_Data_Store $note_data_store WC Admin note data store.
	 */
	public function __construct( WC_Data_Store $note_data_store ) {
		$this->note_data_store = $note_data_store;
	}

	/**
	 * Puts the given note data in the inbox if it hasn't been added before.
	 *
	 * @param  array $note_data  Note data from the API.
	 *
	 * @return bool True if the note has been added.
	 *
	 * @throws Rest_Request_Exception If note data is invalid.
	 */
	public function put_note( array $note_data ) : bool {
		$note = $this->create_note( $note_data );

		if ( ! $this->can_note_be_added( $note->get_name() ) ) {
			return false;
		}

		$this->note_data_store->create( $note );
		return true;
	}

	/**
	 * Creates a new instance of a note from API note data.
	 *
	 * @param array $note_data The note data to process.
	 *
	 * @return Automattic\WooCommerce\Admin\Notes\WC_Admin_Note|Automattic\WooCommerce\Admin\Notes\Note Note object.
	 *
	 * @throws Rest_Request_Exception If note data is invalid.
	 */
	private function create_note( array $note_data ) {
		if ( ! isset( $note_data['title'], $note_data['content'] ) ) {
			throw new Rest_Request_Exception( 'Invalid note.' );
		}

		$title     = $note_data['title'];
		$content   = $note_data['content'];
		$note_name = self::NOTE_NAME_PREFIX . ( $note_data['name'] ?? md5( $title . $content ) );

		$note_class = WC_Payment_Woo_Compat_Utils::get_note_class();
		$note       = new $note_class();

		$note->set_title( $title );
		$note->set_content( $content );
		$note->set_content_data( (object) [] );
		$note->set_type( $note_class::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_name( $note_name );
		$note->set_source( 'woocommerce-payments' );

		if ( isset( $note_data['actions'] ) && is_array( $note_data['actions'] ) ) {
			foreach ( $note_data['actions'] as $action_key => $action ) {
				if ( ! isset( $action['label'], $action['url'] ) ) {
					throw new Rest_Request_Exception( 'Invalid note.' );
				}

				if ( 'wcpay_settings' === $action['url'] ) {
					$url = WC_Payment_Gateway_WCPay::get_settings_url();
				} else {
					throw new Rest_Request_Exception( 'Invalid note.' );
				}

				$note->add_action(
					$note_name . '-' . $action_key,
					$action['label'],
					$url,
					$action['status'] ?? $note_class::E_WC_ADMIN_NOTE_ACTIONED,
					$action['primary'] ?? false
				);
			}
		}

		return $note;
	}

	/**
	 * Deletes all of the notes where names are prefixed with the value of the NOTE_NAME_PREFIX constant.
	 *
	 * @return void
	 */
	public function delete_notes() {
		global $wpdb;
		$prefix = self::NOTE_NAME_PREFIX;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wc_admin_note_actions WHERE name LIKE '{$prefix}%'" );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wc_admin_notes WHERE name LIKE '{$prefix}%'" );
	}

	/**
	 * Checks if the note can be added.
	 *
	 * @param string $note_name Name of the note to add.
	 *
	 * @return boolean True if the note can be added.
	 */
	private function can_note_be_added( string $note_name ) : bool {
		$note_ids = $this->note_data_store->get_notes_with_name( $note_name );
		return empty( $note_ids );
	}
}
