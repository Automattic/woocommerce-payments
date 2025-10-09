<?php
/**
 * WC_Payments_Subscriptions_Core_Limited class
 *
 * Extends WC_Subscriptions_Core_Plugin to disable UI and management features
 * while preserving Stripe Billing renewal functionality for WooPayments 10.2+.
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Limited version of WC_Subscriptions_Core_Plugin for WCPay 10.2+.
 *
 * This class initializes only the components needed for Stripe Billing renewals,
 * disabling all subscription management UI and creation features.
 */
class WC_Payments_Subscriptions_Core_Limited extends WC_Subscriptions_Core_Plugin {

	/**
	 * Initialise the plugin with limited functionality.
	 *
	 * Keeps renewal processing active while disabling UI components.
	 */
	public function init() {
		$payment_gateways_handler = $this->get_gateways_handler_class();

		// Core subscription functionality needed for renewals.
		WC_Subscriptions_Manager::init();
		WC_Subscriptions_Order::init();
		WC_Subscriptions_Renewal_Order::init();
		WC_Subscriptions_Email::init();
		WC_Subscriptions_Addresses::init();
		$payment_gateways_handler::init();
		WCS_Upgrade_Logger::init();
		WCS_Download_Handler::init();
		WCS_Staging::init();
		WCS_Permalink_Manager::init();
		WCS_Custom_Order_Item_Manager::init();
		WCS_Dependent_Hook_Manager::init();

		// Renewal cart functionality for webhook-created renewal orders.
		new WCS_Cart_Renewal();

		/*
		 * Skipped components - not needed for renewal processing:
		 * - WC_Subscriptions_Product - Product creation and management
		 * - WC_Subscriptions_Admin - Admin UI components
		 * - WCS_Admin_Product_Import_Export_Manager - Product import/export
		 * - WCS_Admin_Empty_List_Content_Manager - Empty list content
		 * - WC_Subscriptions_Cart - Cart and checkout for new subscriptions
		 * - WC_Subscriptions_Cart_Validator - Cart validation
		 * - WC_Subscriptions_Checkout - Checkout processing
		 * - WCS_Cart_Resubscribe - Resubscribe cart
		 * - WCS_Cart_Initial_Payment - Initial payment cart
		 * - WC_Subscriptions_Coupon - Coupon functionality
		 * - WC_Subscriptions_Frontend_Scripts - Frontend scripts
		 * - WC_Subscriptions_Change_Payment_Gateway - Change payment gateway UI
		 * - WCS_PayPal_Standard_Change_Payment_Method - PayPal change payment
		 * - WCS_Limiter - Subscription limits on creation
		 * - WCS_Admin_System_Status - Admin system status
		 * - WC_Subscriptions_Tracker - Tracker
		 */

		// Synchroniser for renewal scheduling.
		add_action( 'init', [ 'WC_Subscriptions_Synchroniser', 'init' ] );

		// Upgrader for data migrations.
		add_action( 'after_setup_theme', [ 'WC_Subscriptions_Upgrader', 'init' ], 11 );

		// Version dependent classes (but override to skip UI components).
		add_action( 'plugins_loaded', [ $this, 'init_version_dependant_classes' ] );

		// Related order and customer data stores (needed for renewals).
		add_action( 'plugins_loaded', 'WCS_Related_Order_Store::instance' );
		add_action( 'plugins_loaded', 'WCS_Customer_Store::instance' );

		// Scheduler for renewal processing.
		$scheduler_class = apply_filters( 'woocommerce_subscriptions_scheduler', 'WCS_Action_Scheduler' );
		$this->scheduler = new $scheduler_class();

		// Cache manager.
		$this->cache = WCS_Cache_Manager::get_instance();

		// Keep gateway restrictions if needed.
		if ( ! $payment_gateways_handler::are_zero_total_subscriptions_allowed() ) {
			WC_Subscriptions_Gateway_Restrictions_Manager::init();
		}
	}

	/**
	 * Initialises limited version-dependent classes (skip UI components).
	 *
	 * Hooked onto 'plugins_loaded'.
	 */
	public function init_version_dependant_classes() {
		/*
		 * Skipped UI components:
		 * - WCS_Admin_Post_Types - Subscription list pages
		 * - WCS_Admin_Meta_Boxes - Subscription edit screens
		 * - WCS_Template_Loader - Customer subscription pages
		 * - WCS_Remove_Item - Remove item functionality
		 * - WCS_User_Change_Status_Handler - User status change handler
		 * - WCS_My_Account_Payment_Methods - My Account payment methods
		 * - WCS_My_Account_Auto_Renew_Toggle - My Account auto-renew toggle
		 * - WCS_Query - Query modifications for subscriptions
		 */

		// Deprecated filter hooks for compatibility.
		new WCS_Deprecated_Filter_Hooks();

		// Failed scheduled action manager (renewal processing).
		$failed_scheduled_action_manager = new WCS_Failed_Scheduled_Action_Manager( new WC_Logger() );
		$failed_scheduled_action_manager->init();

		/**
		 * Allow third-party code to enable running v2.0 hook deprecation handling.
		 *
		 * @param bool Whether the hook deprecation handlers should be loaded. False by default.
		 */
		if ( apply_filters( 'woocommerce_subscriptions_load_deprecation_handlers', false ) ) {
			new WCS_Action_Deprecator();
			new WCS_Filter_Deprecator();
			new WCS_Dynamic_Action_Deprecator();
			new WCS_Dynamic_Filter_Deprecator();
		}
	}
}
