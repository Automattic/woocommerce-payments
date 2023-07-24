/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import SetupRealPayments from '../setup-real-payments';

declare const global: {
	wcpaySettings: {
		connectUrl: string;
	};
};

describe( 'Setup Real Payments', () => {
	it( 'opens modal when set up payments button is clicked', () => {
		render( <SetupRealPayments /> );

		const queryHeading = () =>
			screen.queryByRole( 'heading', {
				name: 'Setup live payments on your store',
			} );

		expect( queryHeading() ).not.toBeInTheDocument();

		user.click( screen.getByRole( 'button' ) );

		expect( queryHeading() ).toBeInTheDocument();
	} );

	it( 'closes modal when cancel button is clicked', () => {
		render( <SetupRealPayments /> );

		user.click( screen.getByRole( 'button' ) );
		user.click(
			screen.getByRole( 'button', {
				name: 'Cancel',
			} )
		);

		expect(
			screen.queryByRole( 'heading', {
				name: 'Setup live payments on your store',
			} )
		).not.toBeInTheDocument();
	} );

	it( 'calls handleContinue when continue setup button is clicked', () => {
		global.wcpaySettings = {
			connectUrl: 'https://wcpay.test/connect',
		};

		Object.defineProperty( window, 'location', {
			configurable: true,
			enumerable: true,
			value: new URL( window.location.href ),
		} );

		render( <SetupRealPayments /> );

		user.click( screen.getByRole( 'button' ) );
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
