<?php
/**
 * Adds `extensions.wcpay.express_checkout_methods` to the Store API cart
 * response, currency-filtered at request time so the client can re-evaluate
 * `payment_method_types` when the resolved currency differs from the one
 * localized at page render.
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;

/**
 * Registers the Store API cart extension.
 */
class WC_Payments_Express_Checkout_Store_API_Extension {

	const NAMESPACE_KEY = 'wcpay';

	/**
	 * Express checkout helper used to gate amazon_pay availability.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Helper
	 */
	private $express_checkout_helper;

	/**
	 * WCPay gateway used to gate the payment_request (Apple Pay / Google Pay)
	 * availability.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_Express_Checkout_Button_Helper $express_checkout_helper Express checkout helper.
	 * @param WC_Payment_Gateway_WCPay                   $gateway                 WCPay gateway.
	 */
	public function __construct( WC_Payments_Express_Checkout_Button_Helper $express_checkout_helper, WC_Payment_Gateway_WCPay $gateway ) {
		$this->express_checkout_helper = $express_checkout_helper;
		$this->gateway                 = $gateway;
	}

	/**
	 * Register the extension. Safe to call once on `woocommerce_blocks_loaded`.
	 */
	public function init() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		if ( ! class_exists( CartSchema::class ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			[
				'endpoint'        => CartSchema::IDENTIFIER,
				'namespace'       => self::NAMESPACE_KEY,
				'data_callback'   => [ $this, 'extend_cart_data' ],
				'schema_callback' => [ $this, 'extend_cart_schema' ],
				'schema_type'     => ARRAY_A,
			]
		);
	}

	/**
	 * Returns the ECE method list filtered against the cart's resolved currency.
	 *
	 * Location gating is intentionally skipped here, since the cart endpoint
	 * isn't location-bound. The client intersects this list with the localized
	 * `methods_enabled_at_location` (the raw, currency-independent location
	 * settings) to get the final set.
	 *
	 * @return array{express_checkout_methods: string[]}
	 */
	public function extend_cart_data() {
		$methods = [];

		if ( $this->gateway->is_payment_request_enabled() ) {
			$methods[] = 'payment_request';
		}

		if ( $this->express_checkout_helper->can_use_amazon_pay() ) {
			$methods[] = 'amazon_pay';
		}

		return [ 'express_checkout_methods' => $methods ];
	}

	/**
	 * Schema for the extension data.
	 *
	 * @return array
	 */
	public function extend_cart_schema() {
		return [
			'express_checkout_methods' => [
				'description' => __( 'Express Checkout methods available for the cart\'s current currency.', 'woocommerce-payments' ),
				'type'        => 'array',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
				'items'       => [
					'type' => 'string',
				],
			],
		];
	}
}
