/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import EmbeddedOnboarding from '../embedded-onboarding';
import { OnboardingContextProvider } from '../../context';

jest.mock( '@wordpress/api-fetch' );
jest.mock( '@stripe/connect-js', () => ( {
	loadConnectAndInitialize: jest.fn(),
} ) );

const mockApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

describe( 'EmbeddedOnboarding', () => {
	beforeEach( () => {
		jest.clearAllMocks(); // Clear all mocks between tests
	} );

	it( 'should show error if account session fails', async () => {
		mockApiFetch.mockResolvedValueOnce( { success: false } );

		render(
			<OnboardingContextProvider>
				<EmbeddedOnboarding />
			</OnboardingContextProvider>
		);

		await waitFor( () =>
			expect(
				screen.getByText( /Failed to create account session/i )
			).toBeInTheDocument()
		);
	} );
} );
