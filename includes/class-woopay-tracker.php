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
use WCPay\Constants\Country_Code;
use WP_Error;

defined( 'ABSPATH' ) || exit; // block direct access.

/**
 * Track WooPay related events
 */
class WooPay_Tracker extends Jetpack_Tracks_Client {

	/**
	 * WCPay user event prefix
	 *
	 * @var string
	 */
	private static $user_prefix = 'wcpay';

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
	 * Base URL for stats counter.
	 *
	 * @var string
	 */
	private static $pixel_base_url = 'https://pixel.wp.com/g.gif';


	/**
	 * Constructor.
	 *
	 * @param \WC_Payments_Http_Interface $http    A class implementing WC_Payments_Http_Interface.
	 */
	public function __construct( $http ) {

		$this->http = $http;

		add_action( 'wp_ajax_platform_tracks', [ $this, 'ajax_tracks' ] );
		add_action( 'wp_ajax_nopriv_platform_tracks', [ $this, 'ajax_tracks' ] );
		add_action( 'wp_ajax_get_identity', [ $this, 'ajax_tracks_id' ] );
		add_action( 'wp_ajax_nopriv_get_identity', [ $this, 'ajax_tracks_id' ] );

		// Actions that should result in recorded Tracks events.
		add_action( 'woocommerce_after_checkout_form', [ $this, 'classic_checkout_start' ] );
		add_action( 'woocommerce_after_cart', [ $this, 'classic_cart_page_view' ] );
		add_action( 'woocommerce_after_single_product', [ $this, 'classic_product_page_view' ] );
		add_action( 'woocommerce_blocks_enqueue_checkout_block_scripts_after', [ $this, 'blocks_checkout_start' ] );
		add_action( 'woocommerce_blocks_enqueue_cart_block_scripts_after', [ $this, 'blocks_cart_page_view' ] );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'checkout_order_processed' ], 10, 2 );
		add_action( 'woocommerce_store_api_checkout_order_processed', [ $this, 'checkout_order_processed' ], 10, 2 );
		add_action( 'woocommerce_payments_save_user_in_woopay', [ $this, 'must_save_payment_method_to_platform' ] );
		add_action( 'wp_footer', [ $this, 'add_frontend_tracks_scripts' ] );
		add_action( 'before_woocommerce_pay_form', [ $this, 'pay_for_order_page_view' ] );
		add_action( 'woocommerce_thankyou', [ $this, 'thank_you_page_view' ] );
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
	 * Get tracks ID of the current user
	 */
	public function ajax_tracks_id() {
		$tracks_id = $this->tracks_get_identity();

		if ( $tracks_id ) {
			wp_send_json_success( $tracks_id );
		}
	}


	/**
	 * Generic method to track user events on WooPay enabled stores.
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
	 * Track shopper events with the wcpay_prefix.
	 *
	 * @param string $event name of the event.
	 * @param array  $data array of event properties.
	 * @param bool   $record_on_frontend whether to record the event on the frontend to prevent cache break.
	 */
	public function maybe_record_wcpay_shopper_event( $event, $data = [], $record_on_frontend = true ) {
		$is_admin_event      = false;
		$track_on_all_stores = true;

		// Record the event immediately.
		if ( ! $record_on_frontend ) {
			// Top level events should not be namespaced.
			if ( '_aliasUser' !== $event ) {
				$event = self::$user_prefix . '_' . $event;
			}
			return $this->tracks_record_event( $event, $data, $is_admin_event, $track_on_all_stores );
		}

		// Route the event through frontend to avoid setting cookies on page load.
		$data['record_event_data'] = compact( 'is_admin_event', 'track_on_all_stores' );

		add_filter(
			'wcpay_frontend_tracks',
			function ( $tracks ) use ( $event, $data ) {
				$tracks[] = [
					'event'      => $event,
					'properties' => $data,
				];

				return $tracks;
			}
		);
	}

	/**
	 * Generic method to track admin events on all WCPay stores.
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
	 * Check whether the store country is eligible for Tracks.
	 *
	 * @return bool
	 */
	public function is_country_tracks_eligible() {
		if ( ! function_exists( 'wc_get_base_location' ) ) {
			return false;
		}

		$store_base_location = wc_get_base_location();
		return ! empty( $store_base_location['country'] ) && Country_Code::UNITED_STATES === $store_base_location['country'];
	}


	/**
	 * Override parent method to omit the jetpack TOS check and include custom tracking conditions.
	 *
	 * @param bool $is_admin_event      Indicate whether the event is emitted from admin area.
	 * @param bool $track_on_all_stores Indicate whether the event should be tracked on all stores.
	 *
	 * @return bool
	 */
	public function should_enable_tracking( $is_admin_event = false, $track_on_all_stores = false ) {

		// Don't track if the gateway is not enabled.
		$gateway = \WC_Payments::get_gateway();
		if ( ! $gateway->is_enabled() ) {
			return false;
		}

		// Don't track if the account is not connected.
		$account = WC_Payments::get_account_service();
		if ( is_null( $account ) || ! $account->is_stripe_connected() ) {
			return false;
		}

		// Don't track any non-US stores.
		if ( ! $this->is_country_tracks_eligible() ) {
			return false;
		}

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
		// 3. If track_on_all_stores is enabled, track all events regardless of WooPay eligibility.
		// 4. Otherwise, track only when WooPay is active.

		// Track only site pages.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}

		// Don't track site admins.
		if ( is_user_logged_in() && in_array( 'administrator', wp_get_current_user()->roles, true ) ) {
			return false;
		}

		if ( $track_on_all_stores ) {
			return true;
		}

		// For the remaining events, don't track when woopay is disabled.
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
	 * @param bool   $track_on_all_stores    Indicate whether the event should be tracked on all stores.
	 *
	 * @return bool|array|\WP_Error|\Jetpack_Tracks_Event
	 */
	public function tracks_record_event( $event_name, $properties = [], $is_admin_event = false, $track_on_all_stores = false ) {

		$user = wp_get_current_user();

		// We don't want to track user events during unit tests/CI runs.
		if ( $user instanceof \WP_User && 'wptests_capabilities' === $user->cap_key ) {
			return false;
		}

		$properties = apply_filters( 'wcpay_tracks_event_properties', $properties, $event_name );

		if ( isset( $properties['record_event_data'] ) ) {
			if ( isset( $properties['record_event_data']['is_admin_event'] ) ) {
				$is_admin_event = $properties['record_event_data']['is_admin_event'];
			}

			if ( isset( $properties['record_event_data']['track_on_all_stores'] ) ) {
				$track_on_all_stores = $properties['record_event_data']['track_on_all_stores'];
			}

			unset( $properties['record_event_data'] );
		}

		if ( ! $this->should_enable_tracking( $is_admin_event, $track_on_all_stores ) ) {
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
		$identity = $this->tracks_get_identity();
		$site_url = get_option( 'siteurl' );

		$properties['_lg']       = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) : '';
		$properties['blog_url']  = $site_url;
		$properties['blog_id']   = \Jetpack_Options::get_option( 'id' );
		$properties['user_lang'] = $user->get( 'WPLANG' );
		$properties['store_id']  = $this->get_wc_store_id();

		// Add event property for test mode vs. live mode events.
		$properties['test_mode']     = WC_Payments::mode()->is_test() ? 1 : 0;
		$properties['wcpay_version'] = WCPAY_VERSION_NUMBER;

		// Add client's user agent to the event properties.
		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$properties['_via_ua'] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		}

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
	 * Returns WC store_id value, if available.
	 * store_id introduced in WC 8.4.
	 *
	 * @return string|null
	 */
	public function get_wc_store_id() {
		if ( defined( '\WC_Install::STORE_ID_OPTION' ) ) {
			return get_option( \WC_Install::STORE_ID_OPTION, null );
		}
		return null;
	}

	/**
	 * Get the identity to send to tracks.
	 *
	 * @return array $identity
	 */
	public function tracks_get_identity() {
		$user_id = get_current_user_id();

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

		return [
			'_ut' => 'anon',
			'_ui' => $anon_id,
		];
	}

	/**
	 * Record a Tracks event that the classic checkout page has loaded.
	 */
	public function classic_checkout_start() {
		$is_woopay_enabled = WC_Payments_Features::is_woopay_enabled();
		$this->maybe_record_wcpay_shopper_event(
			'checkout_page_view',
			[
				'theme_type'     => 'short_code',
				'woopay_enabled' => $is_woopay_enabled,
			]
		);
	}

	/**
	 * Record a Tracks event that the blocks checkout page has loaded.
	 */
	public function blocks_checkout_start() {
		$is_woopay_enabled = WC_Payments_Features::is_woopay_enabled();
		$this->maybe_record_wcpay_shopper_event(
			'checkout_page_view',
			[
				'theme_type'     => 'blocks',
				'woopay_enabled' => $is_woopay_enabled,
			]
		);
	}

	/**
	 * Record a Tracks event that the classic cart page has loaded.
	 */
	public function classic_cart_page_view() {
		$this->maybe_record_wcpay_shopper_event(
			'cart_page_view',
			[
				'theme_type' => 'short_code',
			]
		);
	}

	/**
	 * Record a Tracks event that the blocks cart page has loaded.
	 */
	public function blocks_cart_page_view() {
		$this->maybe_record_wcpay_shopper_event(
			'cart_page_view',
			[
				'theme_type' => 'blocks',
			]
		);
	}

	/**
	 * Record a Tracks event that the classic cart product has loaded.
	 */
	public function classic_product_page_view() {
		$this->maybe_record_wcpay_shopper_event(
			'product_page_view',
			[
				'theme_type' => 'short_code',
			]
		);
	}

	/**
	 * Record a Tracks event that the pay-for-order page has loaded.
	 */
	public function pay_for_order_page_view() {
		$this->maybe_record_wcpay_shopper_event(
			'pay_for_order_page_view'
		);
	}

	/**
	 * Bump a counter. No user identifiable information is sent.
	 *
	 * @param string $group     The group to bump the stat in.
	 * @param string $stat_name The name of the stat to bump.
	 *
	 * @return bool
	 */
	public function bump_stats( $group, $stat_name ) {
		$is_admin_event      = false;
		$track_on_all_stores = true;

		if ( ! $this->should_enable_tracking( $is_admin_event, $track_on_all_stores ) ) {
			return false;
		}

		if ( WC_Payments::mode()->is_test() ) {
			return false;
		}

		$pixel_url = sprintf(
			self::$pixel_base_url . '?v=wpcom-no-pv&x_%s=%s',
			$group,
			$stat_name
		);

		$response = wp_remote_get( esc_url_raw( $pixel_url ) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Record that the order has been processed.
	 *
	 * @param int $order_id The ID of the order.
	 */
	public function checkout_order_processed( $order_id ) {

		$payment_gateway = wc_get_payment_gateway_by_order( $order_id );
		$properties      = [ 'payment_title' => 'other' ];

		// If the order was placed using WooCommerce Payments, record the payment title using Tracks.
		if ( isset( $payment_gateway->id ) && strpos( $payment_gateway->id, 'woocommerce_payments' ) === 0 ) {
			$order         = wc_get_order( $order_id );
			$payment_title = $order->get_payment_method_title();
			$properties    = [ 'payment_title' => $payment_title ];

			$is_woopay_order = ( isset( $_SERVER['HTTP_USER_AGENT'] ) && 'WooPay' === $_SERVER['HTTP_USER_AGENT'] );

			// Don't track WooPay orders. They will be tracked on WooPay side with more details.
			if ( ! $is_woopay_order ) {
				$this->maybe_record_wcpay_shopper_event( 'checkout_order_placed', $properties, false );
			}
			// If the order was placed using a different payment gateway, just increment a counter.
		} else {
			$this->bump_stats( 'wcpay_order_completed_gateway', 'other' );
		}
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

	/**
	 * Record a Tracks event that Thank you page was viewed for a WCPay order.
	 *
	 * @param int $order_id The ID of the order.
	 * @return void
	 */
	public function thank_you_page_view( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || 'woocommerce_payments' !== $order->get_payment_method() ) {
			return;
		}

		$this->maybe_record_wcpay_shopper_event( 'order_success_page_view' );
	}

	/**
	 * Record a Tracks event that the WooPay express button locations has been updated.
	 *
	 * @param array $all_locations All pages where WooPay express button can be enabled.
	 * @param array $platform_checkout_enabled_locations pages where WooPay express button is enabled.
	 *
	 * @return void
	 */
	public function woopay_locations_updated( $all_locations, $platform_checkout_enabled_locations ) {
		$props = [];
		foreach ( array_keys( $all_locations ) as $location ) {
			$key = $location . '_enabled';
			if ( in_array( $location, $platform_checkout_enabled_locations, true ) ) {
				$props[ $key ] = true;
			} else {
				$props[ $key ] = false;
			}
		}

		$this->maybe_record_admin_event( 'woopay_express_button_locations_updated', $props );
	}

	/**
	 * Add front-end tracks scripts to prevent cache break.
	 *
	 * @return void
	 */
	public function add_frontend_tracks_scripts() {
		$frontent_tracks = apply_filters( 'wcpay_frontend_tracks', [] );

		if ( count( $frontent_tracks ) === 0 ) {
			return;
		}

		WC_Payments::register_script_with_dependencies( 'wcpay-frontend-tracks', 'dist/frontend-tracks' );

		// Define wcpayConfig before the frontend tracks script if it hasn't been defined yet.
		$wcpay_config = rawurlencode( wp_json_encode( WC_Payments::get_wc_payments_checkout()->get_payment_fields_js_config() ) );
		wp_add_inline_script(
			'wcpay-frontend-tracks',
			"
			var wcpayConfig = wcpayConfig || JSON.parse( decodeURIComponent( '" . esc_js( $wcpay_config ) . "' ) );
			",
			'before'
		);

		wp_localize_script(
			'wcpay-frontend-tracks',
			'wcPayFrontendTracks',
			$frontent_tracks
		);

		wp_enqueue_script( 'wcpay-frontend-tracks' );
	}
}
