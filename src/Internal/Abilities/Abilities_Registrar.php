<?php
/**
 * Class Abilities_Registrar
 *
 * @package WooCommerce\Payments
 */

// @phan-file-suppress PhanUndeclaredFunction, PhanUndeclaredClassMethod @phan-suppress-current-line UnusedSuppression -- Abilities API added in WP 6.9, but then we need a suppression for the WP 6.8 compat run. @todo Remove this line when we drop WP <6.9.

namespace WCPay\Internal\Abilities;

/**
 * Registers WooPayments abilities with the WordPress Abilities API.
 *
 * Concrete abilities are registered in follow-up phases; this scaffold only
 * wires the registration hooks and declares the `woopayments` category so
 * Phase 2 can ship independently.
 */
class Abilities_Registrar {

	/**
	 * The category slug used for every WooPayments ability.
	 *
	 * @var string
	 */
	const CATEGORY_SLUG = 'woopayments';

	/**
	 * Initialize the abilities registration.
	 *
	 * Mirrors the pattern used by Jetpack Forms: if the relevant Abilities API
	 * action has already fired we call the registrar directly, otherwise we
	 * hook it for when the API boots.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Register category.
		if ( did_action( 'wp_abilities_api_categories_init' ) ) {
			self::register_category();
		} else {
			add_action( 'wp_abilities_api_categories_init', [ __CLASS__, 'register_category' ] );
		}

		// Register abilities.
		if ( did_action( 'wp_abilities_api_init' ) ) {
			self::register_abilities();
		} else {
			add_action( 'wp_abilities_api_init', [ __CLASS__, 'register_abilities' ] );
		}
	}

	/**
	 * Register the WooPayments ability category.
	 *
	 * @return void
	 */
	public static function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY_SLUG,
			[
				// "WooPayments" is a product name and should not be translated.
				'label'       => 'WooPayments',
				'description' => __( 'Abilities for managing WooPayments transactions, disputes, payouts, and account status.', 'woocommerce-payments' ),
			]
		);
	}

	/**
	 * Register all WooPayments abilities.
	 *
	 * Concrete abilities are registered in subsequent phases.
	 *
	 * @return void
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		self::register_get_account_status_ability();
	}

	/**
	 * Permission callback mirroring WC_Payments_REST_Controller::check_permission().
	 *
	 * Used as the `permission_callback` for every WooPayments ability so the
	 * authorization surface matches the existing admin REST API.
	 *
	 * @return bool
	 */
	public static function can_manage_payments(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Execute callback for woopayments/get-account-status.
	 *
	 * Delegates to WC_Payments_Account::get_cached_account_data(). Usually
	 * served from cache; on cache miss this may issue a remote request to
	 * the WooPayments server, so agents should not assume this ability is
	 * free to call repeatedly.
	 *
	 * @param mixed $input Optional; ability input. Unused for this ability (empty input_schema) but accepted to match the Abilities API execute_callback signature.
	 * @return array|\WP_Error Array of account data, or WP_Error when WooPayments
	 *                         has not finished initializing or the cached data
	 *                         is unavailable due to a remote error.
	 */
	public static function execute_get_account_status( $input = null ) {
		if ( ! class_exists( '\WC_Payments' ) ) {
			return new \WP_Error(
				'woopayments_not_initialized',
				__( 'WooPayments is not initialized.', 'woocommerce-payments' )
			);
		}

		$account = \WC_Payments::get_account_service();
		if ( ! $account ) {
			return new \WP_Error(
				'woopayments_not_initialized',
				__( 'WooPayments is not initialized.', 'woocommerce-payments' )
			);
		}

		$data = $account->get_cached_account_data();

		if ( false === $data ) {
			return new \WP_Error(
				'woopayments_account_data_unavailable',
				__( 'Unable to retrieve WooPayments account data. The cache may be stale or the remote service returned an error.', 'woocommerce-payments' )
			);
		}

		return is_array( $data ) ? $data : [];
	}

	/**
	 * Register the woopayments/get-account-status ability.
	 *
	 * Reference implementation for the Abilities Everywhere initiative: a
	 * read-only ability an agent should call before any write so it understands
	 * what the merchant's WooPayments account can currently do (KYC state,
	 * enabled capabilities, deposit schedule, required actions).
	 *
	 * @return void
	 */
	private static function register_get_account_status_ability(): void {
		wp_register_ability(
			'woopayments/get-account-status',
			[
				'label'               => __( 'Get WooPayments account status', 'woocommerce-payments' ),
				'description'         => __( "Returns the merchant's WooPayments account status, including KYC state, enabled capabilities, deposit schedule, and any required actions. Agents should call this before any write ability to understand what the merchant can currently do.", 'woocommerce-payments' ),
				'category'            => self::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'default'              => [],
					'properties'           => [],
					'additionalProperties' => false,
				],
				'execute_callback'    => [ __CLASS__, 'execute_get_account_status' ],
				'permission_callback' => [ __CLASS__, 'can_manage_payments' ],
				// output_schema deliberately omitted — the account payload shape drifts with Stripe's API and we don't want to couple to a specific structure here.
				'meta'                => [
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
					'show_in_rest' => true,
				],
			]
		);
	}
}
