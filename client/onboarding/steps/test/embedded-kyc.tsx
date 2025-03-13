/**
 * Internal dependencies
 */
import EmbeddedKyc from '../embedded-kyc';
import { useOnboardingContext } from 'wcpay/onboarding/context';
import { isPoEligible } from 'wcpay/onboarding/utils';

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock( 'wcpay/onboarding/context', () => ( {
	useOnboardingContext: jest.fn(),
} ) );

jest.mock( 'wcpay/onboarding/utils', () => ( {
	isPoEligible: jest.fn(),
	finalizeOnboarding: jest.fn(),
} ) );

describe( 'EmbeddedKyc Component', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'renders the EmbeddedAccountOnboarding component when not finalizing', async () => {
		( useOnboardingContext as jest.Mock ).mockReturnValue( { data: {} } );
		( isPoEligible as jest.Mock ).mockResolvedValueOnce( true );

		render( <EmbeddedKyc /> );

		await waitFor( () => {
			expect(
				screen.getByTestId( 'embedded-account-onboarding' )
			).toBeInTheDocument();
		} );
	} );
} );
