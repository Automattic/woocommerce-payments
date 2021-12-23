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
import { DisputeReason, DisputeStatus } from 'wcpay/types/disputes';

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
		id: 'dp_asdfghjkl',
		amount: 1000,
		currency: 'usd',
		created: 1572590800,
		evidence_details: {
			due_by: 1573199200,
		},
		reason: 'fraudulent' as DisputeReason,
		status: 'needs_response' as DisputeStatus,
		charge: {
			id: 'ch_mock',
			payment_method_details: {
				card: {
					brand: 'visa',
				},
			},
			billing_details: {
				name: 'Mock customer',
				email: 'mock@customer.net',
				address: {
					country: 'US',
				},
			},
		},
		order: {
			number: '1',
			url: 'http://test.local/order/1',
			customer_url: '',
			subscriptions: [],
		},
	},
	{
		id: 'dp_zxcvbnm',
		amount: 1050,
		currency: 'usd',
		created: 1572480800,
		evidence_details: {
			due_by: 1573099200,
		},
		reason: 'general' as DisputeReason,
		status: 'warning_under_review' as DisputeStatus,
		// dispute without order or charge information
	},
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
			// 1. CSV and display indexes are off by 1 because the first field in CSV is dispute id,
			//    which is missing in display.
			//
			// 2. The indexOf check in amount's expect is because the amount in CSV may not contain
			//    trailing zeros as in the display amount.
			//

			expect(
				getUnformattedAmount( displayFirstDispute[ 0 ] ).indexOf(
					csvFirstDispute[ 1 ]
				)
			).not.toBe( -1 ); // amount

			expect( csvFirstDispute[ 2 ] ).toBe(
				`"${ displayFirstDispute[ 1 ] }"`
			); //status

			expect( csvFirstDispute[ 3 ] ).toBe( displayFirstDispute[ 2 ] ); // reason

			expect( csvFirstDispute[ 5 ] ).toBe( displayFirstDispute[ 4 ] ); // order

			expect( csvFirstDispute[ 6 ] ).toBe(
				`"${ displayFirstDispute[ 5 ] }"`
			); // customer

			expect( formatDate( csvFirstDispute[ 9 ], 'Y-m-d' ) ).toBe(
				formatDate( displayFirstDispute[ 6 ], 'Y-m-d' )
			); // date disputed on

			expect( formatDate( csvFirstDispute[ 10 ], 'Y-m-d / g:iA' ) ).toBe(
				formatDate( displayFirstDispute[ 7 ], 'Y-m-d / g:iA' )
			); // date respond by
		} );
	} );
} );
