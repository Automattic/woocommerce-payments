/**
 * Internal dependencies
 */
import EmbeddedKyc from '../embedded-kyc';
import { useOnboardingContext } from 'wcpay/onboarding/context';

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor, act } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock( 'wcpay/embedded-components', () => {
	return {
		EmbeddedAccountOnboarding: () => (
			<div data-testid="embedded-account-onboarding" />
		),
	};
} );
jest.mock( 'wcpay/onboarding/context', () => ( {
	useOnboardingContext: jest.fn(),
} ) );

jest.mock( 'wcpay/onboarding/utils', () => ( {
	finalizeOnboarding: jest.fn(),
} ) );

describe( 'EmbeddedKyc Component', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'renders the EmbeddedAccountOnboarding component when not finalizing', async () => {
		( useOnboardingContext as jest.Mock ).mockReturnValue( {
			data: {},
			setData: jest.fn(),
			errors: {},
			setErrors: jest.fn(),
			touched: {},
			setTouched: jest.fn(),
		} );

		await act( async () => {
			render( <EmbeddedKyc /> );
		} );

		await waitFor( async () => {
			expect(
				await screen.findByTestId( 'embedded-account-onboarding' )
			).toBeInTheDocument();
		} );
	} );
} );
