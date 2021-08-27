/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import { updateQueryString } from '@woocommerce/navigation';
import { downloadCSVFile } from '@woocommerce/csv-export';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import os from 'os';

/**
 * Internal dependencies
 */
import { DepositsList } from '../';
import { useDeposits, useDepositsSummary } from 'wcpay/data';

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

			const { container } = render( <DepositsList /> );
			const downloadButton = container.querySelectorAll(
				'.woocommerce-table__download-button'
			);

			expect( downloadButton ).toHaveLength( 1 );
		} );

		test( 'does not render when there are no deposits', () => {
			useDeposits.mockReturnValue( {
				deposits: [],
				isLoading: false,
			} );

			const { container } = render( <DepositsList /> );
			const downloadButton = container.querySelectorAll(
				'.woocommerce-table__download-button'
			);

			expect( downloadButton ).toHaveLength( 0 );
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
				'',
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
			const csvLines = csvContent.split( os.EOL );
			const cptRows = getAllByRole( 'row' );

			expect( csvLines.length ).toEqual( cptRows.length );

			const cptFirstDepositDate = cptRows[ 1 ].querySelector( 'td' )
				.textContent;
			const csvFirstDeposit = csvLines[ 1 ].split( ',' );
			const csvFirstDepositDate = csvFirstDeposit[ 1 ];
			const date = new Date( JSON.parse( csvFirstDepositDate ) );
			const csvFirstDepositFormattedDate = dateI18n(
				'M j, Y',
				moment.utc( date.getTime() ).toISOString(),
				true // TODO Change call to gmdateI18n and remove this deprecated param once WP 5.4 support ends.
			);

			expect( csvFirstDepositFormattedDate ).toEqual(
				cptFirstDepositDate
			);
		} );
	} );
} );
