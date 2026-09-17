/**
 * Internal dependencies
 */
import { getExpressCheckoutData } from './express-checkout-data';

export type SetupFutureUsage = 'off_session' | null;

type WcpayCartExtensions = {
	wcpay?: {
		setup_future_usage?: SetupFutureUsage;
	};
	[ key: string ]: unknown;
};

/**
 * Reads the server's decision from the localized express checkout params.
 * Used on the product page (no cart yet) and pay-for-order (order API, not cart).
 *
 * @return Stripe setupFutureUsage value.
 */
export const getLocalizedSetupFutureUsage = (): SetupFutureUsage =>
	getExpressCheckoutData( 'setup_future_usage' ) ?? null;

/**
 * Resolves the setupFutureUsage value to pass to Stripe Elements.
 *
 * Prefers `extensions.wcpay.setup_future_usage` from the cart Store API — the
 * server is the only side that knows every reason the payment method might be
 * saved. Pay-for-order uses the order endpoint, which does not carry that
 * extension, so it falls back to the localized value.
 *
 * @param cartData Cart data from Store API (only `extensions` is read).
 * @return Stripe setupFutureUsage value.
 */
export const resolveSetupFutureUsage = ( cartData?: {
	extensions?: unknown;
} ): SetupFutureUsage => {
	const extensions = cartData?.extensions as WcpayCartExtensions | undefined;
	const wcpayExtension = extensions?.wcpay;

	if ( wcpayExtension && 'setup_future_usage' in wcpayExtension ) {
		// Presence, not truthiness: an explicit `null` is the server deciding
		// this cart does not save the payment method.
		return wcpayExtension.setup_future_usage ?? null;
	}

	if ( getExpressCheckoutData( 'button_context' ) === 'pay_for_order' ) {
		return getLocalizedSetupFutureUsage();
	}

	return null;
};
