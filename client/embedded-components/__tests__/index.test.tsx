/**
 * Internal dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * External dependencies
 */
import {
	EmbeddedAccountOnboarding,
	EmbeddedConnectNotificationBanner,
} from 'wcpay/embedded-components';

// Mock dependencies
jest.mock( '@stripe/connect-js', () => ( {
	loadConnectAndInitialize: jest.fn( () => ( {
		on: jest.fn(),
		off: jest.fn(),
		destroy: jest.fn(),
	} ) ),
} ) );
jest.mock( '@stripe/react-connect-js', () => ( {
	ConnectComponentsProvider: ( {
		children,
	}: {
		children: React.ReactNode;
	} ) => <>{ children }</>,
	ConnectAccountOnboarding: jest.fn( () => (
		<div data-testid="connect-account-onboarding">Stripe Onboarding</div>
	) ),
	ConnectNotificationBanner: () => (
		<div data-testid="connect-notification-banner">Stripe Notification</div>
	),
} ) );

jest.mock( '../hooks', () => ( {
	createKycAccountSession: jest.fn().mockResolvedValue( {
		clientSecret: 'test-secret',
		publishableKey: 'test-key',
		locale: 'en_US',
	} ),
	createAccountSession: jest.fn().mockResolvedValue( {
		clientSecret: 'test-secret',
		publishableKey: 'test-key',
		locale: 'en_US',
	} ),
} ) );

// Mock onboarding data
const mockOnboardingData = {
	businessType: 'individual',
	country: 'US',
};

// Tests for EmbeddedAccountOnboarding
describe( 'EmbeddedAccountOnboarding', () => {
	it( 'renders ConnectAccountOnboarding after initialization', async () => {
		const mockOnExit = jest.fn();
		const mockOnStepChange = jest.fn();

		render(
			<EmbeddedAccountOnboarding
				onboardingData={ mockOnboardingData }
				onExit={ mockOnExit }
				collectPayoutRequirements={ false }
				onStepChange={ mockOnStepChange }
			/>
		);

		expect(
			await screen.findByTestId( 'connect-account-onboarding' )
		).toBeInTheDocument();
		expect( mockOnExit ).not.toHaveBeenCalled();
		expect( mockOnStepChange ).not.toHaveBeenCalled();
	} );

	it( 'passes correct props to ConnectAccountOnboarding', async () => {
		const mockOnExit = jest.fn();
		const mockOnStepChange = jest.fn();

		render(
			<EmbeddedAccountOnboarding
				onboardingData={ mockOnboardingData }
				onExit={ mockOnExit }
				collectPayoutRequirements={ true }
				onStepChange={ mockOnStepChange }
			/>
		);

		await screen.findByTestId( 'connect-account-onboarding' );
		const { ConnectAccountOnboarding } = jest.requireMock(
			'@stripe/react-connect-js'
		);
		const props = ConnectAccountOnboarding.mock.lastCall[ 0 ];
		expect( props.collectionOptions ).toEqual( {
			fields: 'eventually_due',
			futureRequirements: 'omit',
		} );
		expect( props.onExit ).toBe( mockOnExit );

		props.onStepChange( { step: 'stripe_user_authentication' } );
		expect( mockOnStepChange ).toHaveBeenCalledWith(
			'stripe_user_authentication'
		);
	} );
} );

// Tests for EmbeddedConnectNotificationBanner
describe( 'EmbeddedConnectNotificationBanner', () => {
	it( 'renders ConnectNotificationBanner after initialization', async () => {
		render(
			<EmbeddedConnectNotificationBanner
				onNotificationsChange={ jest.fn() }
			/>
		);
		expect(
			await screen.findByTestId( 'connect-notification-banner' )
		).toBeInTheDocument();
	} );
} );
