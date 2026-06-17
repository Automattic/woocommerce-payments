/**
 * External dependencies
 */
import type { StripeElementLocale } from '@stripe/stripe-js';

/**
 * Internal dependencies
 */
import {
	getExpressCheckoutButtonAppearance,
	getExpressCheckoutData,
} from './index';

interface ElementsOptionsParams {
	amount: number;
	currency: string;
	useConfirmationTokens: boolean;
	paymentMethodTypes?: string[];
	mode?: 'payment' | 'subscription';
	appearance?: ReturnType< typeof getExpressCheckoutButtonAppearance >;
	locale?: string;
	captureMethod?: 'automatic' | 'manual';
	setupFutureUsage?: 'off_session' | null;
}

/**
 * Builds the options object for Stripe Elements initialization.
 * Used by all four express checkout paths.
 */
export function buildStripeElementsOptions( {
	amount,
	currency,
	useConfirmationTokens,
	paymentMethodTypes = [],
	mode = 'payment',
	appearance,
	locale,
	captureMethod,
	setupFutureUsage,
}: ElementsOptionsParams ) {
	return {
		mode,
		amount: Math.max( amount, 1 ),
		currency: currency.toLowerCase(),
		...( useConfirmationTokens
			? { paymentMethodTypes }
			: { paymentMethodCreation: 'manual' as const } ),
		// `captureMethod` and `setupFutureUsage` are only compatible with the
		// confirmation tokens flow - with manual payment method creation, the
		// equivalent values are set server-side on the intent instead.
		...( useConfirmationTokens && captureMethod === 'manual'
			? { captureMethod: 'manual' as const }
			: {} ),
		...( useConfirmationTokens && setupFutureUsage !== undefined
			? { setupFutureUsage }
			: {} ),
		appearance:
			appearance ?? getExpressCheckoutButtonAppearance( undefined ),
		locale: ( locale ??
			getExpressCheckoutData( 'stripe' )?.locale ??
			'en' ) as StripeElementLocale,
	};
}
