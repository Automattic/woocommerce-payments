/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

interface OrderDetails {
	number: number;
	url: string;
	customer_url?: string;
	subscriptions?: Array< { number: number; url: string } >;
}
