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
		devMode: boolean;
	};
};

describe( 'AccountTools', () => {
	it( 'should NOT render in live mode', () => {
		global.wcpaySettings = {
			devMode: false,
		};

		const { container } = render(
			<AccountTools openModal={ openModal } />
		);

		expect( container ).toMatchSnapshot();
	} );

	it( 'should render in sandbox mode', () => {
		global.wcpaySettings = {
			devMode: true,
		};

		const { container } = render(
			<AccountTools openModal={ openModal } />
		);

		expect( container ).toMatchSnapshot();
	} );
} );
