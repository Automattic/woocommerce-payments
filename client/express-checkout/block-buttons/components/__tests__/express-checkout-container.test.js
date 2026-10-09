/**
 * External dependencies
 */
import { act, render } from '@testing-library/react';

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

let mockComponentMounts;
let mockComponentProps;

jest.mock( '../express-checkout-component', () => {
	const { useEffect } = jest.requireActual( 'react' );
	return ( props ) => {
		mockComponentProps = props;
		useEffect( () => {
			mockComponentMounts++;
		}, [] );
		return <div data-testid="express-checkout-component" />;
	};
} );

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
		mockComponentMounts = 0;
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

	it( 'passes null setupFutureUsage when the server declared none', () => {
		render( <ExpressCheckoutContainer { ...getBaseProps() } /> );

		expect( mockElementsProps.options ).toEqual(
			expect.objectContaining( {
				setupFutureUsage: null,
			} )
		);
	} );

	it( 'passes off_session setupFutureUsage when the server declared it', () => {
		mockCartData = {
			items: [],
			extensions: {
				wcpay: { setup_future_usage: 'off_session' },
			},
		};

		render( <ExpressCheckoutContainer { ...getBaseProps() } /> );

		expect( mockElementsProps.options ).toEqual(
			expect.objectContaining( {
				setupFutureUsage: 'off_session',
			} )
		);
	} );

	it( 'keeps the same Elements group when only the amount changes', () => {
		const props = getBaseProps();
		const { rerender } = render(
			<ExpressCheckoutContainer { ...props } />
		);

		rerender(
			<ExpressCheckoutContainer
				{ ...props }
				billing={ { ...props.billing, cartTotal: { value: 4599 } } }
			/>
		);

		expect( mockComponentMounts ).toBe( 1 );
		expect( mockElementsProps.options.amount ).toBe( 4599 );
	} );

	it( 'starts a new Elements group when the currency changes', () => {
		const props = getBaseProps();
		const { rerender } = render(
			<ExpressCheckoutContainer { ...props } />
		);

		rerender(
			<ExpressCheckoutContainer
				{ ...props }
				billing={ {
					...props.billing,
					currency: { code: 'EUR', minorUnit: 2 },
				} }
			/>
		);

		expect( mockComponentMounts ).toBe( 2 );
		expect( mockElementsProps.options.currency ).toBe( 'eur' );
	} );

	const reportAvailability = ( isAvailable ) =>
		act( () => {
			mockComponentProps.onAvailabilityChange( isAvailable );
		} );

	it( 'hides the slot when the wallet is unavailable, and shows it again when it becomes available', () => {
		const { container } = render(
			<ExpressCheckoutContainer { ...getBaseProps() } />
		);

		reportAvailability( false );
		expect( container.firstChild.hidden ).toBe( true );

		reportAvailability( true );
		expect( container.firstChild.hidden ).toBe( false );
	} );

	it( 'keeps an unavailable wallet hidden while the new group loads after a currency change', () => {
		const props = getBaseProps();
		const { container, rerender } = render(
			<ExpressCheckoutContainer { ...props } />
		);

		reportAvailability( false );
		rerender(
			<ExpressCheckoutContainer
				{ ...props }
				billing={ {
					...props.billing,
					currency: { code: 'EUR', minorUnit: 2 },
				} }
			/>
		);

		expect( mockComponentMounts ).toBe( 2 );
		expect( container.firstChild.hidden ).toBe( true );
	} );
} );
