<?php
/**
 * Class Core Tracks Wrapper
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use WC_Tracks;

defined( 'ABSPATH' ) || exit; // block direct access.

// Loaded on admin_init to ensure that we are in admin and that WC_Tracks is loaded.
add_action(
	'admin_init',
	function() {
		if ( ! class_exists( 'WC_Tracks' ) ) {
			return;
		}

		/**
		 * Move all events from Tracker to Tracks loader
		 * with priority 1 just before the admin_footer hook adds footer pixels
		 */
		add_action(
			'admin_footer',
			function() {
				foreach ( Tracker::get_admin_events() as $event => $properties ) {
					WC_Tracks::record_event( $event, $properties );
					Tracker::remove_admin_event( $event );
				}
			},
			1
		);

		/**
		 * Send all events that were not handled in `admin_footer`.
		 */
		add_action(
			'shutdown',
			function() {
				foreach ( Tracker::get_admin_events() as $event => $properties ) {
					WC_Tracks::record_event( $event, $properties );
					Tracker::remove_admin_event( $event );
				}
			},
			1
		);
	}
);

