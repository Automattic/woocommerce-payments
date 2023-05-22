/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import ProgressiveOnboardingEligibilityModal from '../index';

declare const global: {
	wcpaySettings: {
		connectUrl: string;
	};
};

describe( 'Progressive Onboarding Eligibility Modal', () => {
	it( 'modal is open by default', () => {
		render( <ProgressiveOnboardingEligibilityModal /> );

		const queryHeading = () =>
			screen.queryByRole( 'heading', {
				name:
					'You’re eligible to start selling now and fast-track the setup process.',
			} );

		expect( queryHeading() ).toBeInTheDocument();
	} );

	it( 'closes modal when enable button is clicked', () => {
		render( <ProgressiveOnboardingEligibilityModal /> );

		user.click(
			screen.getByRole( 'button', {
				name: 'Enable payments only',
			} )
		);

		expect(
			screen.queryByRole( 'heading', {
				name:
					'You’re eligible to start selling now and fast-track the setup process.',
			} )
		).not.toBeInTheDocument();
	} );

	it( 'calls `handleSetup` when setup button is clicked', () => {
		global.wcpaySettings = {
			connectUrl: 'https://wcpay.test/connect',
		};

		Object.defineProperty( window, 'location', {
			configurable: true,
			enumerable: true,
			value: new URL( window.location.href ),
		} );

		render( <ProgressiveOnboardingEligibilityModal /> );

		user.click(
			screen.getByRole( 'button', {
				name: 'Set up payments and deposits',
			} )
		);

		expect( window.location.href ).toBe(
			`https://wcpay.test/connect?collect_payout_requirements=true`
		);
	} );
} );
