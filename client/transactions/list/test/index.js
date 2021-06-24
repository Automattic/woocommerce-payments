/** @format */

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';
import { getQuery, updateQueryString } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { TransactionsList } from '../';
import { useTransactions, useTransactionsSummary } from 'data';

import { downloadCSVFile } from '@woocommerce/csv-export';

jest.mock( 'data', () => ( {
	useTransactions: jest.fn(),
	useTransactionsSummary: jest.fn(),
} ) );

jest.mock( '@woocommerce/csv-export', () => {
	const actualModule = jest.requireActual( '@woocommerce/csv-export' );

	return {
		...actualModule,
		downloadCSVFile: jest.fn(),
	};
} );

const getMockTransactions = () => [
	{
		// eslint-disable-next-line camelcase
		transaction_id: 'txn_j23jda9JJa',
		date: '2020-01-02 17:46:02',
		type: 'refund',
		source: 'visa',
		order: {
			number: 123,
			url: 'https://example.com/order/123',
			// eslint-disable-next-line camelcase
			customer_url: 'https://example.com/customer/my-name',
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
		customer_amount: 1000,
		// eslint-disable-next-line camelcase
		customer_currency: 'usd',
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
		available_on: '2020-01-07 00:00:00',
		type: 'charge',
		source: 'mastercard',
		order: {
			number: 125,
			url: 'https://example.com/order/125',
			// eslint-disable-next-line camelcase
			customer_url: 'https://example.com/customer/my-name',
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
		customer_amount: 3000,
		// eslint-disable-next-line camelcase
		customer_currency: 'mok',
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
		updateQueryString( {}, '/', {} );

		global.wcpaySettings = {
			featureFlags: {
				customSearch: true,
			},
			isSubscriptionsActive: false,
			zeroDecimalCurrencies: [],
		};
	} );

	test( 'renders correctly when filtered by deposit', () => {
		useTransactions.mockReturnValue( {
			transactions: getMockTransactions().filter(
				( txn ) => 'po_mock' === txn.deposit_id
			),
			isLoading: false,
		} );

		useTransactionsSummary.mockReturnValue( {
			transactionsSummary: {
				count: 3,
				currency: 'usd',
				// eslint-disable-next-line camelcase
				store_currencies: [ 'usd' ],
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
		let container, rerender;
		beforeEach( () => {
			useTransactions.mockReturnValue( {
				transactions: getMockTransactions(),
				isLoading: false,
			} );

			useTransactionsSummary.mockReturnValue( {
				transactionsSummary: {
					count: 10,
					currency: 'usd',
					// eslint-disable-next-line camelcase
					store_currencies: [ 'usd' ],
					fees: 100,
					total: 1000,
					net: 900,
				},
				isLoading: false,
			} );

			( { container, rerender } = render( <TransactionsList /> ) );
		} );

		test( 'renders correctly', () => {
			expect( container ).toMatchSnapshot();
		} );

		test( 'sorts by default field date', () => {
			sortBy( 'Date and time' );
			expectSortingToBe( 'date', 'asc' );

			sortBy( 'Date and time' );
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

		test( 'renders table summary only when the transactions summary data is available', () => {
			useTransactionsSummary.mockReturnValue( {
				transactionsSummary: {},
				isLoading: true,
			} );

			( { container } = render( <TransactionsList /> ) );
			let tableSummary = container.querySelectorAll(
				'.woocommerce-table__summary'
			);
			expect( tableSummary ).toHaveLength( 0 );

			useTransactionsSummary.mockReturnValue( {
				transactionsSummary: {
					count: 10,
					currency: 'usd',
					// eslint-disable-next-line camelcase
					store_currencies: [ 'usd' ],
					fees: 100,
					total: 1000,
					net: 900,
				},
				isLoading: false,
			} );

			( { container } = render( <TransactionsList /> ) );
			tableSummary = container.querySelectorAll(
				'.woocommerce-table__summary'
			);

			expect( tableSummary ).toHaveLength( 1 );
		} );

		function sortBy( field ) {
			user.click( screen.getByRole( 'button', { name: field } ) );
			rerender( <TransactionsList /> );
		}

		function expectSortingToBe( field, direction ) {
			expect( getQuery().orderby ).toEqual( field );
			expect( getQuery().order ).toEqual( direction );
			const useTransactionsCall =
				useTransactions.mock.calls[
					useTransactions.mock.calls.length - 1
				];
			expect( useTransactionsCall[ 0 ].orderby ).toEqual( field );
			expect( useTransactionsCall[ 0 ].order ).toEqual( direction );
		}
	} );

	test( 'subscription column renders correctly', () => {
		global.wcpaySettings.isSubscriptionsActive = true;

		const mockTransactions = getMockTransactions();
		mockTransactions[ 0 ].order.subscriptions = [
			{
				number: 246,
				url: 'https://example.com/subscription/246',
			},
		];
		mockTransactions[ 1 ].order.subscriptions = [];

		useTransactions.mockReturnValue( {
			transactions: mockTransactions,
			isLoading: false,
		} );

		useTransactionsSummary.mockReturnValue( {
			transactionsSummary: {
				count: 10,
				currency: 'usd',
				// eslint-disable-next-line camelcase
				store_currencies: [ 'usd' ],
				fees: 100,
				total: 1000,
				net: 900,
			},
			isLoading: false,
		} );

		const { container } = render( <TransactionsList /> );

		expect( container ).toMatchSnapshot();
	} );

	// Several settlement currencies are available -> render the currency filter.
	test( 'renders correctly when can filter by several currencies', () => {
		useTransactions.mockReturnValue( {
			transactions: getMockTransactions(),
			isLoading: false,
		} );

		useTransactionsSummary.mockReturnValue( {
			transactionsSummary: {
				count: 10,
				currency: 'usd',
				// eslint-disable-next-line camelcase
				store_currencies: [ 'eur', 'usd' ],
				fees: 100,
				total: 1000,
				net: 900,
			},
			isLoading: false,
		} );

		const { container } = render( <TransactionsList /> );
		expect( container ).toMatchSnapshot();
	} );

	// The currency filter has been applied, render the filter even for a single settlement currency case.
	test( 'renders correctly when filtered by currency', () => {
		// eslint-disable-next-line camelcase
		updateQueryString( { store_currency_is: 'usd' }, '/', {} );

		useTransactions.mockReturnValue( {
			transactions: getMockTransactions().filter(
				( txn ) => txn.currency === getQuery().store_currency_is
			),
			isLoading: false,
		} );

		useTransactionsSummary.mockReturnValue( {
			transactionsSummary: {
				count: 10,
				currency: 'usd',
				// eslint-disable-next-line camelcase
				store_currencies: [ 'usd' ],
				fees: 100,
				total: 1000,
				net: 900,
			},
			isLoading: false,
		} );

		const { container } = render( <TransactionsList /> );
		expect( container ).toMatchSnapshot();
	} );

	describe( 'CSV download', () => {
		beforeEach( () => {
			useTransactions.mockReturnValue( {
				transactions: getMockTransactions(),
				isLoading: false,
			} );

			useTransactionsSummary.mockReturnValue( {
				transactionsSummary: {
					count: 10,
					currency: 'usd',
					// eslint-disable-next-line camelcase
					store_currencies: [ 'eur', 'usd' ],
					fees: 100,
					total: 1000,
					net: 900,
				},
				isLoading: false,
			} );
		} );

		afterEach( () => {
			jest.resetAllMocks();
		} );

		afterAll( () => {
			jest.restoreAllMocks();
		} );

		test( 'should render expected columns in CSV when the download button is clicked', () => {
			const { getByRole } = render( <TransactionsList /> );
			getByRole( 'button', { name: 'Download' } ).click();

			const expected = [
				'"Transaction Id"',
				'"Date / Time"',
				'Type',
				'Amount',
				'Fees',
				'Net',
				'"Order #"',
				'Source',
				'Customer',
				'Email',
				'Country',
				'"Risk level"',
				'Deposit',
			];

			// checking if columns in CSV are rendered correctly
			expect(
				downloadCSVFile.mock.calls[ 0 ][ 1 ]
					.split( '\n' )[ 0 ]
					.split( ',' )
			).toEqual( expected );
		} );
	} );
} );
