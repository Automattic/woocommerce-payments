<?php
/**
 * Class Track_Upe_Status
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

defined( 'ABSPATH' ) || exit;

use WCPay\Tracker;

/**
 * Class Track_Upe_Status
 *
 * Fires an event on plugin upgrade to track whether UPE is enabled.
 * Runs only once. We want to know whether existing install had it
 * enabled before the current version, when we started to track it.
 */
class Track_Upe_Status {

	const IS_TRACKED_OPTION = 'wcpay_upe_is_tracked';

	/**
	 * Checks whether we should trigger the event.
	 */
	public static function maybe_track() {
		// UPE toggling is already being tracked. No need to trigger any event.
		if ( get_option( self::IS_TRACKED_OPTION ) ) {
			return;
		}

		// No need to track on new installs. We want the data
		// from existing ones. New installs will have the events built-in.
		if ( ! get_option( 'woocommerce_woocommerce_payments_version' ) ) {
			self::mark_as_tracked();
			return;
		}

		// Track the event and flag the install so this doesn't run again.
		self::trigger_track_event();
		self::mark_as_tracked();
	}

	/**
	 * Flags the install as a tracked one.
	 */
	private static function mark_as_tracked() {
		add_option( self::IS_TRACKED_OPTION, '1' );
	}

	/**
	 * Triggers the actual track event.
	 */
	private static function trigger_track_event() {
		$event = \WC_Payments_Features::is_upe_enabled() ? 'wcpay_upe_enabled' : 'wcpay_upe_disabled';
		Tracker::track_admin( $event );
	}
}
