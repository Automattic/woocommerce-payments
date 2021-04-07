/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import { updateQueryString } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { DepositsList } from '../';
import { useDeposits, useDepositsSummary } from 'data';

jest.mock( 'data', () => ( {
	useDeposits: jest.fn(),
	useDepositsSummary: jest.fn(),
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

		// the query string is preserved across tests, so we need to reset it
		// eslint-disable-next-line camelcase
		updateQueryString( { currency_is: 'usd' }, '/', {} );

		global.wcpaySettings = {
			currencies: {
				default: 'usd',
				supported: [ 'usd' ],
				names: { usd: 'United States (US) dollar' },
			},
			zeroDecimalCurrencies: [],
		};
	} );

	test( 'renders correctly', () => {
		useDeposits.mockReturnValue( {
			deposits: mockDeposits,
			depositsCount: 2,
			isLoading: false,
		} );

		useDepositsSummary.mockReturnValue( {
			depositsSummary: {
				count: 3,
				total: 300,
			},
			isLoading: false,
		} );

		const { container } = render( <DepositsList /> );
		expect( container ).toMatchSnapshot();
	} );
} );
