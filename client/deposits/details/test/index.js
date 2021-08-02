/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { DepositOverview } from '../';
import { useDeposit } from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useDeposit: jest.fn(),
} ) );

const mockDeposit = {
	id: 'po_mock',
	date: '2020-01-02 17:46:02',
	type: 'deposit',
	amount: 2000,
	status: 'paid',
	bankAccount: 'MOCK BANK •••• 1234 (USD)',
	automatic: true,
	fee: 30,
	fee_percetange: 1.5,
};

describe( 'Deposit overview', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
		};
	} );

	test( 'renders automatic deposit correctly', () => {
		useDeposit.mockReturnValue( {
			deposit: mockDeposit,
			isLoading: false,
		} );

		const { container: overview } = render(
			<DepositOverview depositId="po_mock" />
		);
		expect( overview ).toMatchSnapshot();
	} );

	test( 'renders instant deposit correctly', () => {
		useDeposit.mockReturnValue( {
			deposit: { ...mockDeposit, automatic: false },
			isLoading: false,
		} );

		const { container: overview } = render(
			<DepositOverview depositId="po_mock" />
		);
		expect( overview ).toMatchSnapshot();
	} );
} );
