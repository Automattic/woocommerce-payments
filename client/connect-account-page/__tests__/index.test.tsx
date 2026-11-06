/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import ConnectAccountPage from '..';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

declare const global: {
	wcpaySettings: {
		connectUrl: string;
		connect: {
			country: string;
			availableCountries: Record< string, string >;
		};
		onBoardingDisabled?: boolean;
		isWooPayStoreCountryAvailable?: boolean;
		isJetpackConnected: boolean;
		isAccountConnected?: boolean;
		isAccountValid?: boolean;
		accountStatus?: {
			isLive: boolean;
			testDrive: boolean;
		};
		testModeOnboarding?: boolean;
		devMode?: boolean;
		devModeReason?: string;
	};
};

// jsdom marks `window.location` unforgeable, so the page's redirects go
// through the `redirectTo` helper, which is mocked out here.
jest.mock( 'wcpay/utils', () => ( {
	...jest.requireActual( 'wcpay/utils' ),
	redirectTo: jest.fn(),
} ) );

describe( 'ConnectAccountPage', () => {
	beforeAll( () => {
		jest.spyOn( console, 'error' ).mockImplementation( () => null );
		jest.spyOn( console, 'warn' ).mockImplementation( () => null );
	} );

	beforeEach( () => {
		global.wcpaySettings = {
			connectUrl: '/wcpay-connect-url',
			connect: {
				country: 'US',
				availableCountries: { US: 'United States (US)' },
			},
			isWooPayStoreCountryAvailable: false,
			isJetpackConnected: true,
		};

		// Clear any previous API fetch mocks
		jest.clearAllMocks();
	} );

	test( 'should render correctly', () => {
		const { container: page } = render( <ConnectAccountPage /> );
		expect( page ).toMatchSnapshot();
	} );

	test( 'explains development mode instead of offering to disable test mode', () => {
		global.wcpaySettings.isAccountConnected = false;
		global.wcpaySettings.testModeOnboarding = true;
		global.wcpaySettings.devMode = true;
		global.wcpaySettings.devModeReason =
			'The WCPAY_DEV_MODE constant is set.';

		const { container } = render( <ConnectAccountPage /> );

		expect(
			container.querySelector( '.wcpay-banner-notice__content' )
		).toHaveTextContent(
			'Your store is in development mode, so payments are simulated. ' +
				'The WCPAY_DEV_MODE constant is set. Live accounts can only be set up on your live store. Learn more'
		);
		expect( container ).not.toHaveTextContent( 'please disable it' );
	} );

	test( 'explains development mode for a partially onboarded account', () => {
		global.wcpaySettings.isAccountConnected = true;
		global.wcpaySettings.isAccountValid = false;
		global.wcpaySettings.accountStatus = {
			isLive: false,
			testDrive: false,
		};
		global.wcpaySettings.testModeOnboarding = true;
		global.wcpaySettings.devMode = true;
		global.wcpaySettings.devModeReason =
			'The WCPAY_DEV_MODE constant is set.';

		const { container } = render( <ConnectAccountPage /> );

		expect(
			container.querySelector( '.sandbox-mode-notice' )
		).toHaveTextContent(
			'Your store is in development mode, so payments are simulated. ' +
				'The WCPAY_DEV_MODE constant is set. Live accounts can only be set up on your live store. Learn more'
		);
	} );

	test( 'offers to disable test mode onboarding outside development mode', () => {
		global.wcpaySettings.isAccountConnected = false;
		global.wcpaySettings.testModeOnboarding = true;
		global.wcpaySettings.devMode = false;

		const { container } = render( <ConnectAccountPage /> );

		expect(
			container.querySelector( '.wcpay-banner-notice__content' )
		).toHaveTextContent(
			'Test mode is enabled, only test accounts will be created. If you want to process live transactions, please disable it.'
		);
		expect( container ).not.toHaveTextContent(
			'Your store is in development mode'
		);
	} );

	test( 'should render correctly when on-boarding disabled', () => {
		global.wcpaySettings.onBoardingDisabled = true;
		const { container: page } = render( <ConnectAccountPage /> );
		expect( page ).toMatchSnapshot();
	} );

	test( 'should render correctly with WooPay eligible', () => {
		global.wcpaySettings.isWooPayStoreCountryAvailable = true;
		const { container: page } = render( <ConnectAccountPage /> );
		expect( page ).toMatchSnapshot();
	} );

	test( 'should display non supported country banner', () => {
		global.wcpaySettings = {
			connectUrl: '/wcpay-connect-url',
			connect: {
				country: 'CA',
				availableCountries: {
					GB: 'United Kingdom (UK)',
					US: 'United States (US)',
				},
			},
			isJetpackConnected: true,
		};
		render( <ConnectAccountPage /> );

		expect(
			screen.queryAllByText(
				/woopayments is not currently available in your location/i
			)[ 0 ]
		).toBeInTheDocument();
	} );

	test( 'should prompt unsupported countries', async () => {
		global.wcpaySettings = {
			connectUrl: '/wcpay-connect-url',
			connect: {
				country: 'CA',
				availableCountries: {
					GB: 'United Kingdom (UK)',
					US: 'United States (US)',
				},
			},
			isJetpackConnected: true,
		};
		render( <ConnectAccountPage /> );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Verify business details' } )
		);

		const modalSelector =
			'.woocommerce-payments__onboarding_location_check-modal';
		expect( document.body.querySelector( modalSelector ) ).not.toBeNull();

		// eslint-disable-next-line @typescript-eslint/ban-ts-comment
		// @ts-ignore
		expect( console ).toHaveWarnedWith(
			// eslint-disable-next-line max-len
			'List with items prop is deprecated is deprecated and will be removed in version 9.0.0. Note: See ExperimentalList / ExperimentalListItem for the new API that will replace this component in future versions.'
		);
	} );
} );
