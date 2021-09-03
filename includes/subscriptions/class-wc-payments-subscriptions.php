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
	 * Initialize WooCommerce Payments subscriptions. (Stripe Billing)
	 *
	 * @param WC_Payments_API_Client       $api_client       WCPay API client.
	 * @param WC_Payments_Customer_Service $customer_service WCPay Customer Service.
	 */
	public static function init( WC_Payments_API_Client $api_client, WC_Payments_Customer_Service $customer_service ) {
		include_once __DIR__ . '/class-wc-payments-product-service.php';
		include_once __DIR__ . '/class-wc-payments-invoice-service.php';
		include_once __DIR__ . '/class-wc-payments-subscription-service.php';

		self::$product_service      = new WC_Payments_Product_Service( $api_client );
		self::$invoice_service      = new WC_Payments_Invoice_Service( $api_client, self::$product_service );
		self::$subscription_service = new WC_Payments_Subscription_Service( $api_client, $customer_service, self::$product_service, self::$invoice_service );
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
