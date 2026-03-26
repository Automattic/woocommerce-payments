/**
 * External dependencies
 */
import { memoize } from 'lodash';
import { applyFilters } from '@wordpress/hooks';
import type { Stripe, AvailablePaymentMethods } from '@stripe/stripe-js';

/**
 * Internal dependencies
 */
import type WCPayAPI from 'wcpay/checkout/api';
import { getExpressCheckoutData, getStripeElementsMode } from '.';
import { transformPrice } from '../transformers/wc-to-stripe';

interface CartTotals {
	total_price: string;
	currency_code: string;
	currency_minor_unit: number;
}

interface Cart {
	extensions: unknown;
	cartItems: unknown;
	cartTotals: CartTotals;
}

type PaymentMethod = keyof AvailablePaymentMethods;

/**
 * Gets the effective total price for Stripe initialization.
 * Uses the wcpay.express-checkout.total-amount filter to allow modifications
 * (e.g., for trial subscriptions with $0 initial payment).
 */
const getEffectiveTotalPrice = ( cart: Cart ): string => {
	const filteredTotal = applyFilters(
		'wcpay.express-checkout.total-amount',
		transformPrice(
			parseInt( cart.cartTotals.total_price, 10 ),
			cart.cartTotals
		),
		{
			totals: cart.cartTotals,
			items: cart.cartItems,
			extensions: cart.extensions,
		}
	) as number;

	return String( filteredTotal );
};

/**
 * Mounts a hidden Stripe Express Checkout Element to detect which express
 * payment methods are available on the current device/browser.
 *
 * Both 'card' and 'amazon_pay' must be in paymentMethodTypes for Stripe to
 * report their availability — this matches the product page's Elements config.
 */
function checkAvailablePaymentMethods(
	stripe: Stripe,
	amount: number,
	currency: string,
	mode: string
): Promise< Partial< AvailablePaymentMethods > > {
	const useConfirmationToken =
		getExpressCheckoutData( 'flags' )?.isEceUsingConfirmationTokens ?? true;

	return new Promise( ( resolve ) => {
		try {
			const container = document.createElement( 'div' );
			container.style.position = 'absolute';
			container.style.left = '-9999px';
			container.style.top = '-9999px';
			document.body.appendChild( container );

			const elements = stripe.elements( {
				mode: mode as 'payment' | 'subscription',
				amount: Math.max( amount, 1 ),
				currency,
				...( useConfirmationToken
					? { paymentMethodTypes: [ 'card', 'amazon_pay' ] }
					: { paymentMethodCreation: 'manual' } ),
			} );

			const eceButton = elements.create( 'expressCheckout', {
				buttonType: { applePay: 'plain', googlePay: 'plain' },
				paymentMethods: {
					applePay: 'always',
					googlePay: 'always',
					amazonPay: 'auto',
					link: 'never',
					paypal: 'never',
					klarna: 'never',
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

// Module-level cache for the Stripe promise and memoized check.
let cachedStripePromise: Promise< Stripe > | null = null;
let memoizedCheck:
	| ( (
			_amount: number,
			_currency: string,
			_mode: string
	  ) => Promise< Partial< AvailablePaymentMethods > > )
	| null = null;

/**
 * Checks if a specific express payment method is available.
 * Results are memoized by amount+currency+mode combination.
 */
export async function checkPaymentMethodIsAvailable(
	paymentMethod: PaymentMethod,
	cart: Cart,
	api: WCPayAPI
): Promise< boolean > {
	// Guard against empty currency code during WooCommerce Blocks store
	// hydration. The cart store initialises with currency_code: '' before
	// server-side preloaded data is applied. Passing an empty string to
	// Stripe Elements throws. Returning false here lets WC Blocks
	// re-evaluate once the cart data (and currency) is properly loaded.
	if ( ! cart.cartTotals.currency_code ) {
		return false;
	}

	if ( ! cachedStripePromise ) {
		cachedStripePromise = api.loadStripeForExpressCheckout();
	}

	let stripe: Stripe;
	try {
		stripe = await cachedStripePromise;
	} catch {
		return false;
	}

	if ( ( ( stripe as unknown ) as { error: unknown } )?.error ) {
		return false;
	}

	if ( ! memoizedCheck ) {
		memoizedCheck = memoize(
			// eslint-disable-next-line @typescript-eslint/naming-convention
			( _amount: number, _currency: string, _mode: string ) =>
				checkAvailablePaymentMethods(
					stripe,
					_amount,
					_currency,
					_mode
				),
			// eslint-disable-next-line @typescript-eslint/naming-convention
			( _amount: number, _currency: string, _mode: string ) =>
				`${ _amount }-${ _currency }-${ _mode }`
		);
	}

	const totalPrice = getEffectiveTotalPrice( cart );
	const mode = getStripeElementsMode();

	const availablePaymentMethods = await memoizedCheck(
		Number( totalPrice ),
		cart.cartTotals.currency_code.toLowerCase(),
		mode
	);

	return Boolean( availablePaymentMethods[ paymentMethod ] );
}

/**
 * Resets module-level caches. Only for testing.
 */
// eslint-disable-next-line @typescript-eslint/naming-convention
export function _resetForTesting(): void {
	cachedStripePromise = null;
	memoizedCheck = null;
}
