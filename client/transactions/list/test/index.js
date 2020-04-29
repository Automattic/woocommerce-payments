/** @format */

/**
 * External dependencies
 */
import { render, cleanup, fireEvent } from '@testing-library/react';
import { getQuery, updateQueryString } from '@woocommerce/navigation';
import { isEmpty } from 'lodash';

/**
 * Internal dependencies
 */
import { TransactionsList } from '../';
import { useTransactions, useTransactionsSummary } from 'data';

jest.mock( 'data', () => ( {
	useTransactions: jest.fn(),
	useTransactionsSummary: jest.fn(),
} ) );

const mockTransactions = [
	{
		// eslint-disable-next-line camelcase
		transaction_id: 'txn_j23jda9JJa',
		date: '2020-01-02 17:46:02',
		type: 'refund',
		source: 'visa',
		order: {
			number: 123,
			url: 'https://example.com/order/123',
		},
		// eslint-disable-next-line camelcase
		customer_name: 'Another customer',
		// eslint-disable-next-line camelcase
		customer_email: 'another@customer.com',
		// eslint-disable-next-line camelcase
		customer_country: 'US',
		// eslint-disable-next-line camelcase
		charge_id: 'ch_j23w39dsajda',
		amount: 1000,
		fees: 50,
		net: 950,
		currency: 'usd',
		// eslint-disable-next-line camelcase
		risk_level: 0,
		// eslint-disable-next-line camelcase
		deposit_id: null,
	},
	{
		// eslint-disable-next-line camelcase
		transaction_id: 'txn_oa9kaKaa8',
		date: '2020-01-05 04:22:59',
		// eslint-disable-next-line camelcase
		date_available: '2020-01-07 00:00:00',
		type: 'charge',
		source: 'mastercard',
		order: {
			number: 125,
			url: 'https://example.com/order/125',
		},
		// eslint-disable-next-line camelcase
		customer_name: 'My name',
		// eslint-disable-next-line camelcase
		customer_email: 'a@b.com',
		// eslint-disable-next-line camelcase
		customer_country: 'US',
		// eslint-disable-next-line camelcase
		charge_id: 'ch_j239jda',
		amount: 1500,
		fees: 50,
		net: 1450,
		currency: 'usd',
		// eslint-disable-next-line camelcase
		risk_level: 2,
		// eslint-disable-next-line camelcase
		deposit_id: 'po_mock',
	},
];

describe( 'Transactions list', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		// the query string is preserved across tests, so we need to reset it
		if ( ! isEmpty( getQuery() ) ) {
			updateQueryString( {}, '/', {} );
		}
	} );

	afterEach( () => {
		cleanup();
	} );

	test( 'renders correctly when filtered to deposit', () => {
		useTransactions.mockReturnValue( {
			transactions: mockTransactions.filter( ( txn ) => txn.deposit_id === 'po_mock' ),
			isLoading: false,
		} );

		useTransactionsSummary.mockReturnValue( {
			transactionsSummary: {
				count: 3,
				fees: 30,
				total: 300,
				net: 270,
			},
			isLoading: false,
		} );

		const { container } = render(
			<TransactionsList depositId="po_mock" />
		);
		expect( container ).toMatchSnapshot();
		expect( useTransactions.mock.calls[ 0 ][ 1 ] ).toBe( 'po_mock' );
	} );

	describe( 'when not filtered by deposit', () => {
		let container, getAllByText, rerender;
		beforeEach( () => {
			useTransactions.mockReturnValue( {
				transactions: mockTransactions,
				isLoading: false,
			} );

			useTransactionsSummary.mockReturnValue( {
				transactionsSummary: {
					count: 10,
					fees: 100,
					total: 1000,
					net: 900,
				},
				isLoading: false,
			} );

			( { container, rerender, getAllByText } = render( <TransactionsList /> ) );
		} );

		test( 'renders correctly', () => {
			expect( container ).toMatchSnapshot();
		} );

		test( 'sorts by default field date', () => {
			sortBy( 'Date / Time' );
			expectSortingToBe( 'date', 'asc' );

			sortBy( 'Date / Time' );
			expectSortingToBe( 'date', 'desc' );
		} );

		test( 'sorts by amount', () => {
			sortBy( 'Amount' );
			expectSortingToBe( 'amount', 'desc' );

			sortBy( 'Amount' );
			expectSortingToBe( 'amount', 'asc' );
		} );

		test( 'sorts by fees', () => {
			sortBy( 'Fees' );
			expectSortingToBe( 'fees', 'desc' );

			sortBy( 'Fees' );
			expectSortingToBe( 'fees', 'asc' );
		} );

		test( 'sorts by net', () => {
			sortBy( 'Net' );
			expectSortingToBe( 'net', 'desc' );

			sortBy( 'Net' );
			expectSortingToBe( 'net', 'asc' );
		} );

		function sortBy( field ) {
			const sortButton = getAllByText( field )[ 0 ];
			fireEvent.click( sortButton, { preventDefault: () => {} } );
			rerender( <TransactionsList /> );
		}

		function expectSortingToBe( field, direction ) {
			expect( getQuery().orderby ).toEqual( field );
			expect( getQuery().order ).toEqual( direction );
			const useTransactionsCall = useTransactions.mock.calls[ useTransactions.mock.calls.length - 1 ];
			expect( useTransactionsCall[ 0 ].orderby ).toEqual( field );
			expect( useTransactionsCall[ 0 ].order ).toEqual( direction );
		}
	} );
} );
