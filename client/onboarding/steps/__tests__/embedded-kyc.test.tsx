/**
 * Internal dependencies
 */
import EmbeddedKyc from '../embedded-kyc';
import { useOnboardingContext } from 'wcpay/onboarding/context';
import { isInDevMode } from 'wcpay/utils';

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

jest.mock( 'wcpay/utils', () => ( {
	isInDevMode: jest.fn( () => false ),
	getConnectUrl: jest.fn().mockReturnValue( 'https://example.com/connect' ),
	getOverviewUrl: jest.fn().mockReturnValue( 'https://example.com/overview' ),
} ) );

const mockIsInDevMode = isInDevMode as jest.MockedFunction<
	typeof isInDevMode
>;

const mockOnboardingContext = () => {
	( useOnboardingContext as jest.Mock ).mockReturnValue( {
		data: {},
		setData: jest.fn(),
		errors: {},
		setErrors: jest.fn(),
		touched: {},
		setTouched: jest.fn(),
	} );
};

describe( 'EmbeddedKyc Component', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		mockIsInDevMode.mockReturnValue( false );
	} );

	it( 'renders the EmbeddedAccountOnboarding component when not finalizing', async () => {
		mockOnboardingContext();

		await act( async () => {
			render( <EmbeddedKyc /> );
		} );

		await waitFor( async () => {
			expect(
				await screen.findByTestId( 'embedded-account-onboarding' )
			).toBeInTheDocument();
		} );
	} );

	it( 'shows dev mode warning banner when dev mode is active', async () => {
		mockOnboardingContext();
		mockIsInDevMode.mockReturnValue( true );

		let container: HTMLElement;
		await act( async () => {
			const result = render( <EmbeddedKyc /> );
			container = result.container;
		} );

		expect( container!.textContent ).toContain(
			'Your store is in development mode.'
		);
		expect( container!.textContent ).toContain(
			'can only create test accounts'
		);
	} );

	it( 'does not show dev mode warning when dev mode is not active', async () => {
		mockOnboardingContext();
		mockIsInDevMode.mockReturnValue( false );

		let container: HTMLElement;
		await act( async () => {
			const result = render( <EmbeddedKyc /> );
			container = result.container;
		} );

		expect( container!.textContent ).not.toContain(
			'Your store is in development mode.'
		);
	} );
} );
