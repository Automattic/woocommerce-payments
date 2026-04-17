/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import ExpressCheckoutContainer from '../express-checkout-container';

const mockElements = jest.fn( ( { children } ) => (
	<div data-testid="mock-elements">{ children }</div>
) );
const mockGetExpressCheckoutData = jest.fn();

jest.mock( '@stripe/react-stripe-js', () => ( {
	Elements: ( props ) => mockElements( props ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	select: jest.fn( () => ( {
		getCartData: jest.fn(),
	} ) ),
} ) );

jest.mock( '@wordpress/hooks', () => ( {
	applyFilters: jest.fn( ( _, value ) => value ),
} ) );

jest.mock( '../../../utils', () => ( {
	getExpressCheckoutData: ( key ) => mockGetExpressCheckoutData( key ),
	getExpressCheckoutButtonAppearance: jest.fn( () => ( { theme: 'dark' } ) ),
} ) );

jest.mock( '../express-checkout-component', () => () => (
	<div data-testid="express-checkout-component" />
) );

describe( 'ExpressCheckoutContainer', () => {
	const api = {
		loadStripeForExpressCheckout: jest.fn().mockResolvedValue( {} ),
	};
	const billing = {
		cartTotal: {
			value: 1000,
		},
		currency: {
			code: 'usd',
			minorUnit: 2,
		},
	};

	beforeEach( () => {
		jest.clearAllMocks();
		mockGetExpressCheckoutData.mockImplementation( ( key ) => {
			const data = {
				flags: { isEceUsingConfirmationTokens: true },
				is_manual_capture: false,
				enabled_methods: [ 'payment_request' ],
				stripe: { locale: 'en' },
			};

			return data[ key ];
		} );
	} );

	it( 'passes setupFutureUsage when has_recurring_items is true', () => {
		mockGetExpressCheckoutData.mockImplementation( ( key ) => {
			const data = {
				flags: { isEceUsingConfirmationTokens: true },
				is_manual_capture: false,
				has_recurring_items: true,
				enabled_methods: [ 'payment_request' ],
				stripe: { locale: 'en' },
			};

			return data[ key ];
		} );

		render(
			<ExpressCheckoutContainer
				api={ api }
				billing={ billing }
				buttonAttributes={ {} }
			/>
		);

		expect( mockElements ).toHaveBeenCalledWith(
			expect.objectContaining( {
				options: expect.objectContaining( {
					setupFutureUsage: 'off_session',
				} ),
			} )
		);
	} );

	it( 'falls back to has_subscription when has_recurring_items is absent', () => {
		mockGetExpressCheckoutData.mockImplementation( ( key ) => {
			const data = {
				flags: { isEceUsingConfirmationTokens: true },
				is_manual_capture: false,
				has_subscription: true,
				enabled_methods: [ 'payment_request' ],
				stripe: { locale: 'en' },
			};

			return data[ key ];
		} );

		render(
			<ExpressCheckoutContainer
				api={ api }
				billing={ billing }
				buttonAttributes={ {} }
			/>
		);

		expect( mockElements ).toHaveBeenCalledWith(
			expect.objectContaining( {
				options: expect.objectContaining( {
					setupFutureUsage: 'off_session',
				} ),
			} )
		);
	} );

	it( 'omits setupFutureUsage when neither has_recurring_items nor has_subscription is true', () => {
		mockGetExpressCheckoutData.mockImplementation( ( key ) => {
			const data = {
				flags: { isEceUsingConfirmationTokens: true },
				is_manual_capture: false,
				has_recurring_items: false,
				has_subscription: false,
				enabled_methods: [ 'payment_request' ],
				stripe: { locale: 'en' },
			};

			return data[ key ];
		} );

		render(
			<ExpressCheckoutContainer
				api={ api }
				billing={ billing }
				buttonAttributes={ {} }
			/>
		);

		expect( mockElements ).toHaveBeenCalledWith(
			expect.objectContaining( {
				options: expect.not.objectContaining( {
					setupFutureUsage: expect.anything(),
				} ),
			} )
		);
	} );

	it( 'invokes wcpay.express-checkout.total-amount filter with amount and cart data', () => {
		mockGetExpressCheckoutData.mockImplementation( ( key ) => {
			const data = {
				flags: { isEceUsingConfirmationTokens: true },
				is_manual_capture: false,
				enabled_methods: [ 'payment_request' ],
				stripe: { locale: 'en' },
			};

			return data[ key ];
		} );

		render(
			<ExpressCheckoutContainer
				api={ api }
				billing={ billing }
				buttonAttributes={ {} }
			/>
		);

		expect( applyFilters ).toHaveBeenCalledWith(
			'wcpay.express-checkout.total-amount',
			1000,
			undefined
		);
	} );
} );
