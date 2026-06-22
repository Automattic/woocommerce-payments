/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { recordEvent } from 'tracks';

const mockCreateNotice = jest.fn();
const mockGetQuery = jest.fn();
const mockRequestReportExport = jest.fn();
const mockUseReportsFeesSummary = jest.fn();
const mockGetReportsFeesSummary = jest.fn();
const mockGetFeesCSVRequestURL = jest.fn();

jest.mock( 'tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

const mockRecordEvent = recordEvent as jest.MockedFunction<
	typeof recordEvent
>;

jest.mock( '@wordpress/data', () => ( {
	// Slice stores self-register on import; stub the registration APIs.
	createReduxStore: jest.fn(),
	register: jest.fn(),
	combineReducers: jest.fn(),
	useDispatch: () => ( { createNotice: mockCreateNotice } ),
	select: () => ( {
		getReportsFeesSummary: ( query: unknown ) =>
			mockGetReportsFeesSummary( query ),
	} ),
} ) );

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: () => mockGetQuery(),
} ) );

jest.mock( 'wcpay/hooks/use-report-export', () => ( {
	useReportExport: () => ( {
		isExportInProgress: false,
		requestReportExport: mockRequestReportExport,
	} ),
} ) );

jest.mock( 'wcpay/data/reports/hooks', () => ( {
	useReportsFeesSummary: ( query: unknown ) =>
		mockUseReportsFeesSummary( query ),
} ) );

jest.mock( 'wcpay/data/reports/resolvers', () => ( {
	feesDownloadEndpoint: '/wc/v3/payments/reports/fees/download',
	getFeesCSVRequestURL: ( args: unknown ) => mockGetFeesCSVRequestURL( args ),
} ) );

/**
 * Internal dependencies
 */
import { FeesExportButton } from '../fees-export-button';

type ExportRequestArgs = {
	onSuccess?: () => void;
	onError?: ( error: { reason: 'request' | 'timeout' } ) => void;
};

const getExportRequestArgs = () =>
	mockRequestReportExport.mock.calls[ 0 ][ 0 ] as ExportRequestArgs;

describe( 'FeesExportButton', () => {
	beforeAll( () => {
		const settings = globalThis as typeof globalThis & {
			wcpaySettings: { currentUserEmail: string };
			wcSettings: { locale: { userLocale: string } };
		};

		settings.wcpaySettings = {
			currentUserEmail: 'merchant@example.test',
		};
		settings.wcSettings = {
			locale: { userLocale: 'en_US' },
		};
		Object.assign( window, {
			wcpaySettings: settings.wcpaySettings,
			wcSettings: settings.wcSettings,
		} );
	} );

	beforeEach( () => {
		mockRecordEvent.mockReset();
		mockCreateNotice.mockReset();
		mockGetQuery.mockReset().mockReturnValue( {} );
		mockRequestReportExport.mockReset();
		mockUseReportsFeesSummary.mockReset().mockReturnValue( {
			feesSummary: { count: 42 },
			isLoading: false,
		} );
		mockGetReportsFeesSummary.mockReset().mockReturnValue( undefined );
		mockGetFeesCSVRequestURL
			.mockReset()
			.mockReturnValue( '/wc/v3/payments/reports/fees/download?test=1' );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'emits the CSV export click event when Export is clicked', async () => {
		render( <FeesExportButton /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Export' } )
		);

		expect( mockRecordEvent ).toHaveBeenCalledWith(
			'wcpay_csv_export_click',
			{
				row_type: 'fees',
				source: 'payments_reports',
				exported_row_count: 42,
			}
		);
	} );

	it( 'does not emit the click event when an unfiltered export confirmation is cancelled', async () => {
		mockUseReportsFeesSummary.mockReturnValue( {
			feesSummary: { count: 99999 },
			isLoading: false,
		} );
		jest.spyOn( window, 'confirm' ).mockReturnValue( false );

		render( <FeesExportButton /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Export' } )
		);

		expect( mockRecordEvent ).not.toHaveBeenCalledWith(
			'wcpay_csv_export_click',
			expect.anything()
		);
		expect( mockRequestReportExport ).not.toHaveBeenCalled();
	} );

	it( 'uses date_between for the summary query and CSV export request', async () => {
		const confirm = jest
			.spyOn( window, 'confirm' )
			.mockReturnValue( false );
		mockGetQuery.mockReturnValue( {
			date_between: [ '2026-05-01', '2026-05-18' ],
		} );
		mockUseReportsFeesSummary.mockReturnValue( {
			feesSummary: { count: 25000 },
			isLoading: false,
		} );

		render( <FeesExportButton /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Export' } )
		);

		expect( mockUseReportsFeesSummary ).toHaveBeenCalledWith(
			expect.objectContaining( {
				date_between: [ '2026-05-01', '2026-05-18' ],
			} )
		);
		expect( confirm ).not.toHaveBeenCalled();
		expect( mockGetFeesCSVRequestURL ).toHaveBeenCalledWith(
			expect.objectContaining( {
				dateBetween: [ '2026-05-01', '2026-05-18' ],
			} )
		);
		expect( mockRequestReportExport ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'preserves legacy datetime date filters for the summary query and CSV export request', async () => {
		mockGetQuery.mockReturnValue( {
			date_between: [ '2026-06-01 21:00:00', '2026-06-16 20:59:59' ],
		} );
		mockGetReportsFeesSummary.mockReturnValue( {
			count: 12,
		} );

		render( <FeesExportButton /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Export' } )
		);

		expect( mockUseReportsFeesSummary ).toHaveBeenCalledWith(
			expect.objectContaining( {
				date_between: [ '2026-06-01 21:00:00', '2026-06-16 20:59:59' ],
			} )
		);
		expect( mockGetReportsFeesSummary ).toHaveBeenCalledWith(
			expect.objectContaining( {
				dateBetween: [ '2026-06-01 21:00:00', '2026-06-16 20:59:59' ],
			} )
		);
		expect( mockGetFeesCSVRequestURL ).toHaveBeenCalledWith(
			expect.objectContaining( {
				dateBetween: [ '2026-06-01 21:00:00', '2026-06-16 20:59:59' ],
			} )
		);
	} );

	it( 'uses the click-time query for both the export request and exported row count', async () => {
		mockGetQuery
			.mockReturnValueOnce( {
				date_before: '2026-04-30',
			} )
			.mockReturnValueOnce( {
				date_between: [ '2026-05-01', '2026-05-18' ],
			} );
		mockUseReportsFeesSummary.mockReturnValue( {
			feesSummary: { count: 8 },
			isLoading: false,
		} );
		mockGetReportsFeesSummary.mockReturnValue( {
			count: 12,
		} );

		render( <FeesExportButton /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Export' } )
		);

		expect( mockGetReportsFeesSummary ).toHaveBeenCalledWith(
			expect.objectContaining( {
				dateBetween: expect.any( Array ),
			} )
		);
		expect( mockGetFeesCSVRequestURL ).toHaveBeenCalledWith(
			expect.objectContaining( {
				dateBetween: expect.any( Array ),
			} )
		);
		expect( mockRecordEvent ).toHaveBeenCalledWith(
			'wcpay_csv_export_click',
			expect.objectContaining( {
				exported_row_count: 12,
			} )
		);
	} );

	it( 'emits a Fees export success event when the export hook succeeds', async () => {
		render( <FeesExportButton /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Export' } )
		);

		const args = getExportRequestArgs();
		args.onSuccess?.();

		expect( mockRecordEvent ).toHaveBeenCalledWith(
			'wcpay_reports_fees_export_success',
			{
				exported_row_count: 42,
			}
		);
	} );

	it( 'emits a Fees export error event when the export hook fails', async () => {
		render( <FeesExportButton /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Export' } )
		);

		const args = getExportRequestArgs();
		args.onError?.( { reason: 'timeout' } );

		expect( mockRecordEvent ).toHaveBeenCalledWith(
			'wcpay_reports_fees_export_error',
			{
				error_type: 'timeout',
			}
		);
	} );
} );
