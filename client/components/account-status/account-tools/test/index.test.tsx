/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { AccountTools } from '..';

const accountLink = '/onboarding';
const openModal = jest.fn();

declare const global: {
	wcpaySettings: {
		devMode: boolean;
	};
};

describe( 'AccountTools', () => {
	it( 'should render in live mode', () => {
		global.wcpaySettings = {
			devMode: false,
		};

		const { container } = render(
			<AccountTools accountLink={ accountLink } openModal={ openModal } />
		);
		expect( container ).toMatchSnapshot();
	} );

	it( 'should not render in dev mode', () => {
		global.wcpaySettings = {
			devMode: true,
		};

		render(
			<AccountTools accountLink={ accountLink } openModal={ openModal } />
		);

		expect(
			screen.queryByText(
				'If you are experiencing problems completing account setup, or need to change the email/country associated with your account, you can reset your account and start from the beginning.'
			)
		).not.toBeInTheDocument();
	} );
} );
