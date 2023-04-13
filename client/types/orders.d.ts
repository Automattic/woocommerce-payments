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
	number: number;
	url: string;
	customer_url: null | string;
	subscriptions?: SubscriptionDetails[];
	fraud_meta_box_type?: string;
}
