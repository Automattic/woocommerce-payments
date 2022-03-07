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
}
