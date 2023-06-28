/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { mocked } from 'ts-jest/utils';

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
		connectIncentive?: {
			id: string;
			description: string;
			tc_url: string;
		};
	};
};

const mockedIcentive = {
	id: 'incentive-id',
	description: 'incentive-description',
	tc_url: 'incentive-tc-url',
};

describe( 'ConnectAccountPage', () => {
	beforeEach( () => {
		Object.defineProperty( window, 'location', {
			value: {
				assign: jest.fn(),
			},
		} );

		global.wcpaySettings = {
			connectUrl: '/wcpay-connect-url',
			connect: {
				country: 'US',
				availableCountries: { US: 'United States (US)' },
			},
		};
	} );

	test( 'should render correctly', () => {
		const { container: page } = render( <ConnectAccountPage /> );
		expect( page ).toMatchSnapshot();
	} );

	test( 'should render correctly when on-boarding disabled', () => {
		global.wcpaySettings.onBoardingDisabled = true;
		const { container: page } = render( <ConnectAccountPage /> );
		expect( page ).toMatchSnapshot();
	} );

	test( 'should render correctly with an incentive', () => {
		global.wcpaySettings.connectIncentive = mockedIcentive;
		const { container: page } = render( <ConnectAccountPage /> );
		expect( page ).toMatchSnapshot();
	} );

	test( 'should request promo activation with an incentive', async () => {
		global.wcpaySettings.connectIncentive = mockedIcentive;
		render( <ConnectAccountPage /> );

		userEvent.click(
			screen.getByRole( 'button', { name: 'Finish setup' } )
		);

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledWith( {
				path:
					'/wc-analytics/admin/notes/experimental-activate-promo/incentive-id',
				method: 'POST',
			} );
		} );
	} );

	test( 'should display error from request promo activation with an incentive', async () => {
		global.wcpaySettings.connectIncentive = mockedIcentive;
		render( <ConnectAccountPage /> );

		// mockApiFetch.mockRejectedValueOnce( {} );
		mocked( apiFetch ).mockRejectedValueOnce( new Error() );

		userEvent.click(
			screen.getByRole( 'button', { name: 'Finish setup' } )
		);

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalled();
		} );

		expect(
			screen.queryAllByText(
				/There was an error applying the promotion/
			)[ 0 ]
		).toBeInTheDocument();
	} );

	test( 'should prompt unsupported countries', () => {
		global.wcpaySettings = {
			connectUrl: '/wcpay-connect-url',
			connect: {
				country: 'CA',
				availableCountries: {
					GB: 'United Kingdom (UK)',
					US: 'United States (US)',
				},
			},
		};
		render( <ConnectAccountPage /> );
		userEvent.click(
			screen.getByRole( 'button', { name: 'Finish setup' } )
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
