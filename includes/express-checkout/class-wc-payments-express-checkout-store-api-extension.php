<?php
/**
 * Class WC_Payments_Express_Checkout_Store_API_Extension
 *
 * Adds an `extensions.wcpay.express_checkout_methods` field to the Store API
 * cart response. The field carries the Express Checkout method list filtered
 * for the cart's currency at request time, so the client can re-evaluate
 * `payment_method_types` when its locally-resolved currency differs from
 * the one the page was rendered with.
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
	 * Express checkout helper used to source the canonical filtered method list.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Helper
	 */
	private $express_checkout_helper;

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_Express_Checkout_Button_Helper $express_checkout_helper Express checkout helper.
	 */
	public function __construct( WC_Payments_Express_Checkout_Button_Helper $express_checkout_helper ) {
		$this->express_checkout_helper = $express_checkout_helper;
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
	 * Returns the ECE method list filtered for the current request's currency.
	 *
	 * Delegates to the canonical helper so the Store API answer matches what
	 * the page-render-time localization would produce given the same currency.
	 *
	 * @return array{express_checkout_methods: string[]}
	 */
	public function extend_cart_data() {
		return [
			'express_checkout_methods' => array_values(
				$this->express_checkout_helper->get_enabled_express_checkout_methods_for_context()
			),
		];
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
