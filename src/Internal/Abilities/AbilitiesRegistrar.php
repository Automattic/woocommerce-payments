<?php
/**
 * Class AbilitiesRegistrar
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Registers WooPayments abilities with the WordPress Abilities API.
 *
 * Concrete abilities are registered in follow-up phases; this scaffold wires
 * the registration hooks and declares the `woocommerce-payments` category.
 */
class AbilitiesRegistrar {

	/**
	 * The category slug used for every WooPayments ability.
	 *
	 * @var string
	 */
	const CATEGORY_SLUG = 'woocommerce-payments';

	/**
	 * Tracks whether the category has been registered in this process to
	 * keep `register_category()` idempotent across action variants.
	 *
	 * @var bool
	 */
	private static $category_registered = false;

	/**
	 * Tracks whether the ability set has been registered in this process to
	 * keep `register_abilities()` idempotent across action variants.
	 *
	 * @var bool
	 */
	private static $abilities_registered = false;

	/**
	 * Initialize the abilities registration.
	 *
	 * Gated behind the `woocommerce_payments_abilities_enabled` filter (default
	 * false during rollout). Flip via `add_filter()` on a per-site basis to
	 * enable; default to `true` once the feature graduates.
	 *
	 * @return void
	 */
	public static function init() {
		/**
		 * Filter whether WooPayments' Abilities API registrations are active.
		 *
		 * Default false during initial rollout so the scaffolding can ship
		 * without committing to the final ability shape. Flip per-site to
		 * opt in.
		 *
		 * @since 10.8.0
		 *
		 * @param bool $enabled Whether to register WooPayments abilities. Default false.
		 */
		if ( ! apply_filters( 'woocommerce_payments_abilities_enabled', false ) ) {
			return;
		}

		// The abilities-api package fires the non-prefixed action names
		// (`abilities_api_init`, `abilities_api_categories_init`). WooCommerce
		// Core additionally hooks the `wp_` variants for forward compatibility
		// with any future WP-Core-merged naming; we mirror that. The
		// idempotency guards in `register_category()` / per-ability helpers
		// make repeated invocations safe if both action names fire.
		if ( did_action( 'abilities_api_categories_init' ) || did_action( 'wp_abilities_api_categories_init' ) ) {
			self::register_category();
		} else {
			add_action( 'abilities_api_categories_init', [ __CLASS__, 'register_category' ] );
			add_action( 'wp_abilities_api_categories_init', [ __CLASS__, 'register_category' ] );
		}

		if ( did_action( 'abilities_api_init' ) || did_action( 'wp_abilities_api_init' ) ) {
			self::register_abilities();
		} else {
			add_action( 'abilities_api_init', [ __CLASS__, 'register_abilities' ] );
			add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
		}
	}

	/**
	 * Register the WooPayments ability category.
	 *
	 * Idempotent — re-registering the same category, or looking it up before
	 * it is registered, both fire `_doing_it_wrong` from the Abilities API.
	 * Tracking with a static flag keeps both paths silent across the variant
	 * action names (`abilities_api_categories_init` and the WP-Core merge
	 * target `wp_abilities_api_categories_init`).
	 *
	 * @return void
	 */
	public static function register_category() {
		if ( self::$category_registered || ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		self::$category_registered = true;

		wp_register_ability_category(
			self::CATEGORY_SLUG,
			[
				// "WooPayments" is a product name and is not translated.
				'label'       => 'WooPayments',
				'description' => __( 'Abilities for inspecting a merchant\'s WooPayments account, transactions, disputes, payouts, and related state.', 'woocommerce-payments' ),
			]
		);
	}

	/**
	 * Register all WooPayments abilities.
	 *
	 * Idempotent — guarded by a static flag so the variant action names
	 * (`abilities_api_init` and the WP-Core merge target `wp_abilities_api_init`)
	 * don't double-register if both fire in the same process.
	 *
	 * @return void
	 */
	public static function register_abilities() {
		if ( self::$abilities_registered || ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		self::$abilities_registered = true;

		self::register_get_account_ability();
	}

	/**
	 * Execute callback for `woocommerce-payments/get-account`.
	 *
	 * Delegates to `WC_REST_Payments_Accounts_Controller::get_account_data()`
	 * to preserve parity with the existing REST endpoint (controller adds
	 * `card_present_eligible`, `test_mode`, and `test_mode_onboarding` flags
	 * on top of the cached service payload).
	 *
	 * Reads from the local account cache — no remote API request is issued.
	 *
	 * @param mixed $input Unused (zero-arg ability); accepted to match the
	 *                     Abilities API execute_callback signature.
	 * @return array|\WP_Error Account data array, or WP_Error when WooPayments
	 *                         has not finished initializing.
	 */
	public static function execute_get_account( $input = null ) {
		unset( $input );

		if ( ! class_exists( '\WC_REST_Payments_Accounts_Controller' ) ) {
			return new \WP_Error(
				'woocommerce_payments_not_initialized',
				__( 'WooPayments is not initialized.', 'woocommerce-payments' )
			);
		}

		$api_client = null;
		if ( class_exists( '\WC_Payments' ) && method_exists( '\WC_Payments', 'get_payments_api_client' ) ) {
			$api_client = \WC_Payments::get_payments_api_client();
		}
		if ( null === $api_client ) {
			return new \WP_Error(
				'woocommerce_payments_not_initialized',
				__( 'WooPayments is not initialized.', 'woocommerce-payments' )
			);
		}

		$controller = new \WC_REST_Payments_Accounts_Controller( $api_client );
		$response   = $controller->get_account_data();

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( $response instanceof \WP_REST_Response ) {
			$data = $response->get_data();
			return is_array( $data ) ? $data : [];
		}
		return is_array( $response ) ? $response : [];
	}

	/**
	 * Permission callback mirroring WC_Payments_REST_Controller::check_permission().
	 *
	 * Used as the `permission_callback` for every WooPayments ability so the
	 * authorization surface matches the existing admin REST API.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage_woocommerce() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Register the `woocommerce-payments/get-account` ability.
	 *
	 * Zero-arg read returning the merchant's WooPayments account state —
	 * onboarding status, country, currencies, KYC deadlines, test/live mode.
	 * Backed by `WC_REST_Payments_Accounts_Controller::get_account_data()`.
	 *
	 * This is the reference pattern: simplest safe read, no input, direct
	 * call to the controller (no `WP_REST_Request` synthesis needed because
	 * the backing method is zero-arg).
	 *
	 * @return void
	 */
	private static function register_get_account_ability() {
		wp_register_ability(
			'woocommerce-payments/get-account',
			[
				'label'               => __( 'Get WooPayments account state', 'woocommerce-payments' ),
				'description'         => __( 'Return the merchant\'s WooPayments account state: onboarding status, country, store and customer currencies, KYC requirements, deadlines, and test/live mode flags.', 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'default'              => (object) [],
					'properties'           => [],
					'additionalProperties' => false,
				],
				'execute_callback'    => [ __CLASS__, 'execute_get_account' ],
				'permission_callback' => [ __CLASS__, 'current_user_can_manage_woocommerce' ],
				// output_schema deliberately omitted — the payload shape comes
				// straight from the backing controller and we don't want to
				// couple this registrar to a specific structure here.
				'meta'                => [
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
					'show_in_rest' => true,
					'mcp'          => [
						'public' => true,
					],
				],
			]
		);
	}
}
