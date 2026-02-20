/**
 * External dependencies
 */
import { memoize } from 'lodash';
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import { getStripeElementsMode } from '.';
import { transformPrice } from '../transformers/wc-to-stripe';

/**
 * Gets the effective total price for Stripe initialization.
 * Uses the wcpay.express-checkout.total-amount filter to allow modifications
 * (e.g., for trial subscriptions with $0 initial payment).
 *
 * @param {Object} cart The cart object from WC Blocks.
 * @return {string} The total price to use for Stripe.
 */
const getEffectiveTotalPrice = ( cart ) => {
	// Apply filter to allow modifications (e.g., for trial subscriptions)
	const filteredTotal = applyFilters(
		'wcpay.express-checkout.total-amount',
		// The filter expects numeric amounts, so we pass the transformed total
		transformPrice(
			parseInt( cart.cartTotals.total_price, 10 ),
			cart.cartTotals
		),
		{
			totals: cart.cartTotals,
			items: cart.cartItems,
			extensions: cart.extensions,
		}
	);

	return String( filteredTotal );
};

/**
 * Core function: creates a hidden Stripe Express Checkout Element to detect
 * which express payment methods are available on the current device/browser.
 *
 * @param {Object} stripe   The Stripe instance.
 * @param {number} amount   The payment amount in smallest currency unit.
 * @param {string} currency The currency code (lowercase).
 * @param {string} mode     The Stripe Elements mode ('payment' or 'subscription').
 * @return {Promise<Object>} Object with availability, e.g. { applePay: true, googlePay: false }.
 */
function checkAllMethodsInternal( stripe, amount, currency, mode ) {
	return new Promise( ( resolve ) => {
		try {
			const container = document.createElement( 'div' );
			container.style.position = 'absolute';
			container.style.left = '-9999px';
			container.style.top = '-9999px';
			document.body.appendChild( container );

			const elements = stripe.elements( {
				mode,
				amount: Math.max( amount, 1 ), // Stripe requires amount >= 1.
				currency,
				paymentMethodCreation: 'manual',
			} );

			const eceButton = elements.create( 'expressCheckout', {
				buttonType: { applePay: 'plain', googlePay: 'plain' },
				paymentMethods: {
					applePay: 'always',
					googlePay: 'always',
					amazonPay: 'auto',
				},
			} );

			eceButton.on( 'ready', ( { availablePaymentMethods } ) => {
				eceButton.unmount();
				container.remove();
				resolve( availablePaymentMethods || {} );
			} );

			eceButton.on( 'loaderror', () => {
				eceButton.unmount();
				container.remove();
				resolve( {} );
			} );

			eceButton.mount( container );
		} catch {
			resolve( {} );
		}
	} );
}

// Module-level cache for the Stripe promise and memoized check function.
let cachedStripePromise = null;
let memoizedCheck = null;

/**
 * Checks which express payment methods are available on the current device/browser.
 * Results are memoized by amount+currency+mode combination.
 *
 * @param {Object} api      The WCPay API instance.
 * @param {number} amount   The payment amount in smallest currency unit.
 * @param {string} currency The currency code (lowercase).
 * @param {string} mode     The Stripe Elements mode ('payment' or 'subscription'). Defaults to 'payment'.
 * @return {Promise<Object>} Object with availability, e.g. { applePay: true, googlePay: false, amazonPay: true }.
 */
export async function checkAllExpressMethodsAvailability(
	api,
	amount,
	currency,
	mode = 'payment'
) {
	if ( ! cachedStripePromise ) {
		cachedStripePromise = api.loadStripeForExpressCheckout();
	}

	let stripe;
	try {
		stripe = await cachedStripePromise;
	} catch {
		return {};
	}

	if ( stripe?.error ) {
		return {};
	}

	if ( ! memoizedCheck ) {
		memoizedCheck = memoize(
			( a, c, m ) => checkAllMethodsInternal( stripe, a, c, m ),
			( a, c, m ) => `${ a }-${ c }-${ m }`
		);
	}

	return memoizedCheck( amount, currency, mode );
}

/**
 * Checks if a specific express payment method is available.
 * Thin wrapper for blocks consumers — delegates to checkAllExpressMethodsAvailability.
 *
 * @param {string} paymentMethod The payment method key (e.g., 'applePay', 'googlePay').
 * @param {Object} cart          The cart object from WC Blocks.
 * @param {Object} api           The WCPay API instance.
 * @return {Promise<boolean>} Whether the specific payment method is available.
 */
export async function checkPaymentMethodIsAvailable(
	paymentMethod,
	cart,
	api
) {
	const totalPrice = getEffectiveTotalPrice( cart );
	const mode = getStripeElementsMode();

	const availablePaymentMethods = await checkAllExpressMethodsAvailability(
		api,
		Number( totalPrice ),
		cart.cartTotals.currency_code.toLowerCase(),
		mode
	);

	return Boolean( availablePaymentMethods[ paymentMethod ] );
}

/**
 * Resets module-level caches. Only for testing.
 */
export function _resetForTesting() {
	cachedStripePromise = null;
	memoizedCheck = null;
}
