<?php
/**
 * Class WC_Payments_Subscriptions.
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for loading WooCommerce Payments Subscriptions.
 */
class WC_Payments_Subscriptions {

	/**
	 * Instance of WC_Payments_Product_Service, created in init function.
	 *
	 * @var WC_Payments_Product_Service
	 */
	private static $product_service;

	/**
	 * Instance of WC_Payments_Invoice_Service, created in init function.
	 *
	 * @var WC_Payments_Invoice_Service
	 */
	private static $invoice_service;

	/**
	 * Instance of WC_Payments_Subscription_Service, created in init function.
	 *
	 * @var WC_Payments_Subscription_Service
	 */
	private static $subscription_service;

	/**
	 * Instance of WC_Payments_Subscriptions_Event_Handler, created in init function.
	 *
	 * @var WC_Payments_Subscriptions_Event_Handler
	 */
	private static $event_handler;

	/**
	 * Instance of WC_Payments_Subscription_Data_Migration_Manager, created on action hook init.
	 *
	 * @var WC_Payments_Subscription_Data_Migration_Manager
	 */
	private static $data_migration_manager;

	/**
	 * Initialize WooCommerce Payments subscriptions. (Stripe Billing)
	 *
	 * @param WC_Payments_API_Client       $api_client       WCPay API client.
	 * @param WC_Payments_Customer_Service $customer_service WCPay Customer Service.
	 */
	public static function init( WC_Payments_API_Client $api_client, WC_Payments_Customer_Service $customer_service ) {
		// Load Services.
		include_once __DIR__ . '/class-wc-payments-product-service.php';
		include_once __DIR__ . '/class-wc-payments-invoice-service.php';
		include_once __DIR__ . '/class-wc-payments-subscription-service.php';
		include_once __DIR__ . '/class-wc-payments-subscription-change-payment-method-handler.php';
		include_once __DIR__ . '/class-wc-payments-subscription-data-migration-manager.php';

		self::$product_service      = new WC_Payments_Product_Service( $api_client );
		self::$invoice_service      = new WC_Payments_Invoice_Service( $api_client, self::$product_service );
		self::$subscription_service = new WC_Payments_Subscription_Service( $api_client, $customer_service, self::$product_service, self::$invoice_service );

		// Load the subscription and invoice incoming event handler.
		include_once __DIR__ . '/class-wc-payments-subscriptions-event-handler.php';
		self::$event_handler = new WC_Payments_Subscriptions_Event_Handler( self::$invoice_service, self::$subscription_service );

		new WC_Payments_Subscription_Change_Payment_Method_Handler();

		/**
		 * If the WC Subscriptions plugin is currently being activated, there's a lapse in Subscriptions
		 * functionality being available (WC Pay stops loading it's packaged version but the plugin isn't yet active).
		 *
		 * For that reason we cannot load the migrators until we have access to a Subscriptions install.
		 */
		if ( ! self::is_plugin_being_activated() ) {
			add_action( 'init', [ __CLASS__, 'get_data_migration_manager' ] );
		}
	}

	/**
	 * Get the Event Handler class instance.
	 *
	 * @return WC_Payments_Subscriptions_Event_Handler
	 */
	public static function get_event_handler() {
		return self::$event_handler;
	}

	/**
	 * Returns the product service instance.
	 *
	 * @return WC_Payments_Product_Service The product service object.
	 */
	public static function get_product_service() {
		return self::$product_service;
	}

	/**
	 * Returns the invoice service instance.
	 *
	 * @return WC_Payments_Invoice_Service
	 */
	public static function get_invoice_service() {
		return self::$invoice_service;
	}

	/**
	 * Returns the subscription service instance.
	 *
	 * @return WC_Payments_Subscription_Service
	 */
	public static function get_subscription_service() {
		return self::$subscription_service;
	}

	/**
	 * Returns the data migration manager instance.
	 *
	 * @return WC_Payments_Subscription_Data_Migration_Manager
	 */
	public static function get_data_migration_manager() {
		if ( is_null( self::$data_migration_manager ) && did_action( 'init' ) ) {
			self::$data_migration_manager = new WC_Payments_Subscription_Data_Migration_Manager();
		}

		return self::$data_migration_manager;
	}

	/**
	 * Checks if the current request is to activate the WC Subscriptions plugin.
	 *
	 * @return bool
	 */
	private static function is_plugin_being_activated() {
		$wc_subscriptions_plugin_slug = 'woocommerce-subscriptions/woocommerce-subscriptions.php';
		return isset( $_GET['action'], $_GET['plugin'] ) && 'activate' === wc_clean( wp_unslash( $_GET['action'] ) ) && wc_clean( wp_unslash( $_GET['plugin'] ) ) === $wc_subscriptions_plugin_slug; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
}
