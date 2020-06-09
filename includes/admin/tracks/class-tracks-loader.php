<?php
/**
 * Class Core Tracks Wrapper
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use WC_Tracks;
use WC_Tracks_Client;

defined( 'ABSPATH' ) || exit; // block direct access.

add_action(
	'admin_init',
	function() {
		if ( ! class_exists( 'WC_Tracks' ) ) {
			return;
		}

		/**
		 * A wrapper class interacting with WC_Tracks.
		 *
		 * Needs to be loaded after admin init to ensure WC_Tracks
		 * have been loaded.
		 *
		 * Setting up the wcpay prefix.
		 */
		class Tracks_Loader extends WC_Tracks {

			/**
			 * Tracks event name prefix.
			 */
			const PREFIX = 'wcpay_';

			/**
			 * Overriding method as constant prefix will not work from child class.
			 *
			 * Record an event in Tracks - this is the preferred way to record events from PHP.
			 *
			 * @param string $event_name The name of the event.
			 * @param array  $properties Custom properties to send with the event.
			 * @return bool|WP_Error True for success or WP_Error if the event pixel could not be fired.
			 */
			public static function record_event( $event_name, $properties = [] ) {
				/**
				 * Don't track users who don't have tracking enabled.
				 */
				if ( ! \WC_Site_Tracking::is_tracking_enabled() ) {
					return false;
				}

				$user = wp_get_current_user();

				// We don't want to track user events during unit tests/CI runs.
				if ( $user instanceof \WP_User && 'wptests_capabilities' === $user->cap_key ) {
					return false;
				}
				$prefixed_event_name = self::PREFIX . $event_name;

				$data = [
					'_en' => $prefixed_event_name,
					'_ts' => \WC_Tracks_Client::build_timestamp(),
				];

				$server_details = self::get_server_details();
				$identity       = \WC_Tracks_Client::get_identity( $user->ID );
				$blog_details   = self::get_blog_details( $user->ID );

				// Allow event props to be filtered to enable adding site-wide props.
				$filtered_properties = apply_filters( 'woocommerce_tracks_event_properties', $properties, $prefixed_event_name );

				// Delete _ui and _ut protected properties.
				unset( $filtered_properties['_ui'] );
				unset( $filtered_properties['_ut'] );

				$event_obj = new \WC_Tracks_Event( array_merge( $data, $server_details, $identity, $blog_details, $filtered_properties ) );

				if ( is_wp_error( $event_obj->error ) ) {
					return $event_obj->error;
				}

				return $event_obj->record();
			}
		}

		/**
		 * Move all events from Tracker to Tracks loader
		 * with priority 1 just before the admin_footer hook adds footer pixels
		 */
		add_action(
			'admin_footer',
			function() {
				foreach ( Tracker::get_admin_events() as $event => $properties ) {
					Tracks_Loader::record_event( $event, $properties );
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
					Tracks_Loader::record_event( $event, $properties );
					Tracker::remove_admin_event( $event );
				}
			},
			1
		);
	}
);

