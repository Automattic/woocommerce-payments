/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render as baseRender, screen } from '@testing-library/react';
import { useStripe } from '@stripe/react-stripe-js';

/**
 * Internal dependencies
 */
import PaymentRequestButtonPreview from '../payment-request-button-preview';
import { shouldUseGooglePayBrand } from 'utils/express-checkout';

jest.mock( '@wordpress/a11y', () => ( {
	...jest.requireActual( '@wordpress/a11y' ),
	speak: jest.fn(),
} ) );

jest.mock( 'utils/express-checkout', () => ( {
	shouldUseGooglePayBrand: jest.fn(),
} ) );

jest.mock( '@stripe/react-stripe-js', () => ( {
	PaymentRequestButtonElement: jest.fn( () => (
		<button type="submit">Stripe button mock</button>
	) ),
	useStripe: jest.fn(),
} ) );

jest.mock( 'wcpay/data', () => {
	const actual = jest.requireActual( 'wcpay/data' );
	return {
		__esModule: true,
		...actual,
		useWooPayEnabledSettings: () => [ false, jest.fn() ],
		usePaymentRequestEnabledSettings: () => [ true, jest.fn() ],
	};
} );

const render = ( ui, options ) =>
	baseRender( ui, {
		wrapper: ( { children } ) => (
			<div
				id="wcpay-express-checkout-settings-container"
				data-method-id="payment_request"
			>
				{ children }
			</div>
		),
		...options,
	} );

describe( 'PaymentRequestButtonPreview', () => {
	const canMakePaymentMock = jest.fn();

	let location;
	const mockHttpsLocation = new URL( 'https://example.com' );

	beforeEach( () => {
		// We need the preview component to think we're rendering on a HTTPS enabled page
		// so the buttons are rendered.
		location = global.location;
		delete global.location;
		global.location = mockHttpsLocation;

		shouldUseGooglePayBrand.mockReturnValue( true );
		useStripe.mockReturnValue( {
			paymentRequest: () => ( {
				canMakePayment: canMakePaymentMock,
			} ),
		} );
		canMakePaymentMock.mockResolvedValue( {} );
	} );

	afterEach( () => {
		jest.clearAllMocks();
		Object.defineProperty( window, 'location', {
			configurable: true,
			value: location,
		} );
	} );

	it( 'displays Google Chrome and Google Pay when page is in Safari', async () => {
		shouldUseGooglePayBrand.mockReturnValue( false );

		render( <PaymentRequestButtonPreview /> );

		expect(
			await screen.findByText( 'Stripe button mock' )
		).toBeInTheDocument();
		expect(
			screen.queryByText( /Safari/, {
				ignore: '.a11y-speak-region',
			} )
		).not.toBeInTheDocument();
	} );

	it( 'displays Safari Apple Pay when page is in Google Chrome', async () => {
		shouldUseGooglePayBrand.mockReturnValue( true );

		render( <PaymentRequestButtonPreview /> );

		expect(
			await screen.findByText( 'Stripe button mock' )
		).toBeInTheDocument();
		expect(
			screen.queryByText( /Chrome/, {
				ignore: '.a11y-speak-region',
			} )
		).not.toBeInTheDocument();
	} );

	it( 'does not display anything if stripe is falsy', () => {
		useStripe.mockReturnValue( null );

		render( <PaymentRequestButtonPreview /> );

		expect(
			screen.queryByText( 'Stripe button mock' )
		).not.toBeInTheDocument();
	} );

	it( 'displays an info notice if stripe fails to load', async () => {
		canMakePaymentMock.mockResolvedValue( null );
		render( <PaymentRequestButtonPreview /> );

		expect(
			await screen.findByText(
				/To preview the express checkout buttons, ensure your store uses/,
				{
					ignore: '.a11y-speak-region',
				}
			)
		).toBeInTheDocument();
		expect(
			screen.queryByText( 'Stripe button mock' )
		).not.toBeInTheDocument();
	} );

	it( 'displays the payment button when stripe is loaded', async () => {
		render( <PaymentRequestButtonPreview /> );

		expect(
			await screen.findByText( 'Stripe button mock' )
		).toBeInTheDocument();
		expect(
			screen.queryByText(
				/To preview the express checkout buttons, ensure your store uses/,
				{
					ignore: '.a11y-speak-region',
				}
			)
		).not.toBeInTheDocument();
	} );
} );
