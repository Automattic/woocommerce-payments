/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { DepositsList } from '../';
import { useDeposits } from 'data';

jest.mock( 'data', () => ( {
	useDeposits: jest.fn(),
} ) );

const mockDeposits = [
	{
		id: 'po_mock1',
		date: '2020-01-02 17:46:02',
		type: 'deposit',
		amount: 2000,
		status: 'paid',
		bankAccount: 'MOCK BANK •••• 1234 (USD)',
	},
	{
		id: 'po_mock2',
		date: '2020-01-03 17:46:02',
		type: 'withdrawal',
		amount: 3000,
		status: 'pending',
		bankAccount: 'MOCK BANK •••• 1234 (USD)',
	},
];

describe( 'Deposits list', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		global.wcpaySettings = { zeroDecimalCurrencies: [] };
	} );

	test( 'renders correctly', () => {
		useDeposits.mockReturnValue( {
			deposits: mockDeposits,
			depositsCount: 2,
			isLoading: false,
		} );

		const { container } = render( <DepositsList /> );
		expect( container ).toMatchSnapshot();
	} );
} );
