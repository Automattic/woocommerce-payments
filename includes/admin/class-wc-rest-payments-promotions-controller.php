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
	protected $rest_base = 'payments/promotions';

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
	 * Option key for dismissed promotions.
	 *
	 * @var string
	 */
	const DISMISSED_PROMOTIONS_OPTION = '_wcpay_dismissed_promotions';

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
					'identifier' => [
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
	 * Dismiss a promotion.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function dismiss_promotion( $request ) {
		$identifier = $request->get_param( 'identifier' );

		// TODO: Replace with actual API call when server endpoints are available.
		// $wcpay_request = Dismiss_Promotion::create( $identifier );.
		// $wcpay_request->assign_hook( 'wcpay_dismiss_promotion_request' );.
		// $response = $wcpay_request->handle_rest_request();.

		// Return mock success response.
		$response = [
			'success'    => true,
			'identifier' => $identifier,
			'status'     => 'dismissed',
		];

		// Clear cache and update local state.
		$this->clear_promotions_cache();
		$this->mark_promotion_dismissed( $identifier );

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
	 * Get mock promotions data for testing.
	 * TODO: Remove this method when server endpoints are available.
	 *
	 * @return array Mock promotions data.
	 */
	private function get_mock_promotions_data() {
		$activated = self::get_activated_promotions();

		// Mock available promotions with variations.
		$available_promotions = [
			[
				'promo_id'      => 'promo-card-waive-2024',
				'discount_rate' => '100%',
				'duration_days' => 90,
				'variations'    => [
					[
						'id'          => 'promo-card-waive-2024__variation_1',
						'type'        => 'spotlight',
						'badge'       => 'Limited time offer',
						'badge_type'  => 'success',
						'heading'     => 'Zero Processing Fees for Card Payments',
						'description' => 'Save on every card transaction with 0% processing fees for 90 days',
						'cta_label'   => 'Activate Now',
						'cta_url'     => '#',
						'tc_url'      => 'https://woocommerce.com/terms',
					],
				],
			],
			[
				'promo_id'      => 'promo-affirm-cashback-2024',
				'discount_rate' => '2%',
				'duration_days' => 60,
				'variations'    => [
					[
						'id'          => 'promo-affirm-cashback-2024__variation_1',
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

		// Get IDs of activated promotions.
		$active_promotions = array_keys( $activated );

		return [
			'available_promotions' => $available_promotions,
			'active_promotions'    => $active_promotions,
		];
	}

	/**
	 * Mark a promotion as dismissed in local state.
	 *
	 * @param string $identifier The promotion identifier.
	 *
	 * @return void
	 */
	private function mark_promotion_dismissed( string $identifier ) {
		$dismissed = $this->get_dismissed_promotions();
		if ( ! in_array( $identifier, $dismissed, true ) ) {
			$dismissed[] = $identifier;
			update_option( self::DISMISSED_PROMOTIONS_OPTION, $dismissed );
		}
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
	 * Get list of dismissed promotions.
	 *
	 * @return array Array of dismissed promotion identifiers.
	 */
	public static function get_dismissed_promotions() {
		return get_option( self::DISMISSED_PROMOTIONS_OPTION, [] );
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
	 * Check if a promotion has been dismissed.
	 *
	 * @param string $identifier The promotion identifier.
	 *
	 * @return bool True if dismissed, false otherwise.
	 */
	public static function is_promotion_dismissed( string $identifier ) {
		$dismissed = self::get_dismissed_promotions();
		return in_array( $identifier, $dismissed, true );
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
