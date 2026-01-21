/**
 * External dependencies
 */
import React from 'react';
import { createRoot } from 'react-dom/client';
import { ExpressCheckoutElement, Elements } from '@stripe/react-stripe-js';
import type { AvailablePaymentMethods } from '@stripe/stripe-js';
import { memoize } from 'lodash';

/**
 * Internal dependencies
 */
import type WCPayAPI from 'wcpay/checkout/api';
import { getExpressCheckoutData } from '.';

// types from https://github.com/woocommerce/woocommerce/blob/360d9bc0f5709e6cf13c646860360fca9968ebb0/plugins/woocommerce/client/blocks/assets/js/types/type-defs/cart.ts
interface CartTotals {
	total_price: string;
	currency_code: string;
}

interface Cart {
	cartTotals: CartTotals;
}

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
		const root = createRoot( containerEl );
		root.render(
			<Elements
				stripe={ api.loadStripeForExpressCheckout() }
				options={ {
					mode: 'payment',
					...( useConfirmationToken
						? { paymentMethodTypes: [ 'card' ] }
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
					options={ {
						paymentMethods: {
							applePay:
								paymentMethod === 'applePay'
									? 'always'
									: 'never',
							googlePay:
								paymentMethod === 'googlePay'
									? 'always'
									: 'never',
							amazonPay: 'never',
							link: 'never',
							paypal: 'never',
							klarna: 'never',
						},
					} }
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
		cart.cartTotals.total_price,
		cart.cartTotals.currency_code
	);
};
