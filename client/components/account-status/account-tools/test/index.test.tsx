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
			<AccountTools
				accountLink={ accountLink }
				detailsSubmitted={ false }
				openModal={ openModal }
			/>
		);
		expect( container ).toMatchSnapshot();
	} );

	it( 'should render in sandbox mode', () => {
		global.wcpaySettings = {
			devMode: true,
		};

		const { container } = render(
			<AccountTools
				accountLink={ accountLink }
				detailsSubmitted={ false }
				openModal={ openModal }
			/>
		);

		expect( container ).toMatchSnapshot();
	} );

	it( 'should render in sandbox mode for details submitted account without finish setup button', () => {
		global.wcpaySettings = {
			devMode: true,
		};

		render(
			<AccountTools
				accountLink={ accountLink }
				detailsSubmitted={ true }
				openModal={ openModal }
			/>
		);

		expect( screen.queryByText( 'Finish setup' ) ).not.toBeInTheDocument();
	} );
} );
