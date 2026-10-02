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
		accountStatus: {
			isLive: boolean;
			testDrive: boolean;
		};
	};
};

const setAccount = ( {
	isLive,
	testDrive,
	devMode,
}: {
	isLive: boolean;
	testDrive: boolean;
	devMode: boolean;
} ) => {
	global.wcpaySettings = {
		isAccountConnected: true,
		devMode,
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
	it.each`
		label                       | testDrive
		${ 'test [drive] account' } | ${ true }
		${ 'sandbox account' }      | ${ false }
	`(
		'explains development mode for a $label',
		async ( { testDrive }: { testDrive: boolean } ) => {
			setAccount( { isLive: false, testDrive, devMode: true } );

			const { container } = renderNotice();

			expect(
				container.querySelector( '.sandbox-mode-notice' )
			).toHaveTextContent(
				"You're in development mode and using a test account. Live payments can't be activated while development mode is on."
			);

			await user.click(
				screen.getByRole( 'button', {
					name: 'Learn more about development mode',
				} )
			);

			expect(
				screen.getByText(
					'Development mode is on for staging and development sites. To accept real payments, use your live store.',
					{ exact: false }
				)
			).toBeInTheDocument();
			expect(
				screen.getByRole( 'link', { name: /Learn more/ } )
			).toHaveAttribute(
				'href',
				'https://woocommerce.com/document/woopayments/testing-and-troubleshooting/test-accounts/#developer-notes'
			);
		}
	);

	it( 'explains test mode and tracks Learn more for a live account', async () => {
		setAccount( { isLive: true, testDrive: false, devMode: true } );

		const { container } = renderNotice();

		expect(
			container.querySelector( '.sandbox-mode-notice' )
		).toHaveTextContent(
			'WooPayments is in test mode — all transactions are simulated. Test mode is on because development mode is on.'
		);

		await user.click(
			screen.getByRole( 'button', {
				name: 'Learn more about development mode',
			} )
		);
		const learnMoreLink = screen.getByRole( 'link', {
			name: 'Learn more',
		} );
		expect( learnMoreLink ).toHaveAttribute(
			'href',
			'https://woocommerce.com/document/woopayments/testing-and-troubleshooting/test-accounts/#developer-notes'
		);

		await user.click( learnMoreLink );
		expect( recordEvent ).toHaveBeenCalledWith(
			'wcpay_overview_sandbox_mode_learn_more_clicked',
			{ account_type: 'live', is_dev_mode: true }
		);
	} );

	it( 'renders nothing for a live account outside development mode', () => {
		setAccount( { isLive: true, testDrive: false, devMode: false } );

		const { container } = renderNotice();

		expect( container ).toBeEmptyDOMElement();
	} );
} );
