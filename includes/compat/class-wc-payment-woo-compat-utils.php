<?php
/**
 * Class WC_Payment_Woo_Compat_Utils
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\WC_Admin_Note;

/**
 * Util class for handling compatibilities with different versions of WooCommerce core.
 */
class WC_Payment_Woo_Compat_Utils {
	/**
	 * Return non-deprecated class for instantiating WC-Admin notes.
	 *
	 * @return string
	 */
	public static function get_note_class() : string {
		if ( class_exists( 'Automattic\WooCommerce\Admin\Notes\Note' ) ) {
			return Note::class;
		} else {
			return WC_Admin_Note::class;
		}
	}
}
