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
	 * Instance of WC_Payments_Tax_Service, created in init function.
	 *
	 * @var WC_Payments_Tax_Service
	 */
	private static $tax_service;

	/**
	 * Instance of WC_Payments_Invoice_Service, created in init function.
	 *
	 * @var WC_Payments_Invoice_Service
	 */
	private static $invoice_service;

	/**
	 * Initialize WooCommerce Payments subscriptions. (Stripe Billing)
	 *
	 * @param WC_Payments_API_Client $api_client WCPay API client.
	 */
	public static function init( WC_Payments_API_Client $api_client ) {
		include_once __DIR__ . '/class-wc-payments-product-service.php';
		include_once __DIR__ . '/class-wc-payments-invoice-service.php';

		self::$product_service = new WC_Payments_Product_Service( $api_client );
		self::$tax_service     = null;
		self::$invoice_service = new WC_Payments_Invoice_Service( $api_client, self::$tax_service );

	}
}
