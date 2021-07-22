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

		render(
			<PaymentRequestButtonPreview
				buttonType={ 'default' }
				size={ 'default' }
				theme={ 'light' }
			/>
		);

		expect(
			screen.getByText(
				'To preview the Apple Pay button, view this page in the Safari browser.'
			)
		).toBeInTheDocument();
	} );

	it( 'displays Safari Apple Pay when page is in Google Chrome', () => {
		shouldUseGooglePayBrand.mockReturnValue( false );

		render(
			<PaymentRequestButtonPreview
				buttonType={ 'default' }
				size={ 'default' }
				theme={ 'light' }
			/>
		);

		expect(
			screen.getByText(
				'To preview the Google Pay button, view this page in the Google Chrome browser.'
			)
		).toBeInTheDocument();
	} );
} );
