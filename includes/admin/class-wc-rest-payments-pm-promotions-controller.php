<?php
/**
 * Class WC_REST_Payments_Promotions_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Core\Server\Request;
use WCPay\Core\Server\Request\Activate_Promotion;
use WCPay\Core\Server\Request\Dismiss_Promotion;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for payment methods (PM) promotions functionality.
 */
class WC_REST_Payments_PM_Promotions_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/pm-promotions';

	/**
	 * Transient key for caching promotions.
	 *
	 * @var string
	 */
	const PROMOTIONS_CACHE_KEY = 'wcpay_pm_promotions';

	/**
	 * Option key for promotion dismissals.
	 * Stores array of [promo_id => [variation_id => timestamp]].
	 *
	 * @var string
	 */
	const PROMOTION_DISMISSALS_OPTION = '_wcpay_pm_promotion_dismissals';

	/**
	 * Option key for activated promotions.
	 *
	 * @var string
	 */
	const ACTIVATED_PROMOTIONS_OPTION = '_wcpay_activated_pm_promotions';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_promotions' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<identifier>[a-zA-Z0-9_-]+)/activate',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'activate_promotion' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'identifier'   => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'accept_terms' => [
						'required' => false,
						'type'     => 'boolean',
						'default'  => true,
					],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<identifier>[a-zA-Z0-9_-]+)/dismiss',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'dismiss_promotion' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'identifier'   => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'variation_id' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Retrieve the active promotions list.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_promotions() {
		// First, try to use the cached data.
		$cache = get_transient( self::PROMOTIONS_CACHE_KEY );

		// If the cached data is not expired, and it's a WP_Error,
		// it means there was an API error previously, and we should not retry just yet.
		if ( is_wp_error( $cache ) ) {
			return rest_ensure_response( [] );
		} elseif ( false !== $cache ) {
			return rest_ensure_response( $cache['promotions'] ?? [] );
		}

		// TODO: Replace with actual API call when server endpoints are available.
		// $wcpay_request = Request\Get_PM_Promotions::create();.
		// $response      = $wcpay_request->handle_rest_request();.
		// Return mock data for testing.
		$response = $this->get_mock_promotions_data();

		// Return early if there is an error, waiting 6 hours before the next attempt.
		if ( is_wp_error( $response ) ) {
			// Store a trimmed down, lightweight error.
			/**
			 * Type hint for static analysis.
			 *
			 * @var WP_Error $response
			 */
			$error = new \WP_Error(
				$response->get_error_code(),
				$response->get_error_message(),
				wp_remote_retrieve_response_code( $response )
			);
			// Store the error in the transient so we know this is due to an API error.
			set_transient( self::PROMOTIONS_CACHE_KEY, $error, HOUR_IN_SECONDS * 6 );

			return rest_ensure_response( [] );
		}

		$cache_for  = wp_remote_retrieve_header( $response, 'cache-for' );
		$promotions = [];
		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			// Decode the results, falling back to an empty array.
			$promotions = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];
		}

		// Skip transient cache if `cache-for` header equals zero.
		if ( '0' === $cache_for ) {
			// Remove any transients so there are no leftovers.
			delete_transient( self::PROMOTIONS_CACHE_KEY );

			return rest_ensure_response( $promotions );
		}

		// Store promotions in transient cache for the given number of seconds or 1 day in seconds.
		// Also attach a timestamp to the transient data so we know when we last fetched.
		set_transient(
			self::PROMOTIONS_CACHE_KEY,
			[
				'promotions' => $promotions,
				'timestamp'  => time(),
			],
			! empty( $cache_for ) ? (int) $cache_for : DAY_IN_SECONDS
		);

		// Finally, filter variations based on config and dismissal history.
		$filtered_promotions = $this->filter_variations_by_dismissals( $promotions );

		return rest_ensure_response( $filtered_promotions );
	}

	/**
	 * Activate a promotion.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function activate_promotion( $request ) {
		$identifier   = $request->get_param( 'identifier' );
		$accept_terms = $request->get_param( 'accept_terms' );

		// TODO: Replace with actual API call when server endpoints are available.
		// $wcpay_request = Activate_Promotion::create( $identifier );.
		// $wcpay_request->set_accept_terms( $accept_terms );.
		// $wcpay_request->assign_hook( 'wcpay_activate_promotion_request' );.
		// $response = $wcpay_request->handle_rest_request();.

		// Return mock success response.
		$response = [
			'success'    => true,
			'identifier' => $identifier,
			'status'     => 'active',
		];

		// Clear cache and update local state.
		$this->clear_promotions_cache();
		$this->mark_promotion_activated( $identifier );

		return rest_ensure_response( $response );
	}

	/**
	 * Dismiss a promotion variation.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function dismiss_promotion( $request ) {
		$identifier   = $request->get_param( 'identifier' );
		$variation_id = $request->get_param( 'variation_id' );

		// TODO: Replace with actual API call when server endpoints are available.
		// $wcpay_request = Dismiss_Promotion::create( $identifier, $variation_id );.
		// $wcpay_request->assign_hook( 'wcpay_dismiss_promotion_request' );.
		// $response = $wcpay_request->handle_rest_request();.

		// Return mock success response.
		$response = [
			'success'      => true,
			'identifier'   => $identifier,
			'variation_id' => $variation_id,
			'status'       => 'dismissed',
		];

		// Clear cache and update local state.
		$this->clear_promotions_cache();
		$this->mark_variation_dismissed( $identifier, $variation_id );

		return rest_ensure_response( $response );
	}

	/**
	 * Clear the promotions cache.
	 *
	 * @return void
	 */
	private function clear_promotions_cache() {
		delete_transient( self::PROMOTIONS_CACHE_KEY );
	}

	/**
	 * Filter variations based on config and dismissal history.
	 *
	 * @param array $promotions Array of promotions with variations.
	 *
	 * @return array Filtered promotions array.
	 */
	private function filter_variations_by_dismissals( array $promotions ) {
		foreach ( $promotions as &$promotion ) {
			if ( empty( $promotion['variations'] ) ) {
				continue;
			}

			$promo_id             = $promotion['promo_id'];
			$variation_dismissals = self::get_promotion_variation_dismissals( $promo_id );

			// Group variations by type to apply type-specific config.
			$variations_by_type = [];
			foreach ( $promotion['variations'] as $variation ) {
				$type = $variation['type'] ?? 'default';
				if ( ! isset( $variations_by_type[ $type ] ) ) {
					$variations_by_type[ $type ] = [];
				}
				$variations_by_type[ $type ][] = $variation;
			}

			$filtered_variations = [];

			foreach ( $variations_by_type as $type => $type_variations ) {
				// Get config for this variation type.
				// Defaults: 1 dismissal allowed, no delay (must configure to show multiple variations).
				$type_config    = $promotion['config'][ $type ] ?? [];
				$max_dismissals = $type_config['max_dismissals'] ?? 1;
				$reshow_delay   = $type_config['reshow_delay_days'] ?? 0;
				$delay_seconds  = $reshow_delay * DAY_IN_SECONDS;

				// Count dismissals for variations of this type.
				$type_dismissals       = 0;
				$most_recent_dismissal = 0;
				foreach ( $type_variations as $variation ) {
					$dismissed_at = $variation_dismissals[ $variation['id'] ] ?? null;
					if ( null !== $dismissed_at ) {
						++$type_dismissals;
						if ( $dismissed_at > $most_recent_dismissal ) {
							$most_recent_dismissal = $dismissed_at;
						}
					}
				}

				// Check if max dismissals reached for this type.
				if ( $type_dismissals >= $max_dismissals ) {
					continue;
				}

				// Check if still in delay period.
				if ( $most_recent_dismissal > 0 && $delay_seconds > 0 ) {
					$time_since_dismissal = time() - $most_recent_dismissal;
					if ( $time_since_dismissal < $delay_seconds ) {
						continue;
					}
				}

				// Find first non-dismissed variation of this type.
				foreach ( $type_variations as $variation ) {
					$dismissed_at = $variation_dismissals[ $variation['id'] ] ?? null;
					if ( null === $dismissed_at ) {
						$filtered_variations[] = $variation;
						break;
					}
				}
			}

			$promotion['variations'] = $filtered_variations;
		}
		unset( $promotion );

		return $promotions;
	}

	/**
	 * Get mock promotions data for testing.
	 * TODO: Remove this method when server endpoints are available.
	 *
	 * @return array Mock promotions data (array of promotions).
	 */
	private function get_mock_promotions_data() {
		// Mock available promotions with variations.
		$promotions = [
			[
				'promo_id'      => 'klarna-2026-promo',
				'discount_rate' => '100%',
				'duration_days' => 90,
				'config'        => [
					'spotlight' => [
						'reshow_delay_days' => 7,   // Days to wait before showing next variation.
						'max_dismissals'    => 2,   // Total dismissals before permanent hide.
					],
				],
				'variations'    => [
					[
						'id'          => 'klarna-2026-promo__spotlight_primary',
						'type'        => 'spotlight',
						'badge'       => 'Limited time offer',
						'badge_type'  => 'success',
						'heading'     => 'Zero Processing Fees for Card Payments',
						'description' => 'Save on every card transaction with 0% processing fees for 90 days',
						'cta_label'   => 'Activate Now',
						'cta_url'     => '#',
						'tc_url'      => 'https://woocommerce.com/terms',
						'footnote'    => '*Terms and conditions apply. Offer valid for new customers only.',
					],
					[
						'id'          => 'klarna-2026-promo__spotlight_secondary',
						'type'        => 'spotlight',
						'badge'       => 'Last chance',
						'badge_type'  => 'warning',
						'heading'     => 'Final Reminder: Zero Processing Fees',
						'description' => 'Don\'t miss out! Get 0% processing fees for 90 days on all card payments',
						'cta_label'   => 'Activate Now',
						'cta_url'     => '#',
						'tc_url'      => 'https://woocommerce.com/terms',
						'footnote'    => '*Terms and conditions apply. Limited time offer.',
					],
				],
			],
			[
				'promo_id'      => 'promo-affirm-cashback-2024',
				'discount_rate' => '2%',
				'duration_days' => 60,
				'variations'    => [
					[
						'id'          => 'promo-affirm-cashback-2024__banner_primary',
						'type'        => 'banner',
						'badge'       => 'New',
						'badge_type'  => 'info',
						'heading'     => '2% Cashback on Affirm Transactions',
						'description' => 'Earn cashback on all Affirm payments for 60 days',
						'cta_label'   => 'Learn More',
						'cta_url'     => '#',
						'tc_url'      => 'https://woocommerce.com/terms',
					],
				],
			],
		];

		return [
			'response' => [
				'code' => 200,
			],
			'body'     => wp_json_encode( $promotions ),
		];
	}

	/**
	 * Mark a promotion variation as dismissed in local state.
	 *
	 * @param string $promo_id The promotion identifier.
	 * @param string $variation_id The variation identifier.
	 *
	 * @return void
	 */
	private function mark_variation_dismissed( string $promo_id, string $variation_id ) {
		$dismissals = $this->get_promotion_dismissals();

		if ( ! isset( $dismissals[ $promo_id ] ) ) {
			$dismissals[ $promo_id ] = [];
		}

		$dismissals[ $promo_id ][ $variation_id ] = time();
		update_option( self::PROMOTION_DISMISSALS_OPTION, $dismissals, false );
	}

	/**
	 * Mark a promotion as activated in local state.
	 *
	 * @param string $identifier The promotion identifier.
	 *
	 * @return void
	 */
	private function mark_promotion_activated( string $identifier ) {
		$activated                = $this->get_activated_promotions();
		$activated[ $identifier ] = time();
		update_option( self::ACTIVATED_PROMOTIONS_OPTION, $activated, false );
	}

	/**
	 * Get all promotion dismissals.
	 *
	 * @return array Associative array of [promo_id => [variation_id => timestamp]].
	 */
	public static function get_promotion_dismissals() {
		return get_option( self::PROMOTION_DISMISSALS_OPTION, [] );
	}

	/**
	 * Get list of activated promotions with timestamps.
	 *
	 * @return array Associative array of promotion identifiers to activation timestamps.
	 */
	public static function get_activated_promotions() {
		return get_option( self::ACTIVATED_PROMOTIONS_OPTION, [] );
	}

	/**
	 * Get dismissal timestamp for a specific variation.
	 *
	 * @param string $promo_id The promotion identifier.
	 * @param string $variation_id The variation identifier.
	 *
	 * @return int|null Dismissal timestamp, or null if not dismissed.
	 */
	public static function get_variation_dismissal_time( string $promo_id, string $variation_id ) {
		$dismissals = self::get_promotion_dismissals();
		return $dismissals[ $promo_id ][ $variation_id ] ?? null;
	}

	/**
	 * Get all dismissal timestamps for a promotion.
	 *
	 * @param string $promo_id The promotion identifier.
	 *
	 * @return array Array of [variation_id => timestamp].
	 */
	public static function get_promotion_variation_dismissals( string $promo_id ) {
		$dismissals = self::get_promotion_dismissals();
		return $dismissals[ $promo_id ] ?? [];
	}

	/**
	 * Check if a promotion has been activated.
	 *
	 * @param string $identifier The promotion identifier.
	 *
	 * @return bool True if activated, false otherwise.
	 */
	public static function is_promotion_activated( string $identifier ) {
		$activated = self::get_activated_promotions();
		return isset( $activated[ $identifier ] );
	}

	/**
	 * Get the activation timestamp for a promotion.
	 *
	 * @param string $identifier The promotion identifier.
	 *
	 * @return int|null The activation timestamp, or null if not activated.
	 */
	public static function get_promotion_activation_time( string $identifier ) {
		$activated = self::get_activated_promotions();
		return $activated[ $identifier ] ?? null;
	}
}
