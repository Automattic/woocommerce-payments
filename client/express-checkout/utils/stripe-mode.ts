/**
 * Internal dependencies
 */
import { getExpressCheckoutData } from './express-checkout-data';

/**
 * Checks if the current cart or product context contains a subscription.
 */
const hasSubscriptionInContext = (): boolean => {
	const productType = getExpressCheckoutData( 'product' )?.product_type ?? '';
	const isSubscriptionProduct = [
		'subscription',
		'variable-subscription',
		'subscription_variation',
	].includes( productType );

	return (
		Boolean( getExpressCheckoutData( 'has_subscription' ) ) ||
		isSubscriptionProduct
	);
};

/**
 * Returns the Stripe Elements mode based on the current context.
 * Returns 'subscription' for subscription products (to handle payment method saving internally),
 * or 'payment' for regular products.
 */
export const getStripeElementsMode = (): 'subscription' | 'payment' => {
	return hasSubscriptionInContext() ? 'subscription' : 'payment';
};
