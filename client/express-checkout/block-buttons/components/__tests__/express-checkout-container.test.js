/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ExpressCheckoutContainer from '../express-checkout-container';

let mockCartData;
let mockElementsProps;

jest.mock( '@stripe/react-stripe-js', () => ( {
	Elements: jest.fn( ( props ) => {
		mockElementsProps = props;
		return props.children;
	} ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useSelect: ( callback ) =>
		callback( () => ( {
			getCartData: () => mockCartData,
		} ) ),
} ) );

jest.mock( '../express-checkout-component', () => () => (
	<div data-testid="express-checkout-component" />
) );

const getBaseProps = () => ( {
	api: {
		loadStripeForExpressCheckout: jest.fn().mockResolvedValue( {} ),
	},
	billing: {
		cartTotal: {
			value: 2399,
		},
		currency: {
			code: 'USD',
			minorUnit: 2,
		},
	},
	buttonAttributes: {},
} );

describe( 'ExpressCheckoutContainer', () => {
	beforeEach( () => {
		mockElementsProps = undefined;
		mockCartData = {
			items: [],
			extensions: {},
		};
		window.wcpayExpressCheckoutParams = {
			checkout: {
				stripe_minor_unit: 2,
			},
			flags: {
				isEceUsingConfirmationTokens: true,
			},
			enabled_methods: [ 'payment_request' ],
			stripe: {
				locale: 'en',
			},
		};
	} );

	it( 'passes null setupFutureUsage when the live cart has no subscription', () => {
		render( <ExpressCheckoutContainer { ...getBaseProps() } /> );

		expect( mockElementsProps.options ).toEqual(
			expect.objectContaining( {
				setupFutureUsage: null,
			} )
		);
	} );

	it( 'passes off_session setupFutureUsage when the live cart contains a subscription', () => {
		mockCartData = {
			items: [],
			extensions: {
				subscriptions: [
					{
						billing_period: 'month',
						billing_interval: 1,
						totals: { total_price: '2399' },
					},
				],
			},
		};

		render( <ExpressCheckoutContainer { ...getBaseProps() } /> );

		expect( mockElementsProps.options ).toEqual(
			expect.objectContaining( {
				setupFutureUsage: 'off_session',
			} )
		);
	} );
} );
