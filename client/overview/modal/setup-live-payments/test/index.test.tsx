/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import SetupLivePaymentsModal from '../index';

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn().mockReturnValue( { updateOptions: jest.fn() } ),
} ) );

declare const global: {
	wcpaySettings: {
		connectUrl: string;
	};
};

describe( 'Setup Live Payments Modal', () => {
	global.wcpaySettings = {
		connectUrl: 'https://wcpay.test/connect',
	};

	it( 'modal is open by default', () => {
		render( <SetupLivePaymentsModal closeModal={ () => jest.fn() } /> );

		expect(
			screen.queryByText(
				'Before proceeding, please take note of the following information:'
			)
		).toBeInTheDocument();
	} );

	it( 'calls `handleSetup` when setup button is clicked', () => {
		Object.defineProperty( window, 'location', {
			configurable: true,
			enumerable: true,
			value: new URL( window.location.href ),
		} );

		render( <SetupLivePaymentsModal closeModal={ () => jest.fn() } /> );

		user.click(
			screen.getByRole( 'button', {
				name: 'Continue setup',
			} )
		);

		expect( window.location.href ).toBe(
			`https://wcpay.test/connect?wcpay-disable-onboarding-test-mode=true`
		);
	} );
} );
