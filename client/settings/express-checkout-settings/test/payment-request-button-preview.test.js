/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import { useStripe } from '@stripe/react-stripe-js';

/**
 * Internal dependencies
 */
import PaymentRequestButtonPreview from '../payment-request-button-preview';
import { shouldUseGooglePayBrand } from 'payment-request/utils';

jest.mock( '@wordpress/a11y', () => ( {
	...jest.requireActual( '@wordpress/a11y' ),
	speak: jest.fn(),
} ) );

jest.mock( 'payment-request/utils', () => ( {
	shouldUseGooglePayBrand: jest.fn(),
} ) );

jest.mock( '@stripe/react-stripe-js', () => ( {
	PaymentRequestButtonElement: jest
		.fn()
		.mockReturnValue( <button type="submit">Stripe button mock</button> ),
	useStripe: jest.fn(),
} ) );

describe( 'PaymentRequestButtonPreview', () => {
	const canMakePaymentMock = jest.fn();

	beforeEach( () => {
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
	} );

	it( 'displays Google Chrome and Google Pay when page is in Safari', async () => {
		shouldUseGooglePayBrand.mockReturnValue( false );

		render( <PaymentRequestButtonPreview /> );

		expect(
			await screen.findByText(
				'To preview the Google Pay button, view this page in the Google Chrome browser.'
			)
		).toBeInTheDocument();
		expect( screen.queryByText( /Safari/ ) ).not.toBeInTheDocument();
	} );

	it( 'displays Safari Apple Pay when page is in Google Chrome', async () => {
		shouldUseGooglePayBrand.mockReturnValue( true );

		render( <PaymentRequestButtonPreview /> );

		expect(
			await screen.findByText(
				'To preview the Apple Pay button, view this page in the Safari browser.'
			)
		).toBeInTheDocument();
		expect( screen.queryByText( /Google Chrome/ ) ).not.toBeInTheDocument();
	} );

	it( 'does not display anything if stripe is falsy', () => {
		useStripe.mockReturnValue( null );

		const { container } = render( <PaymentRequestButtonPreview /> );

		expect(
			screen.queryByText( 'Stripe button mock' )
		).not.toBeInTheDocument();
		expect( container.firstChild ).toBeNull();
	} );

	it( 'displays an info notice if stripe fails to load', async () => {
		canMakePaymentMock.mockResolvedValue( null );
		render( <PaymentRequestButtonPreview /> );

		expect(
			await screen.findByText(
				/To preview the buttons, ensure your device is configured/
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
			screen.queryByText( /ensure your device is configured/ )
		).not.toBeInTheDocument();
	} );
} );
