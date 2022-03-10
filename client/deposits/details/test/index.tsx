/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import { DepositOverview } from '../';
import { useDeposit } from 'wcpay/data';
import { CachedDeposit } from 'wcpay/types/deposits';

jest.mock( 'wcpay/data', () => ( {
	useDeposit: jest.fn(),
} ) );

const mockUseDeposit = useDeposit as jest.MockedFunction< typeof useDeposit >;

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
} as CachedDeposit;

declare const global: {
	wcpaySettings: {
		zeroDecimalCurrencies: string[];
	};
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
		mockUseDeposit.mockReturnValue( {
			deposit: mockDeposit,
			isLoading: false,
		} );

		const { container: overview } = render(
			<DepositOverview depositId="po_mock" />
		);
		expect( overview ).toMatchSnapshot();
	} );

	test( 'renders instant deposit correctly', () => {
		mockUseDeposit.mockReturnValue( {
			deposit: { ...mockDeposit, automatic: false },
			isLoading: false,
		} );

		const { container: overview } = render(
			<DepositOverview depositId="po_mock" />
		);
		expect( overview ).toMatchSnapshot();
	} );
} );
