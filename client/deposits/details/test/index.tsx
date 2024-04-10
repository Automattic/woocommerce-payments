/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import type { CachedDeposit } from 'types/deposits';
import { DepositOverview } from '../';

const mockDeposit = {
	id: 'po_mock',
	date: '2020-01-02 17:46:02',
	type: 'deposit',
	amount: 2000,
	status: 'paid',
	bankAccount: 'MOCK BANK •••• 1234 (USD)',
	automatic: true,
	fee: 30,
	fee_percentage: 1.5,
	currency: 'USD',
} as CachedDeposit;

declare const global: {
	wcpaySettings: {
		zeroDecimalCurrencies: string[];
		currencyData: Record< string, any >;
		connect: {
			country: string;
		};
	};
	wcSettings: { countries: Record< string, string > };
};

describe( 'Deposit overview', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		global.wcpaySettings = {
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

	test( 'renders automatic deposit correctly', () => {
		const { container: overview } = render(
			<DepositOverview deposit={ mockDeposit } />
		);
		expect( overview ).toMatchSnapshot();
	} );

	test( 'renders instant deposit correctly', () => {
		const { container: overview } = render(
			<DepositOverview deposit={ { ...mockDeposit, automatic: false } } />
		);
		expect( overview ).toMatchSnapshot();
	} );

	// test when deposit data could not be found, it renders a notice
	test( 'renders notice when deposit data is not found', () => {
		const { container: overview } = render(
			<DepositOverview deposit={ undefined } />
		);
		expect( overview ).toMatchSnapshot();
	} );
} );
