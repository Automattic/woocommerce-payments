<?php
/**
 * Class Platform_Checkout_Tracker
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use Automattic\Jetpack\Tracking;
use WC_Payments_Features;

defined( 'ABSPATH' ) || exit; // block direct access.

/**
 * Track Platform Checkout related events
 */
class Platform_Checkout_Tracker extends Tracking {

	/**
	 * Platform checkout event prefix
	 *
	 * @var string
	 */
	private static $prefix = 'woocommerceanalytics';

	/**
	 * Constructor.
	 *
	 * @param \WC_Payments_Http_Interface $http    A class implementing WC_Payments_Http_Interface.
	 */
	public function __construct( $http ) {
		/**
		 * Tracking class expects a Jetpac\Connection\Manager instance.
		 * We pass in WC_Payments_Http_Interface which is a wrapper around the Connection Manager.
		 *
		 * @psalm-suppress InvalidArgument
		 */
		parent::__construct( self::$prefix, $http );
		add_action( 'wp_ajax_nopriv_jetpack_tracks', [ $this, 'ajax_tracks' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_tracks_scripts' ] );
	}

	/**
	 * Override jetpack-tracking's ajax handling to use internal record_event method.
	 */
	public function ajax_tracks() {
		// Check for nonce.
		if (
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			empty( $_REQUEST['tracksNonce'] ) || ! wp_verify_nonce( $_REQUEST['tracksNonce'], 'jp-tracks-ajax-nonce' )
		) {
			wp_send_json_error(
				__( 'You aren’t authorized to do that.', 'woocommerce-payments' ),
				403
			);
		}

		if ( ! isset( $_REQUEST['tracksEventName'] ) ) {
			wp_send_json_error(
				__( 'No valid event name or type.', 'woocommerce-payments' ),
				403
			);
		}

		$tracks_data = [];
		if ( isset( $_REQUEST['tracksEventProp'] ) ) {
			$event_prop = wc_clean( wp_unslash( $_REQUEST['tracksEventProp'] ) );
			if ( is_array( $event_prop ) ) {
				$tracks_data = $event_prop;
			} else {
				$tracks_data = [ 'clicked' => $event_prop ];
			}
		}

		$this->record_event( sanitize_text_field( wp_unslash( $_REQUEST['tracksEventName'] ) ), $tracks_data );

		wp_send_json_success();
	}

	/**
	 * Generic method to track user events.
	 *
	 * @param string $event name of the event.
	 * @param array  $data array of event properties.
	 */
	public function record_event( $event, $data = [] ) {

		if ( ! $this->should_track() ) {
			return;
		}

		$user     = wp_get_current_user();
		$site_url = get_option( 'siteurl' );

		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$data['_lg']      = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
		$data['blog_url'] = $site_url;
		$data['blog_id']  = \Jetpack_Options::get_option( 'id' );

		// Top level events should not be namespaced.
		if ( '_aliasUser' !== $event ) {
			$event = self::$prefix . '_' . $event;
		}

		return $this->tracks_record_event( $user, $event, $data );
	}

	/**
	 * Check whether tracking should be enabled.
	 *
	 * @return bool
	 */
	public function should_track() {
		// Track only site pages.
		if ( is_admin() && ! wp_doing_ajax() ) {
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
		if ( ! $this->should_track() ) {
			return;
		}

		$this->enqueue_tracks_scripts( true );
	}

	/**
	 * TODO: Track add to cart events.
	 * This is just a placeholder.
	 */
	public function track_add_to_cart() {
		$data = [];
		$this->record_event( 'order_checkout_start', $data );
	}

}
