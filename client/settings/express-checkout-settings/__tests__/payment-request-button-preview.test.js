/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render as baseRender, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentRequestButtonPreview from '../payment-request-button-preview';

jest.mock( '@wordpress/a11y', () => ( {
	...jest.requireActual( '@wordpress/a11y' ),
	speak: jest.fn(),
} ) );

jest.mock(
	'wcpay/express-checkout/blocks/components/express-checkout-preview',
	() => ( {
		ExpressCheckoutPreviewComponent: () => (
			<button type="submit">Stripe button mock</button>
		),
	} )
);

jest.mock( '@stripe/react-stripe-js', () => ( {
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
	let location;
	const mockHttpsLocation = new URL( 'https://example.com' );

	beforeEach( () => {
		// We need the preview component to think we're rendering on a HTTPS enabled page
		// so the buttons are rendered.
		location = global.location;
		delete global.location;
		global.location = mockHttpsLocation;
	} );

	afterEach( () => {
		jest.clearAllMocks();
		Object.defineProperty( window, 'location', {
			configurable: true,
			value: location,
		} );
	} );

	it( 'displays the button preview', async () => {
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
