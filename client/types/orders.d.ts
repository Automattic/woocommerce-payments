/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

interface SubscriptionDetails {
	number: number;
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
}
