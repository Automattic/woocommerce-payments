/**
 * Internal dependencies
 */
import { getExpressCheckoutData } from './index';

/**
 * Builds the payment method types array from enabled express methods.
 * Sent to Stripe to ensure PaymentIntent uses matching types.
 */
export function buildPaymentMethodTypes( enabledMethods?: string[] ): string[] {
	const methods =
		enabledMethods ?? getExpressCheckoutData( 'enabled_methods' ) ?? [];
	return [
		methods.includes( 'payment_request' ) && 'card',
		methods.includes( 'amazon_pay' ) && 'amazon_pay',
	].filter( Boolean ) as string[];
}
