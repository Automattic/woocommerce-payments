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
		 * Tracking class expects a Jetpack\Connection\Manager instance.
		 * We pass in WC_Payments_Http_Interface which is a wrapper around the Connection Manager.
		 *
		 * @psalm-suppress InvalidArgument
		 */
		parent::__construct( self::$prefix, $http );
		add_action( 'wp_ajax_platform_tracks', [ $this, 'ajax_tracks' ] );
		add_action( 'wp_ajax_nopriv_platform_tracks', [ $this, 'ajax_tracks' ] );

		// Actions that should result in recorded Tracks events.
		add_action( 'woocommerce_after_checkout_form', [ $this, 'checkout_start' ] );
		add_action( 'woocommerce_blocks_enqueue_checkout_block_scripts_after', [ $this, 'checkout_start' ] );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'checkout_order_processed' ] );
		add_action( 'woocommerce_blocks_checkout_order_processed', [ $this, 'checkout_order_processed' ] );
	}

	/**
	 * Override jetpack-tracking's ajax handling to use internal maybe_record_event method.
	 */
	public function ajax_tracks() {
		// Check for nonce.
		if (
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			empty( $_REQUEST['tracksNonce'] ) || ! wp_verify_nonce( $_REQUEST['tracksNonce'], 'platform_tracks_nonce' )
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
			}
		}

		$this->maybe_record_event( sanitize_text_field( wp_unslash( $_REQUEST['tracksEventName'] ) ), $tracks_data );

		wp_send_json_success();
	}

	/**
	 * Generic method to track user events.
	 *
	 * @param string $event name of the event.
	 * @param array  $data array of event properties.
	 */
	public function maybe_record_event( $event, $data = [] ) {

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
		$gateway                              = \WC_Payments::get_gateway();
		$is_platform_checkout_feature_enabled = WC_Payments_Features::is_platform_checkout_enabled(); // Feature flag.
		$is_platform_checkout_enabled         = 'yes' === $gateway->get_option( 'platform_checkout', 'no' );
		if ( ! ( $is_platform_checkout_feature_enabled && $is_platform_checkout_enabled ) ) {
			return false;
		}

		// TODO: Don't track if jetpack_tos_agreed flag is not present.

		return true;
	}

	/**
	 * Record a Tracks event that the checkout has started.
	 */
	public function checkout_start() {
		$this->maybe_record_event( 'order_checkout_start' );
	}

	/**
	 * Record a Tracks event that the order has been processed.
	 */
	public function checkout_order_processed() {
		$this->maybe_record_event(
			'order_checkout_complete',
			[
				'source' => isset( $_SERVER['HTTP_X_WCPAY_PLATFORM_CHECKOUT_USER'] ) ? 'platform' : 'standard',
			]
		);
	}

}
