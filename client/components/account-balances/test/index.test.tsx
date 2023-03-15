/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import AccountBalances from '../';

declare const global: {
	wcpaySettings: {
		accountDefaultCurrency: 'USD';
		zeroDecimalCurrencies: string[];
		currencyData: Record< string, any >;
		connect: {
			country: string;
		};
	};
};

describe( 'AccountBalances', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			accountDefaultCurrency: 'USD',
			zeroDecimalCurrencies: [],
			connect: {
				country: 'US',
			},
			currencyData: {
				US: {
					code: 'USD',
					symbol: '$',
					symbolPosition: 'left',
					thousandSeparator: ',',
					decimalSeparator: '.',
					precision: 2,
				},
			},
		};
	} );

	// Adding an initial test to be expanded upon.
	test( 'renders', () => {
		const { container } = render( <AccountBalances /> );
		expect( container ).toMatchSnapshot();
	} );
} );
