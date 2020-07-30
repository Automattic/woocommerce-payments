<?php
/**
 * Class Tracker
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

defined( 'ABSPATH' ) || exit; // block direct access.

/**
 * An API for adding track events that will get unloaded
 * at a later stage and pushed to WP.com.
 */
class Tracker {
	/**
	 * A key value array event_name => properties.
	 *
	 * @var array $admin_events
	 */
	protected static $admin_events = [];

	/**
	 * Record an event in Tracks
	 *
	 * This only sends track events for admin logged in users. This is a limitation of the
	 * WC_Tracks and related classes.
	 *
	 * The event name will be prefixed before sending it see Core_Tracks_Wrapper.
	 *
	 * @param string $event_name The name of the event.
	 * @param array  $properties Custom properties to send with the event.
	 */
	public static function track_admin( $event_name, $properties = [] ) {
		self::$admin_events[ $event_name ] = $properties;
	}

	/**
	 * Remove a track event.
	 *
	 * @param string $event_name The name of the event that should be removed.
	 */
	public static function remove_admin_event( $event_name ) {
		if ( isset( self::$admin_events ) ) {
			unset( self::$admin_events[ $event_name ] );
		}
	}

	/**
	 * Remove a track event.
	 */
	public static function get_admin_events() {
		return self::$admin_events;
	}
}
