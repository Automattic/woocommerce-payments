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

		if ( did_action( 'wp_abilities_api_categories_init' ) ) {
			self::register_category();
		} else {
			add_action( 'wp_abilities_api_categories_init', [ __CLASS__, 'register_category' ] );
		}

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
	public static function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

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
	 * Concrete abilities are registered in subsequent phases.
	 *
	 * @return void
	 */
	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		// Abilities registered in Phases II–III.
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
}
