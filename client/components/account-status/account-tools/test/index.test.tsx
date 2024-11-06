/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { AccountTools } from '..';

const openModal = jest.fn();

declare const global: {
	wcpaySettings: {
		testModeOnboarding: boolean;
	};
};

describe( 'AccountTools', () => {
	it( 'should NOT render in live mode', () => {
		global.wcpaySettings = {
			testModeOnboarding: false,
		};

		const { container } = render(
			<AccountTools openModal={ openModal } />
		);

		expect( container ).toMatchSnapshot();
	} );

	it( 'should render in test/sandbox mode onboarding', () => {
		global.wcpaySettings = {
			testModeOnboarding: true,
		};

		const { container } = render(
			<AccountTools openModal={ openModal } />
		);

		expect( container ).toMatchSnapshot();
	} );
} );
