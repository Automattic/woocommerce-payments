<?php
/**
 * Class Platform_Checkout_Tracker
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use WC_Payments_Features;

defined( 'ABSPATH' ) || exit; // block direct access.

/**
 * Track Platform Checkout related events
 */
class Platform_Checkout_Tracker {

	/**
	 * Instance of Jetpack Tracking
	 *
	 * @var Tracking
	 */
	private static $tracking = null;

	/**
	 * Platform checkout event prefix
	 *
	 * @var string
	 */
	private static $prefix = 'platform_checkout';

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_Http_Interface $http    A class implementing WC_Payments_Http_Interface.
	 */
	public function __construct( $http ) {

		self::$tracking = new \Automattic\Jetpack\Tracking( self::$prefix, $http );

		add_action( 'woocommerce_add_to_cart', [ $this, 'track_add_to_cart' ], 10, 6 );
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_tracks_scripts' ] );
	}

	/**
	 * Track add to cart events.
	 */
	public function track_add_to_cart() {
		$data = [];
		$this->record_user_event( 'order_checkout_start', $data );
	}

	/**
	 * Generic method to track user events.
	 *
	 * @param string $event name of the event.
	 * @param array  $props array of event properties.
	 */
	public function record_user_event( $event, $props = [] ) {
		if ( ! $this->should_enable_tracking() ) {
			return;
		}
		self::$tracking->record_user_event( $event, $props );
	}

	/**
	 * Check whether tracking should be enabled.
	 *
	 * @return bool
	 */
	public function should_enable_tracking() {
		// Tracking only Site pages.
		if ( is_admin() ) {
			return false;
		}

		// Don't track site admins.
		if ( is_user_logged_in() && in_array( 'administrator', wp_get_current_user()->roles, true ) ) {
			return false;
		}

		// Don't track when platform checkout is disabled.
		if ( ! WC_Payments_Features::is_platform_checkout_enabled() ) {
			return false;
		}

		// TODO: Don't track if jetpack_tos_agreed flag is not present.

		return true;
	}

	/**
	 * Enqueue Ajax tracking scripts if the store is trackable.
	 *
	 * @return void
	 */
	public function maybe_enqueue_tracks_scripts() {
		// Don't enqueue tracking scripts if we cannot track.
		if ( ! $this->should_enable_tracking() ) {
			return;
		}

		self::$tracking->enqueue_tracks_scripts( true );
	}
}
