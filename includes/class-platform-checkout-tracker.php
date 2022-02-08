<?php
/**
 * Class Platform_Checkout_Tracker
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

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
		// TODO: dont track when
		// platform checkout is disabled
		// on admin environment
		// on feature flag
		// tos?
		self::$tracking->record_user_event( $event, $props );
	}

}
