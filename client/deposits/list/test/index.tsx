/** @format */

/**
 * External dependencies
 */
import { render, waitFor } from '@testing-library/react';
import { updateQueryString } from '@woocommerce/navigation';
import { downloadCSVFile } from '@woocommerce/csv-export';
import apiFetch from '@wordpress/api-fetch';

import os from 'os';

/**
 * Internal dependencies
 */
import { DepositsList } from '../';
import { useDeposits, useDepositsSummary } from 'wcpay/data';
import { formatDate, getUnformattedAmount } from 'wcpay/utils/test-utils';
import {
	CachedDeposit,
	CachedDeposits,
	DepositsSummary,
} from 'wcpay/types/deposits';
import React from 'react';

jest.mock( 'wcpay/data', () => ( {
	useDeposits: jest.fn(),
	useDepositsSummary: jest.fn(),
} ) );

jest.mock( '@woocommerce/csv-export', () => {
	const actualModule = jest.requireActual( '@woocommerce/csv-export' );

	return {
		...actualModule,
		downloadCSVFile: jest.fn(),
	};
} );

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const mockDeposits = [
	{
		id: 'po_mock1',
		date: '2020-01-02 17:46:02',
		type: 'deposit',
		amount: 2000,
		status: 'paid',
		bankAccount: 'MOCK BANK •••• 1234 (USD)',
		currency: 'USD',
	} as CachedDeposit,
	{
		id: 'po_mock2',
		date: '2020-01-03 17:46:02',
		type: 'withdrawal',
		amount: 3000,
		status: 'pending',
		bankAccount: 'MOCK BANK •••• 1234 (USD)',
		currency: 'USD',
	} as CachedDeposit,
];

declare const global: {
	wcpaySettings: {
		zeroDecimalCurrencies: string[];
		currentUserEmail: string;
		currencyData: Record< string, any >;
		connect: {
			country: string;
		};
	};
};

// Workaround for mocking @wordpress/data.
// See https://github.com/WordPress/gutenberg/issues/15031
jest.mock( '@wordpress/data', () => ( {
	createRegistryControl: jest.fn(),
	dispatch: jest.fn( () => ( { setIsMatching: jest.fn() } ) ),
	registerStore: jest.fn(),
	select: jest.fn(),
	useDispatch: jest.fn( () => ( { createNotice: jest.fn() } ) ),
	withDispatch: jest.fn( () => jest.fn() ),
	withSelect: jest.fn( () => jest.fn() ),
} ) );

const mockApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

const mockUseDeposits = useDeposits as jest.MockedFunction<
	typeof useDeposits
>;

const mockUseDepositsSummary = useDepositsSummary as jest.MockedFunction<
	typeof useDepositsSummary
>;

const mockDownloadCSVFile = downloadCSVFile as jest.MockedFunction<
	typeof downloadCSVFile
>;

describe( 'Deposits list', () => {
	beforeEach( () => {
		jest.clearAllMocks();

		// the query string is preserved across tests, so we need to reset it
		updateQueryString( {}, '/', {} );

		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
			connect: {
				country: 'US',
			},
			currentUserEmail: 'mock@example.com',
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

	// this also covers structural test for single currency.
	test( 'renders correctly with multiple currencies', () => {
		mockUseDeposits.mockReturnValue( {
			deposits: mockDeposits,
			depositsCount: 2,
			isLoading: false,
		} );

		mockUseDepositsSummary.mockReturnValue( {
			depositsSummary: {
				count: 2,
				total: 5000,
				store_currencies: [ 'usd', 'eur' ],
				currency: 'usd',
			} as DepositsSummary,
			isLoading: false,
		} );

		const { container } = render( <DepositsList /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders correctly a single deposit', () => {
		mockUseDeposits.mockReturnValue( {
			deposits: mockDeposits,
			depositsCount: 1,
			isLoading: false,
		} );

		mockUseDepositsSummary.mockReturnValue( {
			depositsSummary: {
				count: 1,
				total: 5000,
				store_currencies: [ 'usd' ],
				currency: 'usd',
			} as DepositsSummary,
			isLoading: false,
		} );

		const { container } = render( <DepositsList /> );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders table summary only when the deposits summary data is available', () => {
		mockUseDeposits.mockReturnValue( {
			deposits: mockDeposits,
			isLoading: false,
		} as CachedDeposits );

		mockUseDepositsSummary.mockReturnValue( {
			depositsSummary: {
				count: 30,
			} as DepositsSummary,
			isLoading: true,
		} );

		let { container } = render( <DepositsList /> );
		let tableSummary = container.querySelectorAll(
			'.woocommerce-table__summary'
		);

		expect( tableSummary ).toHaveLength( 0 );

		mockUseDepositsSummary.mockReturnValue( {
			depositsSummary: {
				count: 2,
				total: 100,
			} as DepositsSummary,
			isLoading: false,
		} );

		( { container } = render( <DepositsList /> ) );
		tableSummary = container.querySelectorAll(
			'.woocommerce-table__summary'
		);

		expect( tableSummary ).toHaveLength( 1 );
	} );

	describe( 'Download button', () => {
		test( 'renders when there are one or more deposits', () => {
			mockUseDeposits.mockReturnValue( {
				deposits: mockDeposits,
				isLoading: false,
			} as CachedDeposits );

			const { queryByRole } = render( <DepositsList /> );
			const button = queryByRole( 'button', { name: 'Download' } );

			expect( button ).not.toBeNull();
		} );

		test( 'does not render when there are no deposits', () => {
			mockUseDeposits.mockReturnValue( {
				deposits: [],
				isLoading: false,
				depositsCount: 0,
			} as CachedDeposits );

			const { queryByRole } = render( <DepositsList /> );
			const button = queryByRole( 'button', { name: 'Download' } );

			expect( button ).toBeNull();
		} );
	} );

	describe( 'CSV download', () => {
		beforeEach( () => {
			jest.restoreAllMocks();
			mockUseDeposits.mockReturnValue( {
				deposits: mockDeposits,
				depositsCount: 2,
				isLoading: false,
			} );
			mockUseDepositsSummary.mockReturnValue( {
				depositsSummary: {
					count: 2,
					total: 5000,
				} as DepositsSummary,
				isLoading: false,
			} );
		} );

		test( 'should render expected columns in CSV when the download button is clicked', () => {
			const { getByRole } = render( <DepositsList /> );
			getByRole( 'button', { name: 'Download' } ).click();

			const expected = [
				'"Deposit Id"',
				'Date',
				'Type',
				'Amount',
				'Status',
				'"Bank account"',
			];

			const csvContent = mockDownloadCSVFile.mock.calls[ 0 ][ 1 ];
			const csvHeaderRow = csvContent.split( os.EOL )[ 0 ].split( ',' );
			expect( csvHeaderRow ).toEqual( expected );
		} );

		test( 'should match the visible rows', () => {
			const { getByRole, getAllByRole } = render( <DepositsList /> );
			getByRole( 'button', { name: 'Download' } ).click();

			const csvContent = mockDownloadCSVFile.mock.calls[ 0 ][ 1 ];
			const csvRows = csvContent.split( os.EOL );
			const displayRows = getAllByRole( 'row' );

			expect( csvRows.length ).toEqual( displayRows.length );

			const csvFirstDeposit = csvRows[ 1 ].split( ',' );
			const displayFirstDeposit = Array.from(
				displayRows[ 1 ].querySelectorAll( 'td' )
			).map( ( td ) => td.textContent );

			// Note:
			//
			// 1. CSV and display indexes are off by 1 because the first field in CSV is deposit id,
			//    which is missing in display.
			//
			// 2. The indexOf check in amount's expect is because the amount in CSV may not contain
			//    trailing zeros as in the display amount.
			//
			expect( formatDate( csvFirstDeposit[ 1 ], 'M j, Y' ) ).toBe(
				displayFirstDeposit[ 0 ]
			); // date
			expect( csvFirstDeposit[ 2 ] ).toBe( displayFirstDeposit[ 1 ] ); // type
			expect(
				getUnformattedAmount( displayFirstDeposit[ 2 ] ).indexOf(
					csvFirstDeposit[ 3 ]
				)
			).not.toBe( -1 ); // amount
			expect( csvFirstDeposit[ 4 ] ).toBe( displayFirstDeposit[ 3 ] ); // status
			expect( csvFirstDeposit[ 5 ] ).toBe(
				`"${ displayFirstDeposit[ 4 ] }"`
			); // bank account
		} );

		test( 'should fetch export after confirmation when download button is selected for unfiltered exports larger than 1000.', async () => {
			window.confirm = jest.fn( () => true );
			mockUseDepositsSummary.mockReturnValue( {
				depositsSummary: {
					count: 1100,
					total: 50000,
				} as DepositsSummary,
				isLoading: false,
			} );

			const { getByRole } = render( <DepositsList /> );

			getByRole( 'button', { name: 'Download' } ).click();

			expect( window.confirm ).toHaveBeenCalledTimes( 1 );
			expect( window.confirm ).toHaveBeenCalledWith(
				"You are about to export 1100 deposits. If you'd like to reduce the size of your export, you can use one or more filters. Would you like to continue?"
			);

			await waitFor( () => {
				expect( mockApiFetch ).toHaveBeenCalledTimes( 1 );
				expect( mockApiFetch ).toHaveBeenCalledWith( {
					method: 'POST',
					path:
						'/wc/v3/payments/deposits/download?user_email=mock%40example.com',
				} );
			} );
		} );

		test( 'should not fetch export after cancel when download button is selected for unfiltered exports larger than 1000.', async () => {
			window.confirm = jest.fn( () => false );
			mockUseDepositsSummary.mockReturnValue( {
				depositsSummary: {
					count: 1100,
					total: 50000,
				} as DepositsSummary,
				isLoading: false,
			} );

			const { getByRole } = render( <DepositsList /> );

			getByRole( 'button', { name: 'Download' } ).click();

			expect( window.confirm ).toHaveBeenCalledTimes( 1 );
			expect( window.confirm ).toHaveBeenCalledWith(
				"You are about to export 1100 deposits. If you'd like to reduce the size of your export, you can use one or more filters. Would you like to continue?"
			);

			await waitFor( () =>
				expect( mockApiFetch ).not.toHaveBeenCalled()
			);
		} );
	} );
} );
