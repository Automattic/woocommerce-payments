/** @format */
/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import { downloadCSVFile } from '@woocommerce/csv-export';
import os from 'os';

/**
 * Internal dependencies
 */
import DisputesList from '..';
import { useDisputes, useDisputesSummary } from 'data/index';
import { formatDate, getUnformattedAmount } from 'wcpay/utils/test-utils';
import React from 'react';
import {
	DisputeReason,
	DisputeStatus,
	CachedDispute,
} from 'wcpay/types/disputes';

declare const global: {
	wcpaySettings: {
		zeroDecimalCurrencies: string[];
	};
};

jest.mock( 'data/index', () => ( {
	useDisputes: jest.fn(),
	useDisputesSummary: jest.fn(),
} ) );

const mockUseDisputes = useDisputes as jest.MockedFunction<
	typeof useDisputes
>;

const mockUseDisputesSummary = useDisputesSummary as jest.MockedFunction<
	typeof useDisputesSummary
>;

jest.mock( '@woocommerce/csv-export', () => {
	const actualModule = jest.requireActual( '@woocommerce/csv-export' );

	return {
		...actualModule,
		downloadCSVFile: jest.fn(),
	};
} );

const mockDownloadCSVFile = downloadCSVFile as jest.MockedFunction<
	typeof downloadCSVFile
>;

const mockDisputes = [
	{
		wcpay_disputes_cache_id: 4,
		stripe_account_id: 'acct_test',
		dispute_id: 'dp_asdfghjkl',
		charge_id: 'ch_mock',
		amount: 1000,
		currency: 'usd',
		reason: 'fraudulent' as DisputeReason,
		source: 'visa',
		order_number: 1,
		customer_name: 'Mock customer',
		customer_email: 'mock@customer.net',
		customer_country: 'US',
		status: 'needs_response' as DisputeStatus,
		created: '2019-11-01 23:59:59',
		due_by: '2019-11-08 02:46:00',
		order: {
			number: '1',
			url: 'http://test.local/order/1',
		},
	} as CachedDispute,
	{
		// dispute without order or charge information
		wcpay_disputes_cache_id: 5,
		stripe_account_id: 'acct_test',
		dispute_id: 'dp_zxcvbnm',
		charge_id: 'ch_mock',
		amount: 1050,
		currency: 'usd',
		reason: 'general' as DisputeReason,
		order_number: 2,
		status: 'under_review' as DisputeStatus,
		created: '2019-10-30 09:14:33',
		due_by: '2019-11-06 23:00:59',
	} as CachedDispute,
];

describe( 'Disputes list', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
		};
	} );

	test( 'renders correctly', () => {
		mockUseDisputes.mockReturnValue( {
			isLoading: false,
			disputes: mockDisputes,
		} );

		mockUseDisputesSummary.mockReturnValue( {
			isLoading: false,
			disputesSummary: {
				count: 25,
			},
		} );

		const { container: list } = render( <DisputesList /> );
		expect( list ).toMatchSnapshot();
	} );

	describe( 'Download button', () => {
		test( 'renders when there are one or more disputes', () => {
			mockUseDisputes.mockReturnValue( {
				disputes: mockDisputes,
				isLoading: false,
			} );

			const { queryByRole } = render( <DisputesList /> );
			const button = queryByRole( 'button', { name: 'Download' } );

			expect( button ).not.toBeNull();
		} );

		test( 'does not render when there are no disputes', () => {
			mockUseDisputes.mockReturnValue( {
				disputes: [],
				isLoading: false,
			} );

			const { queryByRole } = render( <DisputesList /> );
			const button = queryByRole( 'button', { name: 'Download' } );

			expect( button ).toBeNull();
		} );
	} );

	describe( 'CSV download', () => {
		beforeEach( () => {
			mockUseDisputes.mockReturnValue( {
				disputes: mockDisputes,
				isLoading: false,
			} );

			mockUseDisputesSummary.mockReturnValue( {
				isLoading: false,
				disputesSummary: {
					count: 25,
				},
			} );
		} );

		afterEach( () => {
			jest.resetAllMocks();
		} );

		afterAll( () => {
			jest.restoreAllMocks();
		} );

		test( 'should render expected columns in CSV when the download button is clicked ', () => {
			const { getByRole } = render( <DisputesList /> );
			getByRole( 'button', { name: 'Download' } ).click();

			const expected = [
				'"Dispute Id"',
				'Amount',
				'Currency',
				'Status',
				'Reason',
				'Source',
				'"Order #"',
				'Customer',
				'Email',
				'Country',
				'"Disputed on"',
				'"Respond by"',
			];

			const csvContent = mockDownloadCSVFile.mock.calls[ 0 ][ 1 ];
			const csvHeaderRow = csvContent.split( os.EOL )[ 0 ].split( ',' );
			expect( csvHeaderRow ).toEqual( expected );
		} );

		test( 'should match the visible rows', () => {
			const { getByRole, getAllByRole } = render( <DisputesList /> );
			getByRole( 'button', { name: 'Download' } ).click();

			const csvContent = mockDownloadCSVFile.mock.calls[ 0 ][ 1 ];
			const csvRows = csvContent.split( os.EOL );
			const displayRows = getAllByRole( 'row' );

			expect( csvRows.length ).toEqual( displayRows.length );

			const csvFirstDispute = csvRows[ 1 ].split( ',' );
			const displayFirstDispute = Array.from(
				displayRows[ 1 ].querySelectorAll( 'td' )
			).map( ( td ) => td.textContent );

			// Note:
			//
			// 1. CSV and display indexes are off by 2 because:
			// 		- the first field in CSV is dispute id, which is missing in display.
			// 		- the third field in CSV is currency, which is missing in display (it's displayed in "amount" column).
			//
			// 2. The indexOf check in amount's expect is because the amount in CSV may not contain
			//    trailing zeros as in the display amount.
			//
			expect(
				getUnformattedAmount( displayFirstDispute[ 0 ] ).indexOf(
					csvFirstDispute[ 1 ]
				)
			).not.toBe( -1 ); // amount

			expect( csvFirstDispute[ 2 ] ).toBe( 'usd' );

			expect( csvFirstDispute[ 3 ] ).toBe(
				`"${ displayFirstDispute[ 1 ] }"`
			); //status

			expect( csvFirstDispute[ 4 ] ).toBe( displayFirstDispute[ 2 ] ); // reason

			expect( csvFirstDispute[ 6 ] ).toBe( displayFirstDispute[ 4 ] ); // order

			expect( csvFirstDispute[ 7 ] ).toBe(
				`"${ displayFirstDispute[ 5 ] }"`
			); // customer

			expect( formatDate( csvFirstDispute[ 10 ], 'Y-m-d' ) ).toBe(
				formatDate( displayFirstDispute[ 6 ], 'Y-m-d' )
			); // date disputed on

			expect( formatDate( csvFirstDispute[ 11 ], 'Y-m-d / g:iA' ) ).toBe(
				formatDate( displayFirstDispute[ 7 ], 'Y-m-d / g:iA' )
			); // date respond by
		} );
	} );
} );
