/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

interface SubscriptionDetails {
	number: string; // Comment for OderDetails.number below applies here as well.
	url: string;
}

interface OrderDetails {
	id: number;
	/**
	 * The order number for display.
	 * By default, it's order ID but a plugin can customize it.
	 * See PHP method WC_Order::get_order_number().
	 */
	number: string;
	url: string;
	customer_url: null | string;
	customer_email: null | string;
	customer_name: null | string;
	subscriptions?: SubscriptionDetails[];
	fraud_meta_box_type?: string;
	ip_address: string;
	/**
	 * Server-suggested product type derived from order line items. Used as a
	 * fallback when the merchant has not yet selected a product type via the
	 * dispute response wizard. Possible values match the `ProductType` union
	 * in `client/types/disputes.d.ts`, plus the empty string / `'multiple'`
	 * legacy values that the wizard normalizes at read time.
	 */
	suggested_product_type?: string;
}
