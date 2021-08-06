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
import { useDeposits, useDepositsSummary } from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
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
		updateQueryString( {}, '/', {} );

		global.wcpaySettings = { zeroDecimalCurrencies: [] };
	} );

	// this also covers structural test for single currency.
	test( 'renders correctly with multiple currencies', () => {
		useDeposits.mockReturnValue( {
			deposits: mockDeposits,
			depositsCount: 2,
			isLoading: false,
		} );

		useDepositsSummary.mockReturnValue( {
			depositsSummary: {
				count: 2,
				total: 5000,
				store_currencies: [ 'usd', 'eur' ],
			},
			isLoading: false,
		} );

		const { container } = render( <DepositsList /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders correctly a single deposit', () => {
		useDeposits.mockReturnValue( {
			deposits: mockDeposits,
			depositsCount: 1,
			isLoading: false,
		} );

		useDepositsSummary.mockReturnValue( {
			depositsSummary: {
				count: 1,
				total: 5000,
				store_currencies: [ 'usd' ],
			},
			isLoading: false,
		} );

		const { container } = render( <DepositsList /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders table summary only when the deposits summary data is available', () => {
		useDeposits.mockReturnValue( {
			deposits: mockDeposits,
			isLoading: false,
		} );

		useDepositsSummary.mockReturnValue( {
			depositsSummary: {
				count: 30,
			},
			isLoading: true,
		} );

		let { container } = render( <DepositsList /> );
		let tableSummary = container.querySelectorAll(
			'.woocommerce-table__summary'
		);

		expect( tableSummary ).toHaveLength( 0 );

		useDepositsSummary.mockReturnValue( {
			depositsSummary: {
				count: 2,
				total: 100,
			},
			isLoading: false,
		} );

		( { container } = render( <DepositsList /> ) );
		tableSummary = container.querySelectorAll(
			'.woocommerce-table__summary'
		);

		expect( tableSummary ).toHaveLength( 1 );
	} );
} );
