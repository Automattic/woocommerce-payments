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
 * REST controller for promotions functionality.
 */
class WC_REST_Payments_Promotions_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/payment-method-promotions';

	/**
	 * Transient key for caching promotions.
	 *
	 * @var string
	 */
	const PROMOTIONS_CACHE_KEY = 'wcpay_promotions';

	/**
	 * Cache duration in seconds (5 minutes).
	 *
	 * @var int
	 */
	const CACHE_DURATION = 300;

	/**
	 * Option key for promotion dismissals.
	 * Stores array of [promo_id => [variation_id => timestamp]].
	 *
	 * @var string
	 */
	const PROMOTION_DISMISSALS_OPTION = '_wcpay_promotion_dismissals';

	/**
	 * Option key for activated promotions.
	 *
	 * @var string
	 */
	const ACTIVATED_PROMOTIONS_OPTION = '_wcpay_activated_promotions';

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
	 * Retrieve promotions list with caching.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_promotions() {
		// Check cache first.
		$cached_promotions = get_transient( self::PROMOTIONS_CACHE_KEY );
		if ( false !== $cached_promotions ) {
			return rest_ensure_response( $cached_promotions );
		}

		// TODO: Replace with actual API call when server endpoints are available.
		// $wcpay_request = Request::get( WC_Payments_API_Client::PROMOTIONS_API );.
		// $wcpay_request->assign_hook( 'wcpay_get_promotions' );.
		// $promotions = $wcpay_request->send();.

		// Return mock data for testing.
		$promotions = $this->get_mock_promotions_data();

		// Cache the response.
		set_transient( self::PROMOTIONS_CACHE_KEY, $promotions, self::CACHE_DURATION );

		return rest_ensure_response( $promotions );
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

		// Filter variations based on config and dismissal history.
		return $this->filter_variations_by_dismissals( $promotions );
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
		update_option( self::PROMOTION_DISMISSALS_OPTION, $dismissals );
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
		update_option( self::ACTIVATED_PROMOTIONS_OPTION, $activated );
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
