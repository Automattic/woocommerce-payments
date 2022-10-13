/** @format */
/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import user from '@testing-library/user-event';
import ConnectAccountPage from '..';

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
		user.click( screen.getByRole( 'link', { name: /Finish setup/ } ) );

		const modalSelector =
			'.woocommerce-payments__onboarding_location_check-modal';
		expect( document.body.querySelector( modalSelector ) ).not.toBeNull();
	} );
} );
