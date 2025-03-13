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
	} ),
	createAccountSession: jest.fn().mockResolvedValue( {
		clientSecret: 'test-secret',
		publishableKey: 'test-key',
	} ),
} ) );

// Tests for EmbeddedAccountOnboarding
describe( 'EmbeddedAccountOnboarding', () => {
	it( 'renders ConnectAccountOnboarding after initialization', async () => {
		render( <EmbeddedAccountOnboarding onExit={ jest.fn() } /> );
		expect(
			await screen.findByTestId( 'connect-account-onboarding' )
		).toBeInTheDocument();
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
