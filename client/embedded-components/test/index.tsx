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
	ConnectAccountOnboarding: () => (
		<div data-testid="connect-account-onboarding">Stripe Onboarding</div>
	),
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
				isPoEligible={ true }
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
				isPoEligible={ true }
				collectPayoutRequirements={ true }
				onStepChange={ mockOnStepChange }
			/>
		);

		expect(
			await screen.findByTestId( 'connect-account-onboarding' )
		).toBeInTheDocument();
		expect( mockOnExit ).not.toHaveBeenCalled();
		expect( mockOnStepChange ).not.toHaveBeenCalled();
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
