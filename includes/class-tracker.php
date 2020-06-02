<?php
/**
 * Class Tracker
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

defined( 'ABSPATH' ) || exit; // block direct access.

/**
 * A wrapper class interacting with WC_Tracks.
 */
class Tracker {
	/**
	 * Record an event in Tracks
	 *
	 * @param string $event_name The name of the event.
	 * @param array  $properties Custom properties to send with the event.
	 * @return bool|WP_Error True for success or WP_Error if the event pixel could not be fired.
	 */
	public static function track( $event_name, $properties = [] ) {
		error_log( var_export( 'Tracker: track called', true ) . PHP_EOL . __FILE__  );
		if ( ! class_exists( 'Core_Tracks_Wrapper' ) ) {
			error_log( var_export( 'N wrapper class so no tracking', true ) . PHP_EOL . __FILE__  );
			return false;
		}

		error_log( var_export( 'recording in main API' . $event_name, true ) . PHP_EOL . __FILE__  );

		return;
		return self::record_event;
	}
}
