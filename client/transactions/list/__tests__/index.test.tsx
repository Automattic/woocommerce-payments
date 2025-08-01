/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import user from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import { getQuery, updateQueryString } from '@woocommerce/navigation';
import { useUserPreferences } from '@woocommerce/data';
import { PAYMENT_METHOD_BRANDS } from 'wcpay/constants/payment-method';

/**
 * Internal dependencies
 */
import { getUserTimeZone } from 'jest-utils/timezone';
import { TransactionsList } from '..';
import { useTransactions, useTransactionsSummary } from 'data';
import type { Transaction } from 'data/transactions/hooks';

jest.mock( '@woocommerce/data', () => {
	const actualModule = jest.requireActual( '@woocommerce/data' );

	return {
		...actualModule,
		useUserPreferences: jest.fn(),
	};
} );

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

// Workaround for mocking @wordpress/data.
// See https://github.com/WordPress/gutenberg/issues/15031
jest.mock( '@wordpress/data', () => ( {
	createRegistryControl: jest.fn(),
	dispatch: jest.fn( () => ( {
		setIsMatching: jest.fn(),
		onLoad: jest.fn(),
		onHistoryChange: jest.fn(),
	} ) ),
	registerStore: jest.fn(),
	select: jest.fn(),
	useDispatch: jest.fn( () => ( { createNotice: jest.fn() } ) ),
	withDispatch: jest.fn( () => jest.fn() ),
	withSelect: jest.fn( () => jest.fn() ),
} ) );

jest.mock( 'data/index', () => ( {
	useTransactions: jest.fn(),
	useTransactionsSummary: jest.fn(),
} ) );

// Mock dateI18n
jest.mock( '@wordpress/date', () => ( {
	dateI18n: jest.fn( ( format, date ) => {
		return jest
			.requireActual( '@wordpress/date' )
			.dateI18n( format, date, 'UTC' ); // Ensure UTC is used
	} ),
} ) );

const mockApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

const mockUseTransactions = useTransactions as jest.MockedFunction<
	typeof useTransactions
>;

const mockUseTransactionsSummary = useTransactionsSummary as jest.MockedFunction<
	typeof useTransactionsSummary
>;

const mockUseUserPreferences = useUserPreferences as jest.MockedFunction<
	typeof useUserPreferences
>;

declare const global: {
	wcpaySettings: {
		accountStatus: {
			country: string;
		};
		isSubscriptionsActive: boolean;
		featureFlags: {
			customSearch: boolean;
		};
		zeroDecimalCurrencies: string[];
		currentUserEmail: string;
		connect: {
			country: string;
		};
		currencyData: {
			[ key: string ]: {
				code: string;
				symbol: string;
				symbolPosition: string;
				thousandSeparator: string;
				decimalSeparator: string;
				precision: number;
			};
		};
	};
	wooPaymentsPaymentMethodsConfig: Record< string, { title: string } >;
};

const getMockTransactions: () => Transaction[] = () => [
	{
		available_on: '',
		transaction_id: 'txn_j23jda9JJa',
		date: '2020-01-02 17:46:02',
		type: 'refund',
		source: PAYMENT_METHOD_BRANDS.VISA,
		order: {
			id: 123,
			number: 'custom-123',
			url: 'https://example.com/order/123',
			// eslint-disable-next-line camelcase
			customer_url: 'https://example.com/customer/my-name',
			customer_name: '',
			customer_email: '',
			ip_address: '127.0.0.1',
		},
		channel: 'online',
		source_identifier: '1234',
		customer_name: 'Another customer',
		customer_email: 'another@customer.com',
		customer_country: 'US',
		charge_id: 'ch_j23w39dsajda',
		amount: 1000,
		fees: 50,
		net: 950,
		currency: 'usd',
		customer_amount: 1000,
		customer_currency: 'usd',
		risk_level: 0,
		deposit_id: undefined,
		loan_id: undefined,
		payment_intent_id: 'pi_mock',
	},
	{
		transaction_id: 'txn_oa9kaKaa8',
		date: '2020-01-05 04:22:59',
		available_on: '2020-01-07 00:00:00',
		type: 'charge',
		source: PAYMENT_METHOD_BRANDS.MASTERCARD,
		order: {
			id: 123,
			number: 'custom-125',
			url: 'https://example.com/order/125',
			// eslint-disable-next-line camelcase
			customer_url: 'https://example.com/customer/my-name',
			customer_name: '',
			customer_email: '',
			ip_address: '127.0.0.1',
		},
		channel: 'online',
		source_identifier: '1234',
		customer_name: 'My name',
		customer_email: 'a@b.com',
		customer_country: 'US',
		charge_id: 'ch_j239jda',
		amount: 1500,
		fees: 50,
		net: 1450,
		currency: 'usd',
		customer_amount: 3000,
		customer_currency: 'mok',
		risk_level: 2,
		deposit_id: 'po_mock',
		loan_id: 'flxln_mock',
		payment_intent_id: 'pi_mock',
	},
	{
		available_on: '',
		transaction_id: 'txn_mmtr89gjh5',
		date: '2020-01-02 19:55:05',
		type: 'charge',
		source: PAYMENT_METHOD_BRANDS.VISA,
		order: {
			id: 123,
			number: 'custom-335',
			url: 'https://example.com/order/335',
			// eslint-disable-next-line camelcase
			customer_url: 'https://example.com/customer/my-name',
			customer_name: '',
			customer_email: '',
			ip_address: '127.0.0.1',
		},
		channel: 'in_person',
		source_identifier: '1234',
		source_device: 'ios',
		customer_name: 'Best customer',
		customer_email: 'best@customer.com',
		customer_country: 'US',
		charge_id: 'ch_rskkmpe46yu',
		amount: 2000,
		fees: 75,
		net: 1925,
		currency: 'usd',
		customer_amount: 2000,
		customer_currency: 'usd',
		risk_level: 0,
		deposit_id: undefined,
		loan_id: undefined,
	},
];

describe( 'Transactions list', () => {
	beforeEach( () => {
		jest.clearAllMocks();

		// the query string is preserved across tests, so we need to reset it
		updateQueryString( {}, '/', {} );

		mockUseUserPreferences.mockReturnValue( {
			updateUserPreferences: jest.fn(),
			wc_payments_transactions_hidden_columns: '',
			isRequesting: false,
		} as any );

		global.wcpaySettings = {
			accountStatus: {
				country: 'US',
			},
			featureFlags: {
				customSearch: true,
			},
			isSubscriptionsActive: false,
			zeroDecimalCurrencies: [],
			currentUserEmail: 'mock@example.com',
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

		global.wooPaymentsPaymentMethodsConfig = {};

		window.wcpaySettings.dateFormat = 'M j, Y';
		window.wcpaySettings.timeFormat = 'g:iA';
	} );

	test( 'renders correctly when filtered by payout', () => {
		mockUseTransactions.mockReturnValue( {
			transactions: getMockTransactions().filter(
				( txn: Transaction ) => 'po_mock' === txn.deposit_id
			),
			transactionsError: undefined,
			isLoading: false,
		} );

		mockUseTransactionsSummary.mockReturnValue( {
			transactionsSummary: {
				count: 3,
				currency: 'usd',
				store_currencies: [ 'usd' ],
				fees: 30,
				total: 300,
				net: 270,
			},
			isLoading: false,
		} );

		const { container, getByRole } = render(
			<TransactionsList depositId="po_mock" />
		);
		expect( container ).toMatchSnapshot();
		getByRole( 'heading', { name: 'Transactions' } );
		expect( mockUseTransactions.mock.calls[ 0 ][ 1 ] ).toBe( 'po_mock' );
	} );

	describe( 'when not filtered by payout', () => {
		let container: Element;
		let rerender: ( ui: React.ReactElement ) => void;
		beforeEach( () => {
			mockUseTransactions.mockReturnValue( {
				transactions: getMockTransactions(),
				isLoading: false,
				transactionsError: undefined,
			} );

			mockUseTransactionsSummary.mockReturnValue( {
				transactionsSummary: {
					count: 10,
					currency: 'usd',
					store_currencies: [ 'usd' ],
					fees: 100,
					total: 1000,
					net: 900,
				},
				isLoading: false,
			} );

			( { container, rerender } = render( <TransactionsList /> ) );
		} );

		function expectSortingToBe( field: string, direction: string ) {
			expect( getQuery().orderby ).toEqual( field );
			expect( getQuery().order ).toEqual( direction );
			const useTransactionsCall =
				mockUseTransactions.mock.calls[
					mockUseTransactions.mock.calls.length - 1
				];
			expect( useTransactionsCall[ 0 ].orderby ).toEqual( field );
			expect( useTransactionsCall[ 0 ].order ).toEqual( direction );
		}

		async function sortBy( field: string ) {
			await user.click( screen.getByRole( 'button', { name: field } ) );
			rerender( <TransactionsList /> );
		}

		test( 'renders correctly', () => {
			expect( container ).toMatchSnapshot();
		} );

		test( 'sorts by default field date', async () => {
			await sortBy( 'Date and time' );
			expectSortingToBe( 'date', 'asc' );

			await sortBy( 'Date and time' );
			expectSortingToBe( 'date', 'desc' );
		} );

		test( 'sorts by amount', async () => {
			await sortBy( 'Amount in Payout Currency' );
			expectSortingToBe( 'amount', 'desc' );

			await sortBy( 'Amount in Payout Currency' );
			expectSortingToBe( 'amount', 'asc' );
		} );

		test( 'sorts by fees', async () => {
			await sortBy( 'Fees' );
			expectSortingToBe( 'fees', 'desc' );

			await sortBy( 'Fees' );
			expectSortingToBe( 'fees', 'asc' );
		} );

		test( 'sorts by net', async () => {
			await sortBy( 'Net' );
			expectSortingToBe( 'net', 'desc' );

			await sortBy( 'Net' );
			expectSortingToBe( 'net', 'asc' );
		} );

		test( 'renders table summary only when the transactions summary data is available', () => {
			mockUseTransactionsSummary.mockReturnValue( {
				transactionsSummary: {},
				isLoading: true,
			} );

			( { container } = render( <TransactionsList /> ) );
			let tableSummary = container.querySelectorAll(
				'.woocommerce-table__summary'
			);
			expect( tableSummary ).toHaveLength( 0 );

			mockUseTransactionsSummary.mockReturnValue( {
				transactionsSummary: {
					count: 10,
					currency: 'usd',
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

		test( 'renders table summary only when the transactions summary data is available with a single transaction', () => {
			mockUseTransactionsSummary.mockReturnValue( {
				transactionsSummary: {},
				isLoading: true,
			} );

			( { container } = render( <TransactionsList /> ) );
			let tableSummary = container.querySelectorAll(
				'.woocommerce-table__summary'
			);
			expect( tableSummary ).toHaveLength( 0 );

			mockUseTransactionsSummary.mockReturnValue( {
				transactionsSummary: {
					count: 1,
					currency: 'usd',
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
			expect( container ).toMatchSnapshot();
		} );

		test( 'renders table with a TTP source device', () => {
			( { container } = render( <TransactionsList /> ) );
			const ttpLogo = container.querySelectorAll(
				'.woocommerce-taptopay__icon'
			);

			expect( ttpLogo ).toHaveLength( 1 );
		} );
	} );

	test( 'subscription column renders correctly', () => {
		global.wcpaySettings.isSubscriptionsActive = true;

		const mockTransactions = getMockTransactions();
		mockTransactions[ 0 ].order.subscriptions = [
			{
				number: 'custom-246',
				url: 'https://example.com/subscription/246',
			},
		];
		mockTransactions[ 1 ].order.subscriptions = [];

		mockUseTransactions.mockReturnValue( {
			transactions: mockTransactions,
			isLoading: false,
			transactionsError: undefined,
		} );

		mockUseTransactionsSummary.mockReturnValue( {
			transactionsSummary: {
				count: 10,
				currency: 'usd',
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
		mockUseTransactions.mockReturnValue( {
			transactions: getMockTransactions(),
			isLoading: false,
			transactionsError: undefined,
		} );

		mockUseTransactionsSummary.mockReturnValue( {
			transactionsSummary: {
				count: 10,
				currency: 'usd',
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
		updateQueryString( { store_currency_is: 'usd' }, '/', {} );

		mockUseTransactions.mockReturnValue( {
			transactions: getMockTransactions().filter(
				( txn ) => txn.currency === getQuery().store_currency_is
			),
			isLoading: false,
			transactionsError: undefined,
		} );

		mockUseTransactionsSummary.mockReturnValue( {
			transactionsSummary: {
				count: 10,
				currency: 'usd',
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

	test( 'renders columns hidden as per user preferences', () => {
		mockUseTransactions.mockReturnValue( {
			transactions: getMockTransactions(),
			isLoading: false,
			transactionsError: undefined,
		} );

		mockUseTransactionsSummary.mockReturnValue( {
			transactionsSummary: {
				count: 10,
				currency: 'usd',
				store_currencies: [ 'usd' ],
				fees: 100,
				total: 1000,
				net: 900,
			},
			isLoading: false,
		} );

		mockUseUserPreferences.mockReturnValue( {
			wc_payments_transactions_hidden_columns: [ 'fees' ],
		} as any );

		const { getByRole, queryByRole } = render( <TransactionsList /> );

		// Fees column should not be visible, as it is hidden in user preferences.
		expect(
			queryByRole( 'columnheader', {
				name: /Fees/i,
			} )
		).not.toBeInTheDocument();

		// Channel column should be visible, as it is not hidden in user preferences.
		expect(
			getByRole( 'columnheader', {
				name: /Channel/i,
			} )
		).toBeInTheDocument();
	} );

	describe( 'CSV download', () => {
		beforeEach( () => {
			mockUseTransactions.mockReturnValue( {
				transactions: getMockTransactions(),
				isLoading: false,
				transactionsError: undefined,
			} );

			mockUseTransactionsSummary.mockReturnValue( {
				transactionsSummary: {
					count: 2,
					currency: 'usd',
					store_currencies: [ 'eur', 'usd' ],
					fees: 100,
					total: 1000,
					net: 900,
				},
				isLoading: false,
			} );
		} );

		// Test also makes sure that the currentUserEmail is included in the path in the API call.
		test( 'should fetch export after confirmation when download button is selected for unfiltered exports larger than 10000.', async () => {
			window.confirm = jest.fn( () => true );
			mockUseTransactionsSummary.mockReturnValue( {
				transactionsSummary: {
					count: 11000,
				},
				isLoading: false,
			} );

			const { getByRole } = render( <TransactionsList /> );

			getByRole( 'button', { name: 'Export' } ).click();

			expect( window.confirm ).toHaveBeenCalledTimes( 1 );
			expect( window.confirm ).toHaveBeenCalledWith(
				"You are about to export 11000 transactions. If you'd like to reduce the size of your export, you can use one or more filters. Would you like to continue?"
			);

			await waitFor( () => {
				expect( mockApiFetch ).toHaveBeenCalledTimes( 1 );
			} );
		} );

		test( 'should not fetch export after cancel when download button is selected for unfiltered exports larger than 10000.', async () => {
			window.confirm = jest.fn( () => false );
			mockUseTransactionsSummary.mockReturnValue( {
				transactionsSummary: {
					count: 11000,
				},
				isLoading: false,
			} );

			const { getByRole } = render( <TransactionsList /> );

			getByRole( 'button', { name: 'Export' } ).click();

			expect( window.confirm ).toHaveBeenCalledTimes( 1 );
			expect( window.confirm ).toHaveBeenCalledWith(
				"You are about to export 11000 transactions. If you'd like to reduce the size of your export, you can use one or more filters. Would you like to continue?"
			);

			await waitFor( () =>
				expect( mockApiFetch ).not.toHaveBeenCalled()
			);
		} );

		// Test also makes sure that the currentUserEmail is included in the path in the API call.
		test( 'should fetch export with deposit_id if payouts transactions page', async () => {
			window.confirm = jest.fn( () => true );

			mockUseTransactionsSummary.mockReturnValue( {
				transactionsSummary: {
					count: 101,
					currency: 'usd',
					store_currencies: [ 'usd' ],
					fees: 30,
					total: 300,
					net: 270,
				},
				isLoading: false,
			} );

			const { getByRole } = render(
				<TransactionsList depositId="po_mock" />
			);

			getByRole( 'button', { name: 'Export' } ).click();

			await waitFor( () => {
				expect( mockApiFetch ).toHaveBeenCalledTimes( 1 );
				expect( mockApiFetch ).toHaveBeenCalledWith( {
					method: 'POST',
					path: `/wc/v3/payments/transactions/download?user_email=mock%40example.com&deposit_id=po_mock&user_timezone=${ encodeURIComponent(
						getUserTimeZone()
					) }&locale=en_US`,
				} );
			} );
		} );
	} );
} );
