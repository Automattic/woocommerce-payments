/**
 * External dependencies
 */
import React from 'react';
import { createRoot } from 'react-dom/client';
import { ExpressCheckoutElement, Elements } from '@stripe/react-stripe-js';
import type { AvailablePaymentMethods } from '@stripe/stripe-js';
import { memoize } from 'lodash';
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import type WCPayAPI from 'wcpay/checkout/api';
import { getExpressCheckoutData, getStripeElementsMode } from '.';
import { transformPrice } from '../transformers/wc-to-stripe';
import { getPaymentMethodsOverride } from './payment-method-overrides';

// types from https://github.com/woocommerce/woocommerce/blob/360d9bc0f5709e6cf13c646860360fca9968ebb0/plugins/woocommerce/client/blocks/assets/js/types/type-defs/cart.ts
interface CartTotals {
	total_price: string;
	currency_code: string;
	currency_minor_unit: number;
}

interface Cart {
	extensions: any;
	cartItems: any;
	cartTotals: CartTotals;
}

/**
 * Gets the effective total price for Stripe initialization.
 * Uses the wcpay.express-checkout.total-amount filter to allow modifications
 * (e.g., for trial subscriptions with $0 initial payment).
 *
 * @param cart The cart object from WC Blocks.
 * @return The total price to use for Stripe.
 */
const getEffectiveTotalPrice = ( cart: Cart ): string => {
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
	) as number;

	return String( filteredTotal );
};

type PaymentMethod = keyof AvailablePaymentMethods;

type MemoizedAvailabilityFunction = (
	totalPrice: string,
	currencyCode: string
) => Promise< boolean >;

const checkPaymentMethodIsAvailableInternal = (
	paymentMethod: PaymentMethod,
	totalPrice: string,
	currencyCode: string,
	api: WCPayAPI
): Promise< boolean > => {
	// Guard against empty currency code during WooCommerce Blocks store
	// hydration. The cart store initialises with currency_code: '' before
	// server-side preloaded data is applied. Passing an empty string to
	// Stripe Elements throws: "Invalid value for elements(): currency should
	// be one of ...". Returning false here lets WC Blocks re-evaluate once
	// the cart data (and currency) is properly loaded.
	if ( ! currencyCode ) {
		return Promise.resolve( false );
	}

	return new Promise( ( resolve ) => {
		const bodyElement = document.querySelector( 'body' );
		if ( ! bodyElement ) {
			return resolve( false );
		}

		// Create the DIV container on the fly
		const containerEl = document.createElement( 'div' );

		// Ensure the element is hidden and doesn't interfere with the page layout.
		containerEl.style.display = 'none';

		bodyElement.appendChild( containerEl );

		const useConfirmationToken =
			getExpressCheckoutData( 'flags' )?.isEceUsingConfirmationTokens ??
			true;
		const paymentMethodTypes = [
			[ 'applePay', 'googlePay' ].includes( paymentMethod ) && 'card',
			paymentMethod === 'amazonPay' && 'amazon_pay',
		].filter( Boolean ) as string[];

		const root = createRoot( containerEl );
		root.render(
			<Elements
				stripe={ api.loadStripeForExpressCheckout() }
				options={ {
					mode: getStripeElementsMode(),
					...( useConfirmationToken
						? { paymentMethodTypes }
						: { paymentMethodCreation: 'manual' } ),
					amount: Number( totalPrice ),
					currency: currencyCode.toLowerCase(),
				} }
			>
				<ExpressCheckoutElement
					onConfirm={ () => null }
					onLoadError={ () => {
						resolve( false );
						root.unmount();
						containerEl.remove();
					} }
					options={ getPaymentMethodsOverride( paymentMethod ) }
					onReady={ ( { availablePaymentMethods } ) => {
						resolve(
							Boolean(
								availablePaymentMethods?.[ paymentMethod ]
							)
						);
						root.unmount();
						containerEl.remove();
					} }
				/>
			</Elements>
		);
	} );
};

// we want to ensure we memoize the availability checks by payment method.
// this is a cache of checks done by payment method.
const paymentMethodAvailabilityFunctions: Record<
	string,
	MemoizedAvailabilityFunction
> = {};

export const checkPaymentMethodIsAvailable = (
	paymentMethod: PaymentMethod,
	cart: Cart,
	api: WCPayAPI
): Promise< boolean > => {
	// if totals change, we need to calculate a new function result.
	// but if this `checkPaymentMethodIsAvailable` function is called with the same payment method and the same totals,
	// we can just return the memoized value from a previous execution.
	let memoizedFn = paymentMethodAvailabilityFunctions[ paymentMethod ];
	if ( ! memoizedFn ) {
		memoizedFn = memoize(
			( totalPrice, currencyCode ) =>
				checkPaymentMethodIsAvailableInternal(
					paymentMethod,
					totalPrice,
					currencyCode,
					api
				),
			( totalPrice, currencyCode ) => `${ totalPrice }-${ currencyCode }`
		);
		paymentMethodAvailabilityFunctions[ paymentMethod ] = memoizedFn;
	}

	return memoizedFn(
		// Use effective total price to handle trial subscriptions with $0 initial payment
		getEffectiveTotalPrice( cart ),
		cart.cartTotals.currency_code
	);
};
