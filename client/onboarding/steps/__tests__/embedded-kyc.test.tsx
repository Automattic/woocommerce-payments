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
import { render, screen, waitFor } from '@testing-library/react';
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

declare const global: {
	wcpaySettings: { devModeReason?: string };
};

const devModeReason =
	'A plugin or custom code turned it on using the wcpay_dev_mode filter.';

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

	afterEach( () => {
		delete global.wcpaySettings.devModeReason;
	} );

	it( 'renders the EmbeddedAccountOnboarding component when not finalizing', async () => {
		mockOnboardingContext();

		render( <EmbeddedKyc /> );

		await waitFor( async () => {
			expect(
				await screen.findByTestId( 'embedded-account-onboarding' )
			).toBeInTheDocument();
		} );
	} );

	it( 'shows dev mode warning banner when dev mode is active', async () => {
		mockOnboardingContext();
		mockIsInDevMode.mockReturnValue( true );
		global.wcpaySettings.devModeReason = devModeReason;

		const { container } = render( <EmbeddedKyc /> );

		expect( container ).toHaveTextContent(
			'Your store is in development mode, so payments are simulated. ' +
				'A plugin or custom code turned it on using the wcpay_dev_mode filter. Live accounts can only be set up on your live store. Learn more'
		);
	} );

	it( 'leaves out the development mode reason when there is none', async () => {
		mockOnboardingContext();
		mockIsInDevMode.mockReturnValue( true );

		const { container } = render( <EmbeddedKyc /> );

		expect( container ).toHaveTextContent(
			'Your store is in development mode, so payments are simulated. ' +
				'Live accounts can only be set up on your live store. Learn more'
		);
	} );

	it( 'does not show dev mode warning when dev mode is not active', async () => {
		mockOnboardingContext();
		mockIsInDevMode.mockReturnValue( false );

		const { container } = render( <EmbeddedKyc /> );

		expect( container.textContent ).not.toContain(
			'Your store is in development mode'
		);
	} );
} );
