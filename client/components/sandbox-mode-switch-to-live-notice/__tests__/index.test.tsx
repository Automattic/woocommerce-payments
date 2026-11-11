/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import SandboxModeSwitchToLiveNotice from '..';
import { recordEvent } from 'wcpay/tracks';

jest.mock( 'wcpay/tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

declare const global: {
	wcpaySettings: {
		isAccountConnected: boolean;
		devMode: boolean;
		testMode: boolean;
		devModeReason: string;
		accountStatus: {
			isLive: boolean;
			testDrive: boolean;
		};
	};
};

const devModeReason = 'The WCPAY_DEV_MODE constant is set.';

// Development mode forces test mode unless the wcpay_test_mode filter overrides it.
const setAccount = ( {
	isLive,
	testDrive,
	devMode,
	testMode = devMode,
}: {
	isLive: boolean;
	testDrive: boolean;
	devMode: boolean;
	testMode?: boolean;
} ) => {
	global.wcpaySettings = {
		isAccountConnected: true,
		devMode,
		testMode,
		devModeReason,
		accountStatus: { isLive, testDrive },
	};
};

const renderNotice = () =>
	render(
		<SandboxModeSwitchToLiveNotice
			from="WCPAY_OVERVIEW"
			source="wcpay-overview-page"
		/>
	);

describe( 'SandboxModeSwitchToLiveNotice in development mode', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it.each`
		label                       | isLive     | testDrive  | accountType
		${ 'test [drive] account' } | ${ false } | ${ true }  | ${ 'test' }
		${ 'sandbox account' }      | ${ false } | ${ false } | ${ 'sandbox' }
		${ 'live account' }         | ${ true }  | ${ false } | ${ 'live' }
	`(
		'shows the development mode message and tracks Learn more for a $label',
		async ( {
			isLive,
			testDrive,
			accountType,
		}: {
			isLive: boolean;
			testDrive: boolean;
			accountType: string;
		} ) => {
			setAccount( { isLive, testDrive, devMode: true } );

			const { container } = renderNotice();

			expect(
				container.querySelector( '.sandbox-mode-notice' )
			).toHaveTextContent(
				'Your store is in development mode, so payments are simulated. ' +
					'The WCPAY_DEV_MODE constant is set. Real payments only work on your live store. Learn more'
			);

			const learnMoreLink = screen.getByRole( 'link', {
				name: /Learn more/,
			} );
			expect( learnMoreLink ).toHaveAttribute(
				'href',
				'https://woocommerce.com/document/woopayments/testing-and-troubleshooting/test-accounts/#developer-notes'
			);

			await user.click( learnMoreLink );
			expect( recordEvent ).toHaveBeenCalledWith(
				'wcpay_overview_sandbox_mode_learn_more_clicked',
				{ account_type: accountType, is_dev_mode: true }
			);
		}
	);

	it( 'renders nothing for a live account when a filter turns test mode off in development mode', () => {
		setAccount( {
			isLive: true,
			testDrive: false,
			devMode: true,
			testMode: false,
		} );

		const { container } = renderNotice();

		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'renders nothing for a live account outside development mode', () => {
		setAccount( { isLive: true, testDrive: false, devMode: false } );

		const { container } = renderNotice();

		expect( container ).toBeEmptyDOMElement();
	} );
} );
