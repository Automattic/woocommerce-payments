/** @format */
/**
 * External dependencies
 */
import * as React from 'react';
import { render, screen } from '@testing-library/react';
import { updateQueryString } from '@woocommerce/navigation';
import os from 'os';

/**
 * Internal dependencies
 */
import PaymentCardReaderChargeDetails from '../';
import { useCardReaderStats } from 'wcpay/data';
import { downloadCSVFile } from '@woocommerce/csv-export';

jest.mock( 'wcpay/data', () => ( {
	useCardReaderStats: jest.fn(),
} ) );

jest.mock( '@woocommerce/csv-export', () => {
	const actualModule = jest.requireActual( '@woocommerce/csv-export' );

	return {
		...actualModule,
		downloadCSVFile: jest.fn(),
	};
} );

const mockUseCardReaderStats = useCardReaderStats;
const mockDownloadCSVFile = downloadCSVFile;

describe( 'RenderPaymentCardReaderChargeDetails', () => {
	beforeEach( () => {
		jest.clearAllMocks();

		// the query string is preserved across tests, so we need to reset it
		updateQueryString( {}, '/', {} );

		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
		};
	} );

	test( 'renders without reader charges', () => {
		mockUseCardReaderStats.mockReturnValue( {
			readers: [],
			chargeError: false,
			isLoading: false,
		} );

		const { container } = render(
			<PaymentCardReaderChargeDetails
				chargeId={ 'mock_charge_id' }
				transactionId={ 'mock_transaction_id' }
			/>
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders reader charges', () => {
		const readers = [
			{
				reader_id: 1,
				count: 3,
				status: 'active',
				fee: { amount: 300, currency: 'usd' },
			},
			{
				reader_id: 2,
				count: 1,
				status: 'inactive',
				fee: { amount: 0, currency: 'usd' },
			},
		];

		mockUseCardReaderStats.mockReturnValue( {
			readers: readers,
			chargeError: false,
			isLoading: false,
		} );

		const { container } = render(
			<PaymentCardReaderChargeDetails
				chargeId={ 'mock_charge_id' }
				transactionId={ 'mock_transaction_id' }
			/>
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders loading state', () => {
		mockUseCardReaderStats.mockReturnValue( {
			readers: [],
			chargeError: false,
			isLoading: true,
		} );
		render(
			<PaymentCardReaderChargeDetails
				chargeId={ 'mock_charge_id' }
				transactionId={ 'mock_transaction_id' }
			/>
		);

		expect(
			screen.getByText( 'Your requested data is loading' )
		).toBeInTheDocument();
	} );

	test( 'loading error', () => {
		mockUseCardReaderStats.mockReturnValue( {
			readers: [],
			chargeError: new Error( 'test' ),
			isLoading: false,
		} );
		render(
			<PaymentCardReaderChargeDetails
				chargeId={ 'mock_charge_id' }
				transactionId={ 'mock_transaction_id' }
			/>
		);

		expect(
			screen.getByText( 'Readers details not loaded' )
		).toBeInTheDocument();
	} );

	test( 'should render expected columns in CSV when the download button is clicked', () => {
		const readers = [
			{
				reader_id: 1,
				count: 3,
				status: 'active',
				fee: { amount: 300, currency: 'usd' },
			},
		];

		mockUseCardReaderStats.mockReturnValue( {
			readers: readers,
			chargeError: false,
			isLoading: false,
		} );
		const { getByRole } = render(
			<PaymentCardReaderChargeDetails
				chargeId={ 'mock_charge_id' }
				transactionId={ 'mock_transaction_id' }
			/>
		);
		getByRole( 'button', { name: 'Download' } ).click();
		const expected = [ '"Reader id"', 'Status', 'Transactions', 'Fee' ];

		// checking if columns in CSV are rendered correctly
		expect(
			mockDownloadCSVFile.mock.calls[ 0 ][ 1 ]
				.split( '\n' )[ 0 ]
				.split( ',' )
		).toEqual( expected );
	} );

	test( 'should match the visible rows', () => {
		const readers = [
			{
				reader_id: 1,
				count: 3,
				status: 'active',
				fee: { amount: 300, currency: 'usd' },
			},
		];

		mockUseCardReaderStats.mockReturnValue( {
			readers: readers,
			chargeError: false,
			isLoading: false,
		} );
		const { getByRole, getAllByRole } = render(
			<PaymentCardReaderChargeDetails
				chargeId={ 'mock_charge_id' }
				transactionId={ 'mock_transaction_id' }
			/>
		);
		getByRole( 'button', { name: 'Download' } ).click();
		const csvContent = mockDownloadCSVFile.mock.calls[ 0 ][ 1 ];
		const csvRows = csvContent.split( os.EOL );
		const displayRows = getAllByRole( 'row' );
		expect( csvRows.length ).toEqual( displayRows.length );

		const csvFirstTransaction = csvRows[ 1 ].split( ',' );
		const displayFirstTransaction = Array.from(
			displayRows[ 1 ].querySelectorAll( 'td' )
		).map( ( td ) => td.textContent || '' );

		const displayFirstRowHead = Array.from(
			displayRows[ 1 ].querySelectorAll( 'th' )
		).map( ( th ) => th.textContent || '' );
		displayFirstTransaction.unshift( displayFirstRowHead[ 0 ] );

		expect( displayFirstTransaction[ 0 ] ).toBe( csvFirstTransaction[ 0 ] ); // reader id
		expect( displayFirstTransaction[ 1 ] ).toBe( csvFirstTransaction[ 1 ] ); // status
		expect( displayFirstTransaction[ 2 ] ).toBe( csvFirstTransaction[ 2 ] ); // count
		expect(
			parseFloat( getUnformattedAmount( displayFirstTransaction[ 3 ] ) )
		).toBe( parseFloat( csvFirstTransaction[ 3 ] ) ); // fee
	} );

	function getUnformattedAmount( formattedAmount ) {
		const amount = formattedAmount.replace( /[^0-9,.' ]/g, '' ).trim();
		return amount.replace( ',', '.' ); // Euro fix
	}
} );
