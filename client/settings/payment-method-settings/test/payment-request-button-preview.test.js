/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PaymentRequestButtonPreview from '../payment-request-button-preview';
import { shouldUseGooglePayBrand } from 'payment-request/utils';

// Setup mocks for modules
jest.mock( 'payment-request/utils', () => ( {
	getPaymentRequestData: jest.fn().mockReturnValue( {
		publishableKey: '123',
		accountId: '0001',
		locale: 'en',
	} ),
	shouldUseGooglePayBrand: jest.fn(),
} ) );

jest.mock( '../payment-request-demo-button' );

jest.mock( '@stripe/stripe-js', () => ( {
	loadStripe: jest.fn().mockResolvedValue( true ),
} ) );

jest.mock( '@stripe/react-stripe-js', () => ( {
	Elements: jest.fn().mockImplementation( () => <></> ),
} ) );

describe( 'PaymentRequestButtonPreview', () => {
	it( 'displays Google Chrome and Google Pay when page is in Safari', () => {
		shouldUseGooglePayBrand.mockReturnValue( true );

		render( <PaymentRequestButtonPreview /> );

		expect(
			screen.getByText(
				'To preview the Apple Pay button, view this page in the Safari browser.'
			)
		).toBeInTheDocument();
	} );

	it( 'displays Safari Apple Pay when page is in Google Chrome', () => {
		shouldUseGooglePayBrand.mockReturnValue( false );

		render( <PaymentRequestButtonPreview /> );

		expect(
			screen.getByText(
				'To preview the Google Pay button, view this page in the Google Chrome browser.'
			)
		).toBeInTheDocument();
	} );

	it( 'displays info notice if Stripe fails to render both Apple Pay and Google Pay', () => {
		shouldUseGooglePayBrand.mockReturnValue( false );

		render(
			<PaymentRequestButtonPreview
				isLoading={ false }
				paymentRequest={ false }
			/>
		);

		expect(
			screen.getAllByText(
				'To preview the buttons, ensure your device is configured to accept Apple Pay, \
			or Google Pay, and view this page using the Safari or Chrome browsers.'
			)[ 0 ]
		).toBeInTheDocument();
	} );
} );
