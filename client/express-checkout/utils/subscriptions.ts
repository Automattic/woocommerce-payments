/**
 * Internal dependencies
 */
import { getExpressCheckoutData } from './express-checkout-data';

export type SetupFutureUsage = 'off_session' | null;

type CartData = {
	extensions?: {
		wcpay?: {
			setup_future_usage?: SetupFutureUsage;
		};
		[ key: string ]: unknown;
	};
	[ key: string ]: unknown;
};

/**
 * Reads the server's decision from the localized express checkout params.
 * Used on the product page (no cart yet) and pay-for-order (order API, not cart).
 *
 * @return Stripe setupFutureUsage value.
 */
export const getSetupFutureUsageForContext = (): SetupFutureUsage =>
	getExpressCheckoutData( 'setup_future_usage' ) ?? null;

/**
 * Gets the setupFutureUsage value that should be passed to Stripe Elements for
 * the current cart.
 *
 * The server decides this — it is the only side that knows every reason the payment
 * method might be saved. WooPayments registers `extensions.wcpay.setup_future_usage`
 * on the cart Store API; pay-for-order uses the order endpoint, which does not.
 *
 * @param cartData Cart data from Store API.
 * @return Stripe setupFutureUsage value.
 */
export const getSetupFutureUsageForCart = (
	cartData?: CartData
): SetupFutureUsage => {
	const wcpayExtension = cartData?.extensions?.wcpay;

	if ( wcpayExtension && 'setup_future_usage' in wcpayExtension ) {
		// Presence, not truthiness: an explicit `null` is the server deciding
		// this cart does not save the payment method.
		return wcpayExtension.setup_future_usage ?? null;
	}

	if ( getExpressCheckoutData( 'button_context' ) === 'pay_for_order' ) {
		return getSetupFutureUsageForContext();
	}

	return null;
};
