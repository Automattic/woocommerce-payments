<?php
/**
 * Class WooPay_Tracker
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use Jetpack_Tracks_Client;
use Jetpack_Tracks_Event;
use WC_Payments;
use WC_Payments_Features;
use WP_Error;

defined( 'ABSPATH' ) || exit; // block direct access.

/**
 * Track WooPay related events
 */
class WooPay_Tracker extends Jetpack_Tracks_Client {

	/**
	 * WooPay user event prefix
	 *
	 * @var string
	 */
	private static $user_prefix = 'woocommerceanalytics';

	/**
	 * WooPay admin event prefix
	 *
	 * @var string
	 */
	private static $admin_prefix = 'wcadmin';

	/**
	 * WCPay http interface.
	 *
	 * @var Object
	 */
	private $http;


	/**
	 * Constructor.
	 *
	 * @param \WC_Payments_Http_Interface $http    A class implementing WC_Payments_Http_Interface.
	 */
	public function __construct( $http ) {

		$this->http = $http;

		add_action( 'wp_ajax_platform_tracks', [ $this, 'ajax_tracks' ] );
		add_action( 'wp_ajax_nopriv_platform_tracks', [ $this, 'ajax_tracks' ] );

		// Actions that should result in recorded Tracks events.
		add_action( 'woocommerce_after_checkout_form', [ $this, 'checkout_start' ] );
		add_action( 'woocommerce_blocks_enqueue_checkout_block_scripts_after', [ $this, 'checkout_start' ] );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'checkout_order_processed' ] );
		add_action( 'woocommerce_blocks_checkout_order_processed', [ $this, 'checkout_order_processed' ] );
		add_action( 'woocommerce_payments_save_user_in_woopay', [ $this, 'must_save_payment_method_to_platform' ] );
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
			// tracksEventProp is a JSON-encoded string.
			$event_prop = json_decode( wc_clean( wp_unslash( $_REQUEST['tracksEventProp'] ) ), true );
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
		// Top level events should not be namespaced.
		if ( '_aliasUser' !== $event ) {
			$event = self::$user_prefix . '_' . $event;
		}

		return $this->tracks_record_event( $event, $data );
	}

	/**
	 * Generic method to track admin events.
	 *
	 * @param string $event name of the event.
	 * @param array  $data array of event properties.
	 */
	public function maybe_record_admin_event( $event, $data = [] ) {
		// Top level events should not be namespaced.
		if ( '_aliasUser' !== $event ) {
			$event = self::$admin_prefix . '_' . $event;
		}

		$is_admin_event = true;

		return $this->tracks_record_event( $event, $data, $is_admin_event );
	}

	/**
	 * Override parent method to omit the jetpack TOS check.
	 *
	 * @param bool $is_admin_event Indicate whether the event is emitted from admin area.
	 *
	 * @return bool
	 */
	public function should_enable_tracking( $is_admin_event = false ) {
		// Always respect the user specific opt-out cookie.
		if ( ! empty( $_COOKIE['tk_opt-out'] ) ) {
			return false;
		}

		// Track all WooPay events from the admin area.
		if ( $is_admin_event ) {
			return true;
		}

		// For all other events ensure:
		// 1. Only site pages are tracked.
		// 2. Site Admin activity in site pages are not tracked.
		// 3. Site page tracking is only enabled when WooPay is active.

		// Track only site pages.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}

		// Don't track site admins.
		if ( is_user_logged_in() && in_array( 'administrator', wp_get_current_user()->roles, true ) ) {
			return false;
		}

		// Don't track when woopay is disabled.
		$gateway            = \WC_Payments::get_gateway();
		$is_woopay_eligible = WC_Payments_Features::is_woopay_eligible(); // Feature flag.
		$is_woopay_enabled  = 'yes' === $gateway->get_option( 'platform_checkout', 'no' );
		if ( ! ( $is_woopay_eligible && $is_woopay_enabled ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Record an event in Tracks - this is the preferred way to record events from PHP.
	 *
	 * @param string $event_name             The name of the event.
	 * @param array  $properties             Custom properties to send with the event.
	 * @param bool   $is_admin_event         Indicate whether the event is emitted from admin area.
	 *
	 * @return bool|array|\WP_Error|\Jetpack_Tracks_Event
	 */
	public function tracks_record_event( $event_name, $properties = [], $is_admin_event = false ) {

		$user = wp_get_current_user();

		// We don't want to track user events during unit tests/CI runs.
		if ( $user instanceof \WP_User && 'wptests_capabilities' === $user->cap_key ) {
			return false;
		}

		if ( ! $this->should_enable_tracking( $is_admin_event ) ) {
			return false;
		}

		$event_obj = $this->tracks_build_event_obj( $user, $event_name, $properties );

		if ( is_wp_error( $event_obj ) ) {
			return $event_obj;
		}

		$pixel = $event_obj->build_pixel_url( $event_obj );

		if ( ! $pixel ) {
			return new WP_Error( 'invalid_pixel', 'cannot generate tracks pixel for given input', 400 );
		}

		return self::record_pixel( $pixel );
	}

	/**
	 * Procedurally build a Tracks Event Object.
	 *
	 * @param \WP_User $user                  WP_user object.
	 * @param string   $event_name            The name of the event.
	 * @param array    $properties            Custom properties to send with the event.
	 *
	 * @return \Jetpack_Tracks_Event|\WP_Error
	 */
	private function tracks_build_event_obj( $user, $event_name, $properties = [] ) {
		$identity = $this->tracks_get_identity( $user->ID );
		$site_url = get_option( 'siteurl' );

		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$properties['_lg']       = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
		$properties['blog_url']  = $site_url;
		$properties['blog_id']   = \Jetpack_Options::get_option( 'id' );
		$properties['user_lang'] = $user->get( 'WPLANG' );

		// Add event property for test mode vs. live mode events.
		$properties['test_mode']     = WC_Payments::mode()->is_test() ? 1 : 0;
		$properties['wcpay_version'] = WCPAY_VERSION_NUMBER;

		$blog_details = [
			'blog_lang' => isset( $properties['blog_lang'] ) ? $properties['blog_lang'] : get_bloginfo( 'language' ),
		];

		$timestamp        = round( microtime( true ) * 1000 );
		$timestamp_string = is_string( $timestamp ) ? $timestamp : number_format( $timestamp, 0, '', '' );

		/**
		 * Ignore incorrect argument definition in Jetpack_Tracks_Event.
		 *
		 * @psalm-suppress InvalidArgument
		 */
		return new \Jetpack_Tracks_Event(
			array_merge(
				$blog_details,
				(array) $properties,
				$identity,
				[
					'_en' => $event_name,
					'_ts' => $timestamp_string,
				]
			)
		);
	}

	/**
	 * Get the identity to send to tracks.
	 *
	 * @param int $user_id The user id of the local user.
	 *
	 * @return array $identity
	 */
	public function tracks_get_identity( $user_id ) {

		// Meta is set, and user is still connected.  Use WPCOM ID.
		$wpcom_id = get_user_meta( $user_id, 'jetpack_tracks_wpcom_id', true );
		if ( $wpcom_id && $this->http->is_user_connected( $user_id ) ) {
			return [
				'_ut' => 'wpcom:user_id',
				'_ui' => $wpcom_id,
			];
		}

		// User is connected, but no meta is set yet.  Use WPCOM ID and set meta.
		if ( $this->http->is_user_connected( $user_id ) ) {
			$wpcom_user_data = $this->http->get_connected_user_data( $user_id );
			update_user_meta( $user_id, 'jetpack_tracks_wpcom_id', $wpcom_user_data['ID'] );

			return [
				'_ut' => 'wpcom:user_id',
				'_ui' => $wpcom_user_data['ID'],
			];
		}

		// User isn't linked at all.  Fall back to anonymous ID.
		$anon_id = get_user_meta( $user_id, 'jetpack_tracks_anon_id', true );
		if ( ! $anon_id ) {
			$anon_id = \Jetpack_Tracks_Client::get_anon_id();
			add_user_meta( $user_id, 'jetpack_tracks_anon_id', $anon_id, false );
		}

		if ( ! isset( $_COOKIE['tk_ai'] ) && ! headers_sent() ) {
			setcookie( 'tk_ai', $anon_id );
		}

		return [
			'_ut' => 'anon',
			'_ui' => $anon_id,
		];
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
				'source' => ( isset( $_SERVER['HTTP_USER_AGENT'] ) && 'WooPay' === $_SERVER['HTTP_USER_AGENT'] ) ? 'platform' : 'standard',
			]
		);
	}

	/**
	 * Record a Tracks event that user chose to save payment information in woopay.
	 */
	public function must_save_payment_method_to_platform() {
		$this->maybe_record_event(
			'woopay_registered',
			[
				'source' => 'checkout',
			]
		);
	}

}
