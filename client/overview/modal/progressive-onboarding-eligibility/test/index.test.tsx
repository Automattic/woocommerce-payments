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

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn().mockReturnValue( { updateOptions: jest.fn() } ),
} ) );

declare const global: {
	wcpaySettings: {
		connectUrl: string;
		progressiveOnboarding?: {
			isEligibilityModalDismissed: boolean;
		};
	};
};

describe( 'Progressive Onboarding Eligibility Modal', () => {
	global.wcpaySettings = {
		connectUrl: 'https://wcpay.test/connect',
		progressiveOnboarding: {
			isEligibilityModalDismissed: false,
		},
	};

	it( 'modal is open by default', () => {
		render( <ProgressiveOnboardingEligibilityModal /> );

		const queryHeading = () =>
			screen.queryByRole( 'heading', {
				name: 'You’re ready to sell.',
			} );

		expect( queryHeading() ).toBeInTheDocument();
	} );

	it( 'closes modal when enable button is clicked', () => {
		global.wcpaySettings = {
			connectUrl: 'https://wcpay.test/connect',
			progressiveOnboarding: {
				isEligibilityModalDismissed: false,
			},
		};

		render( <ProgressiveOnboardingEligibilityModal /> );

		user.click(
			screen.getByRole( 'button', {
				name: 'Start selling',
			} )
		);

		expect(
			screen.queryByRole( 'heading', {
				name: 'You’re ready to sell.',
			} )
		).not.toBeInTheDocument();
	} );

	it( 'calls `handleSetup` when setup button is clicked', () => {
		global.wcpaySettings = {
			connectUrl: 'https://wcpay.test/connect',
			progressiveOnboarding: {
				isEligibilityModalDismissed: false,
			},
		};

		Object.defineProperty( window, 'location', {
			configurable: true,
			enumerable: true,
			value: new URL( window.location.href ),
		} );

		render( <ProgressiveOnboardingEligibilityModal /> );

		user.click(
			screen.getByRole( 'button', {
				name: 'Start receiving deposits',
			} )
		);

		expect( window.location.href ).toBe(
			`https://wcpay.test/connect?collect_payout_requirements=true`
		);
	} );
} );
