<?php
/**
 * Class Qualitative_Feedback_Admin_Note_Removal_Test
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Migrations;

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\Notes;
use WC_Data_Store;
use WC_Payments_Notes_Qualitative_Feedback;
use WCPAY_UnitTestCase;

/**
 * WCPay\Migrations\Qualitative_Feedback_Admin_Note_Removal unit tests.
 */
class Qualitative_Feedback_Admin_Note_Removal_Test extends WCPAY_UnitTestCase {

	/**
	 * @var Qualitative_Feedback_Admin_Note_Removal
	 */
	private $migration;

	public function set_up() {
		parent::set_up();

		require_once WCPAY_ABSPATH . 'includes/notes/class-wc-payments-notes-qualitative-feedback.php';

		$this->migration = new Qualitative_Feedback_Admin_Note_Removal();
		Notes::delete_notes_with_name( WC_Payments_Notes_Qualitative_Feedback::NOTE_NAME );
	}

	public function tear_down() {
		Notes::delete_notes_with_name( WC_Payments_Notes_Qualitative_Feedback::NOTE_NAME );
		delete_option( 'woocommerce_woocommerce_payments_version' );

		parent::tear_down();
	}

	public function test_it_removes_the_note_when_upgrading_from_an_older_version() {
		update_option( 'woocommerce_woocommerce_payments_version', '11.0.0' );
		$this->create_deprecated_note();

		$this->migration->maybe_migrate();

		$this->assertSame( [], $this->get_deprecated_note_ids() );
	}

	public function test_it_removes_the_note_through_the_plugin_update_hook() {
		update_option( 'woocommerce_woocommerce_payments_version', '10.9.0' );
		$this->create_deprecated_note();

		\WC_Payments::install_actions();

		$this->assertSame( [], $this->get_deprecated_note_ids() );
	}

	public function test_it_removes_the_note_when_the_stored_version_is_missing() {
		delete_option( 'woocommerce_woocommerce_payments_version' );
		$this->create_deprecated_note();

		$this->migration->maybe_migrate();

		$this->assertSame( [], $this->get_deprecated_note_ids() );
	}

	/**
	 * @dataProvider versions_without_applying_migration_provider
	 */
	public function test_it_keeps_the_note_when_the_migration_was_already_applied( string $stored_wcpay_version ) {
		update_option( 'woocommerce_woocommerce_payments_version', $stored_wcpay_version );
		$this->create_deprecated_note();

		$this->migration->maybe_migrate();

		$this->assertNotSame( [], $this->get_deprecated_note_ids() );
	}

	public function versions_without_applying_migration_provider() {
		return [
			'same version'  => [ Qualitative_Feedback_Admin_Note_Removal::VERSION_SINCE ],
			'newer version' => [ '11.2.0' ],
		];
	}

	private function create_deprecated_note() {
		$note = new Note();
		$note->set_name( WC_Payments_Notes_Qualitative_Feedback::NOTE_NAME );
		$note->save();
	}

	private function get_deprecated_note_ids() {
		return ( WC_Data_Store::load( 'admin-note' ) )->get_notes_with_name( WC_Payments_Notes_Qualitative_Feedback::NOTE_NAME );
	}
}
