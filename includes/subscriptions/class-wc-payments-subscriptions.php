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
	 * Instance of WC_Payments_Order_Service, created in init function.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private static $order_service;

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
	 * Instance of WC_Payments_Subscriptions_Migrator, created in init function.
	 *
	 * @var WC_Payments_Subscriptions_Migrator
	 */
	private static $stripe_billing_migrator;

	/**
	 * Initialize WooCommerce Payments subscriptions. (Stripe Billing)
	 *
	 * @param WC_Payments_API_Client       $api_client       WCPay API client.
	 * @param WC_Payments_Customer_Service $customer_service WCPay Customer Service.
	 * @param WC_Payments_Order_Service    $order_service    WCPay Order Service.
	 * @param WC_Payments_Account          $account          WC_Payments_Account.
	 * @param WC_Payments_Token_Service    $token_service    WC_Payments_Token_Service.
	 */
	public static function init( WC_Payments_API_Client $api_client, WC_Payments_Customer_Service $customer_service, WC_Payments_Order_Service $order_service, WC_Payments_Account $account, WC_Payments_Token_Service $token_service ) {
		// Store dependencies.
		self::$order_service = $order_service;

		// Load Services.
		include_once __DIR__ . '/class-wc-payments-product-service.php';
		include_once __DIR__ . '/class-wc-payments-invoice-service.php';
		include_once __DIR__ . '/class-wc-payments-subscription-service.php';
		include_once __DIR__ . '/class-wc-payments-subscription-change-payment-method-handler.php';
		include_once __DIR__ . '/class-wc-payments-subscriptions-plugin-notice-manager.php';
		include_once __DIR__ . '/class-wc-payments-subscriptions-empty-state-manager.php';
		include_once __DIR__ . '/class-wc-payments-subscriptions-event-handler.php';
		include_once __DIR__ . '/class-wc-payments-subscriptions-onboarding-handler.php';
		include_once __DIR__ . '/class-wc-payments-subscription-minimum-amount-handler.php';

		// Instantiate additional classes.
		self::$product_service      = new WC_Payments_Product_Service( $api_client, $account );
		self::$invoice_service      = new WC_Payments_Invoice_Service( $api_client, self::$product_service, self::$order_service );
		self::$subscription_service = new WC_Payments_Subscription_Service( $api_client, $customer_service, self::$product_service, self::$invoice_service );
		self::$event_handler        = new WC_Payments_Subscriptions_Event_Handler( self::$invoice_service, self::$subscription_service );

		new WC_Payments_Subscription_Change_Payment_Method_Handler();
		new WC_Payments_Subscriptions_Plugin_Notice_Manager();
		new WC_Payments_Subscriptions_Empty_State_Manager( $account );
		new WC_Payments_Subscriptions_Onboarding_Handler( $account );
		new WC_Payments_Subscription_Minimum_Amount_Handler( $api_client );

		if ( class_exists( 'WCS_Background_Repairer' ) ) {
			include_once __DIR__ . '/class-wc-payments-subscriptions-migrator.php';
			self::$stripe_billing_migrator = new WC_Payments_Subscriptions_Migrator( $api_client, $token_service );
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
	 * Returns the the product service instance.
	 *
	 * @return WC_Payments_Product_Service The product service object.
	 */
	public static function get_product_service() {
		return self::$product_service;
	}

	/**
	 * Returns the the invoice service instance.
	 *
	 * @return WC_Payments_Invoice_Service
	 */
	public static function get_invoice_service() {
		return self::$invoice_service;
	}

	/**
	 * Returns the the subscription service instance.
	 *
	 * @return WC_Payments_Subscription_Service
	 */
	public static function get_subscription_service() {
		return self::$subscription_service;
	}

	/**
	 * Returns the the Stripe Billing migrator instance.
	 *
	 * @return WC_Payments_Subscriptions_Migrator
	 */
	public static function get_stripe_billing_migrator() {
		return self::$stripe_billing_migrator;
	}

	/**
	 * Determines if this is a duplicate/staging site.
	 *
	 * This function is a wrapper for WCS_Staging::is_duplicate_site().
	 *
	 * @return bool Whether the site is a duplicate URL or not.
	 */
	public static function is_duplicate_site() {
		if ( class_exists( 'WC_Subscriptions' )
				&& version_compare( WC_Subscriptions::$version, '4.0.0', '<' )
				&& method_exists( 'WC_Subscriptions', 'is_duplicate_site' )
			) {
			return WC_Subscriptions::is_duplicate_site();
		}

		return class_exists( 'WCS_Staging' )
			&& method_exists( 'WCS_Staging', 'is_duplicate_site' )
			&& WCS_Staging::is_duplicate_site();
	}
}
