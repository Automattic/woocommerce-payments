/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { loadConnectAndInitialize } from '@stripe/connect-js';

/**
 * Internal dependencies
 */
import EmbeddedKyc from '../embedded-kyc';

jest.mock( '@wordpress/api-fetch' );
jest.mock( '@stripe/connect-js', () => ( {
	loadConnectAndInitialize: jest.fn(),
} ) );

// Mock data, setData from OnboardingContext
const data = {
	country: 'US',
	business_type: 'individual',
	mcc: 'most_popular__software_services',
	annual_revenue: 'less_than_250k',
	go_live_timeframe: 'within_1month',
};
const setData = jest.fn();

jest.mock( '../../context', () => ( {
	useOnboardingContext: jest.fn( () => ( {
		data,
		setData,
	} ) ),
} ) );

jest.mock( 'components/stepper', () => ( {
	useStepperContext: jest.fn( () => ( {
		currentStep: 'loading',
	} ) ),
} ) );

describe( 'EmbeddedOnboarding', () => {
	beforeEach( () => {
		jest.clearAllMocks(); // Clear all mocks between tests
	} );

	it( 'should show error if account session fails', async () => {
		jest.mocked( apiFetch ).mockResolvedValueOnce( {
			result: 'eligible',
			data: [],
		} );

		jest.mocked( apiFetch ).mockResolvedValueOnce( {
			success: false,
		} );

		render( <EmbeddedKyc /> );

		await waitFor( () =>
			expect(
				screen.getByText( /Failed to create account session/i )
			).toBeInTheDocument()
		);
	} );

	it( 'should initialize Stripe Connect when publishableKey and clientSecret are set', async () => {
		jest.mocked( apiFetch ).mockResolvedValueOnce( {
			result: 'eligible',
			data: [],
		} );

		const mockAccountSessionData = {
			publishableKey: 'test_publishable_key',
			clientSecret: 'test_client_secret',
			locale: 'en_US',
		};
		jest.mocked( apiFetch ).mockResolvedValueOnce( mockAccountSessionData );

		render( <EmbeddedKyc /> );

		await waitFor( () =>
			expect( loadConnectAndInitialize ).toHaveBeenCalledWith( {
				publishableKey: mockAccountSessionData.publishableKey,
				fetchClientSecret: expect.any( Function ),
				appearance: {
					overlays: 'drawer',
					variables: expect.any( Object ),
				},
				locale: 'en-US', // Locale should be formatted correctly
			} )
		);
	} );
} );
