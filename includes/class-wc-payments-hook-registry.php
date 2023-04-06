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
		// Account Service.
		$this->add_action( 'admin_init', 'get_account_service', 'maybe_handle_onboarding' );
		$this->add_action( 'admin_init', 'get_account_service', 'maybe_redirect_to_onboarding', 11 ); // Run this after the WC setup wizard and onboarding redirection logic.
		$this->add_action( 'admin_init', 'get_account_service', 'maybe_redirect_to_wcpay_connect', 12 ); // Run this after the redirect to onboarding logic.
		$this->add_action( 'woocommerce_payments_account_refreshed', 'get_account_service', 'handle_instant_deposits_inbox_note' );
		$this->add_action( 'woocommerce_payments_account_refreshed', 'get_account_service', 'handle_loan_approved_inbox_note' );
		$this->add_action( 'wcpay_instant_deposit_reminder', 'get_account_service', 'handle_instant_deposits_inbox_reminder' );
		$this->add_filter( 'allowed_redirect_hosts', 'get_account_service', 'allowed_redirect_hosts' );
		$this->add_action( 'jetpack_site_registered', 'get_account_service', 'clear_cache' );
		$this->add_action( 'updated_option', 'get_account_service', 'possibly_update_wcpay_account_locale', 10, 3 );
		$this->add_action( 'admin_init', 'get_account_service', 'maybe_redirect_to_capital_offer' ); // Add capital offer redirection.
		$this->add_action( 'admin_init', 'get_account_service', 'maybe_redirect_to_server_link' ); // Add server links handler.

		// Apple Pay.
		$this->add_action( 'init', 'get_apple_pay_registration', 'add_domain_association_rewrite_rule', 5 );
		$this->add_action( 'woocommerce_woocommerce_payments_updated', 'get_apple_pay_registration', 'verify_domain_on_update' );
		$this->add_action( 'init', 'get_apple_pay_registration', 'init' );

		$this->add_gateway_hooks();

		$this->add_subscriptions_hooks();
	}

	private function add_gateway_hooks() {
		// @TODO This will work with classic and UPE, but not split UPE.
		$this->add_action( 'woocommerce_update_options_payment_gateways_' . WC_Payment_Gateway_WCPay::GATEWAY_ID, 'get_gateway', 'process_admin_options' );
		$this->add_action( 'admin_notices', 'get_gateway', 'display_errors', 9999 );
		$this->add_action( 'woocommerce_order_actions', 'get_gateway', 'add_order_actions' );
		$this->add_action( 'woocommerce_order_action_capture_charge', 'get_gateway', 'capture_charge' );
		$this->add_action( 'woocommerce_order_action_cancel_authorization', 'get_gateway', 'cancel_authorization' );
		$this->add_action( 'wp_ajax_update_order_status', 'get_gateway', 'update_order_status' );
		$this->add_action( 'wp_ajax_nopriv_update_order_status', 'get_gateway', 'update_order_status' );
		$this->add_action( 'wp_enqueue_scripts', 'get_gateway', 'register_scripts' );
		$this->add_action( 'wp_enqueue_scripts', 'get_gateway', 'register_scripts_for_zero_order_total', 11 );
		$this->add_action( 'wp_ajax_create_setup_intent', 'get_gateway', 'create_setup_intent_ajax' );
		$this->add_action( 'wp_ajax_nopriv_create_setup_intent', 'get_gateway', 'create_setup_intent_ajax' );
		$this->add_action( 'woocommerce_update_order', 'get_gateway', 'schedule_order_tracking', 10, 2 );
		$this->add_action( 'set_logged_in_cookie', 'get_gateway', 'set_cookie_on_current_request' ); // Update the current request logged_in cookie after a guest user is created to avoid nonce inconsistencies.
		$this->add_action( WC_Payment_Gateway_WCPay::UPDATE_SAVED_PAYMENT_METHOD, 'get_gateway', 'update_saved_payment_method', 10, 3 );
		$this->add_filter( 'woocommerce_billing_fields', 'get_gateway', 'checkout_update_email_field_priority', 50 ); // Update the email field position.
		$this->add_action( 'template_redirect', 'get_gateway', 'clear_session_processing_order_after_landing_order_received_page', 21 ); // Priority 21 to run right after wc_clear_cart_after_payment.
	}

	private function add_subscriptions_hooks() {
		// TODO: the conditionals here could be moved to the hooks themselves.
		if ( ! $this->is_subscriptions_enabled() ) {
			return;
		}

		$this->add_filter( 'woocommerce_email_classes', 'get_gateway', 'add_emails', 20 );
		$this->add_filter( 'woocommerce_available_payment_gateways', 'get_gateway', 'prepare_order_pay_page' );

		$this->add_action( 'woocommerce_scheduled_subscription_payment_' . WC_Payment_Gateway_WCPay::GATEWAY_ID, 'get_gateway', 'scheduled_subscription_payment', 10, 2 );
		$this->add_action( 'woocommerce_subscription_failing_payment_method_updated_' . WC_Payment_Gateway_WCPay::GATEWAY_ID, 'get_gateway', 'update_failing_payment_method', 10, 2 );
		$this->add_filter( 'wc_payments_display_save_payment_method_checkbox', 'get_gateway', 'display_save_payment_method_checkbox', 10 );

		// Display the credit card used for a subscription in the "My Subscriptions" table.
		$this->add_filter( 'woocommerce_my_subscriptions_payment_method', 'get_gateway', 'maybe_render_subscription_payment_method', 10, 2 );

		// Used to filter out unwanted metadata on new renewal orders.
		if ( ! class_exists( 'WC_Subscriptions_Data_Copier' ) ) {
			$this->add_filter( 'wcs_renewal_order_meta_query', 'get_gateway', 'update_renewal_meta_data', 10, 3 );
		} else {
			$this->add_filter( 'wc_subscriptions_renewal_order_data', 'get_gateway', 'remove_data_renewal_order', 10, 3 );
		}

		// Allow store managers to manually set Stripe as the payment method on a subscription.
		$this->add_filter( 'woocommerce_subscription_payment_meta', 'get_gateway', 'add_subscription_payment_meta', 10, 2 );
		$this->add_filter( 'woocommerce_subscription_validate_payment_meta', 'get_gateway', 'validate_subscription_payment_meta', 10, 3 );
		$this->add_action( 'wcs_save_other_payment_meta', 'get_gateway', 'save_meta_in_order_tokens', 10, 4 );

		// To make sure payment meta is copied from subscription to order.
		$this->add_filter( 'wcs_copy_payment_meta_to_order', 'get_gateway', 'append_payment_meta', 10, 3 );

		$this->add_filter( 'woocommerce_subscription_note_old_payment_method_title', 'get_gateway', 'get_specific_old_payment_method_title', 10, 3 );
		$this->add_filter( 'woocommerce_subscription_note_new_payment_method_title', 'get_gateway', 'get_specific_new_payment_method_title', 10, 3 );

		// TODO: Remove admin payment method JS hack for Subscriptions <= 3.0.7 when we drop support for those versions.
		// Enqueue JS hack when Subscriptions does not provide the meta input filter.
		if ( $this->is_subscriptions_plugin_active() && version_compare( $this->get_subscriptions_plugin_version(), '3.0.7', '<=' ) ) {
			$this->add_action( 'woocommerce_admin_order_data_after_billing_address', 'get_gateway', 'add_payment_method_select_to_subscription_edit' );
		}

		/*
		 * WC subscriptions hooks into the "template_redirect" hook with priority 100.
		 * If the screen is "Pay for order" and the order is a subscription renewal, it redirects to the plain checkout.
		 * See: https://github.com/woocommerce/woocommerce-subscriptions/blob/99a75687e109b64cbc07af6e5518458a6305f366/includes/class-wcs-cart-renewal.php#L165
		 * If we are in the "You just need to authorize SCA" flow, we don't want that redirection to happen.
		 */
		$this->add_action( 'template_redirect', 'get_gateway', 'remove_order_pay_var', 99 );
		$this->add_action( 'template_redirect', 'get_gateway', 'restore_order_pay_var', 101 );

		// Update subscriptions token when user sets a default payment method.
		$this->add_filter( 'woocommerce_subscriptions_update_subscription_token', 'get_gateway', 'update_subscription_token', 10, 3 );
	}

	private function is_subscriptions_enabled() {
		if ( $this->is_subscriptions_plugin_active() ) {
			return version_compare( $this->get_subscriptions_plugin_version(), '2.2.0', '>=' );
		}

		// TODO update this once we know how the base library feature will be enabled.
		return class_exists( 'WC_Subscriptions_Core_Plugin' );
	}

	private function is_subscriptions_plugin_active() {
		return class_exists( 'WC_Subscriptions' );
	}

	public function get_subscriptions_plugin_version() {
		return class_exists( 'WC_Subscriptions' ) ? WC_Subscriptions::$version : null;
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
