/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import { updateQueryString } from '@woocommerce/navigation';
import { downloadCSVFile } from '@woocommerce/csv-export';
import os from 'os';

/**
 * Internal dependencies
 */
import { DepositsList } from '../';
import { useDeposits, useDepositsSummary } from 'wcpay/data';
import { formatDate, getUnformattedAmount } from 'wcpay/utils/test-utils';

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

	describe( 'Download button', () => {
		test( 'renders when there are one or more deposits', () => {
			useDeposits.mockReturnValue( {
				deposits: mockDeposits,
				isLoading: false,
			} );

			const { queryByRole } = render( <DepositsList /> );
			const button = queryByRole( 'button', { name: 'Download' } );

			expect( button ).not.toBeNull();
		} );

		test( 'does not render when there are no deposits', () => {
			useDeposits.mockReturnValue( {
				deposits: [],
				isLoading: false,
			} );

			const { queryByRole } = render( <DepositsList /> );
			const button = queryByRole( 'button', { name: 'Download' } );

			expect( button ).toBeNull();
		} );
	} );

	describe( 'CSV download', () => {
		beforeEach( () => {
			useDeposits.mockReturnValue( {
				deposits: mockDeposits,
				depositsCount: 2,
				isLoading: false,
			} );
			useDepositsSummary.mockReturnValue( {
				depositsSummary: {
					count: 2,
					total: 5000,
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

			const csvContent = downloadCSVFile.mock.calls[ 0 ][ 1 ];
			const csvHeaderRow = csvContent.split( os.EOL )[ 0 ].split( ',' );
			expect( csvHeaderRow ).toEqual( expected );
		} );

		test( 'should match the visible rows', () => {
			const { getByRole, getAllByRole } = render( <DepositsList /> );
			getByRole( 'button', { name: 'Download' } ).click();

			const csvContent = downloadCSVFile.mock.calls[ 0 ][ 1 ];
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
	} );
} );
