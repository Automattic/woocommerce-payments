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
	 * Initialize WooCommerce Payments subscriptions. (Stripe Billing)
	 *
	 * @param WC_Payments_API_Client       $api_client       WCPay API client.
	 * @param WC_Payments_Customer_Service $customer_service WCPay Customer Service.
	 * @param WC_Payment_Gateway_WCPay     $gateway          WCPay Payment Gateway.
	 * @param WC_Payments_Account          $account          WC_Payments_Account.
	 */
	public static function init( WC_Payments_API_Client $api_client, WC_Payments_Customer_Service $customer_service, WC_Payment_Gateway_WCPay $gateway, WC_Payments_Account $account ) {
		// Load Services.
		include_once __DIR__ . '/class-wc-payments-product-service.php';
		include_once __DIR__ . '/class-wc-payments-invoice-service.php';
		include_once __DIR__ . '/class-wc-payments-subscription-service.php';
		include_once __DIR__ . '/class-wc-payments-subscription-change-payment-method-handler.php';
		include_once __DIR__ . '/class-wc-payments-subscriptions-plugin-notice-manager.php';
		include_once __DIR__ . '/class-wc-payments-subscriptions-empty-state-manager.php';

		self::$product_service      = new WC_Payments_Product_Service( $api_client );
		self::$invoice_service      = new WC_Payments_Invoice_Service( $api_client, self::$product_service, $gateway );
		self::$subscription_service = new WC_Payments_Subscription_Service( $api_client, $customer_service, self::$product_service, self::$invoice_service );

		// Load the subscription and invoice incoming event handler.
		include_once __DIR__ . '/class-wc-payments-subscriptions-event-handler.php';
		self::$event_handler = new WC_Payments_Subscriptions_Event_Handler( self::$invoice_service, self::$subscription_service );

		new WC_Payments_Subscription_Change_Payment_Method_Handler();
		new WC_Payments_Subscriptions_Plugin_Notice_Manager();

		new WC_Payments_Subscriptions_Empty_State_Manager( $account );

		// Load the Subscriptions Onboarding class.
		include_once __DIR__ . '/class-wc-payments-subscriptions-onboarding-handler.php';
		new WC_Payments_Subscriptions_Onboarding_Handler( $account );

		include_once __DIR__ . '/class-wc-payments-subscription-minimum-amount-handler.php';
		new WC_Payments_Subscription_Minimum_Amount_Handler( $api_client );
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
}
