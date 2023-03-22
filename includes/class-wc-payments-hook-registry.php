<?php
/**
 * Class WC_Payments_Hook_Registry
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class that registers the hooks for the plugin.
 */
class WC_Payments_Hook_Registry {
	/**
	 * Registers this plugin's hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		$this->add_action( 'admin_init', 'get_account_service', 'maybe_handle_onboarding' );
		$this->add_action( 'admin_init', 'get_account_service', 'maybe_redirect_to_onboarding', 11 ); // Run this after the WC setup wizard and onboarding redirection logic.
		$this->add_action( 'admin_init', 'get_account_service', 'maybe_redirect_to_wcpay_connect', 12 ); // Run this after the redirect to onboarding logic.
		$this->add_action( 'woocommerce_payments_account_refreshed', 'get_account_service', 'handle_instant_deposits_inbox_note' );
		$this->add_action( 'woocommerce_payments_account_refreshed', 'get_account_service', 'handle_loan_approved_inbox_note' );
		$this->add_action( 'wcpay_instant_deposit_reminder', 'get_account_service', 'handle_instant_deposits_inbox_reminder' );
		$this->add_filter( 'allowed_redirect_hosts', 'get_account_service', 'allowed_redirect_hosts' );
		$this->add_action( 'jetpack_site_registered', 'get_account_service', 'clear_cache' );
		$this->add_action( 'updated_option', 'get_account_service', 'possibly_update_wcpay_account_locale', 10, 3 );
		// Add capital offer redirection.
		$this->add_action( 'admin_init', 'get_account_service', 'maybe_redirect_to_capital_offer' );
		// Add server links handler.
		$this->add_action( 'admin_init', 'get_account_service', 'maybe_redirect_to_server_link' );

		$this->add_action( 'init', 'get_apple_pay_registration', 'add_domain_association_rewrite_rule', 5 );
		$this->add_action( 'woocommerce_woocommerce_payments_updated', 'get_apple_pay_registration', 'verify_domain_on_update' );
		$this->add_action( 'init', 'get_apple_pay_registration', 'init' );
	}

	/**
	 * Adds an action to the WordPress hooks.
	 *
	 * @param string $tag - The name of the WordPress action that is being registered.
	 * @param string $class_getter - The name of the method that returns the class instance.
	 * @param string $method - The name of the method in the class that should be called.
	 * @param int    $priority - Optional. The priority at which the function should be fired. Default 10.
	 * @param int    $accepted_args - Optional. The number of arguments that should be passed to the $callback. Default 1.
	 *
	 * @return void
	 */
	private function add_action( string $tag, string $class_getter, string $method, int $priority = 10, int $accepted_args = 1 ) {
		add_action(
			$tag,
			function( ...$args ) use ( $class_getter, $method ) {
				$class = WC_Payments::{$class_getter}();
				$class->{$method}( ...$args );
			},
			$priority,
			$accepted_args
		);
	}

	/**
	 * Adds a filter to the WordPress hooks.
	 *
	 * @param string $tag - The name of the WordPress filter that is being registered.
	 * @param string $class_getter - The name of the method that returns the class instance.
	 * @param string $method - The name of the method in the class that should be called.
	 * @param int    $priority - Optional. The priority at which the function should be fired. Default 10.
	 * @param int    $accepted_args - Optional. The number of arguments that should be passed to the $callback. Default 1.
	 *
	 * @return void
	 */
	private function add_filter( string $tag, string $class_getter, string $method, int $priority = 10, int $accepted_args = 1 ) {
		add_filter(
			$tag,
			function( ...$args ) use ( $class_getter, $method ) {
				$class = WC_Payments::{$class_getter}();
				return $class->{$method}( ...$args );
			},
			$priority,
			$accepted_args
		);
	}
}
