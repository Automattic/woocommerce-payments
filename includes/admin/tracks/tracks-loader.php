<?php
/**
 * Class Core Tracks Wrapper
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use WC_Tracks;

defined( 'ABSPATH' ) || exit; // block direct access.

/**
 * Moves all events from Tracker to Tracks loader
 */
function record_tracker_events() {
	foreach ( Tracker::get_admin_events() as $event => $properties ) {
		WC_Tracks::record_event( $event, $properties );
		Tracker::remove_admin_event( $event );
	}
}

// Loaded on admin_init to ensure that we are in admin and that WC_Tracks is loaded.
add_action(
	'admin_init',
	function () {
		if ( ! class_exists( 'WC_Tracks' ) ) {
			return;
		}

		// Move all events with priority 1 just before the admin_footer hook adds footer pixels.
		add_action( 'admin_footer', 'WCPay\record_tracker_events', 1 );

		/**
		 * Send all events that were not handled in `admin_footer`.
		 *
		 * Between shutdown and admin footer many things can happen. Admin footer loads
		 * scripts in the markup images that will call tracks from the browsers
		 * side (which means it's faster as we're not doing network calls to wp.com server
		 * side). Anything that is added afterward must be sent from the
		 * server, but doing it on shutdown means it's not blocking anything.
		 */
		add_action( 'shutdown', __NAMESPACE__ . '\\record_tracker_events', 1 );
	}
);
