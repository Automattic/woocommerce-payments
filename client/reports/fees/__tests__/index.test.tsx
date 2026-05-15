/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

const mockUseReportsFees = jest.fn();
const mockUseReportsFeesSummary = jest.fn();
const mockGetQuery = jest.fn( () => ( {} as Record< string, unknown > ) );
const mockUpdateQueryString = jest.fn();
const mockUpdateUserPreferences = jest.fn();
const mockRequestReportExport = jest.fn();
const mockRecordEvent = jest.fn();
const mockCreateNotice = jest.fn();

jest.mock( 'wcpay/data', () => ( {
	useReportsFees: ( q: unknown ) => mockUseReportsFees( q ),
	useReportsFeesSummary: ( q: unknown ) => mockUseReportsFeesSummary( q ),
} ) );

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: () => mockGetQuery(),
	updateQueryString: ( args: Record< string, unknown >, path?: string ) =>
		mockUpdateQueryString( args, path ),
} ) );

jest.mock( '@woocommerce/data', () => ( {
	useUserPreferences: () => ( {
		updateUserPreferences: mockUpdateUserPreferences,
	} ),
} ) );

jest.mock( 'wcpay/hooks/use-report-export', () => ( {
	useReportExport: () => ( {
		requestReportExport: mockRequestReportExport,
		isExportInProgress: false,
	} ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: () => ( { createNotice: mockCreateNotice } ),
} ) );

jest.mock( 'tracks', () => ( {
	recordEvent: ( event: string, props: unknown ) =>
		mockRecordEvent( event, props ),
} ) );

jest.mock( 'multi-currency/interface/functions', () => ( {
	formatExplicitCurrency: ( amount: number, currency: string ) =>
		`${ currency.toUpperCase() } ${ amount }`,
} ) );

jest.mock( 'wcpay/components/clickable-cell', () => ( {
	__esModule: true,
	default: ( {
		children,
		href,
	}: {
		children: React.ReactNode;
		href: string;
	} ) => <a href={ href }>{ children }</a>,
} ) );

jest.mock( 'wcpay/components/details-link', () => ( {
	getDetailsURL: jest.fn( ( id: string ) => `/transaction/${ id }` ),
} ) );

jest.mock( 'wcpay/utils', () => ( {
	formatStringValue: ( value: string ) => value,
	getAdminUrl: ( args: Record< string, string | number > ) =>
		`/admin?${ new URLSearchParams(
			Object.entries( args ).map( ( [ key, value ] ) => [
				key,
				String( value ),
			] )
		).toString() }`,
} ) );

jest.mock( 'wcpay/utils/date-time', () => ( {
	formatDateTimeFromString: ( value: string ) => `formatted ${ value }`,
} ) );

jest.mock( '@woocommerce/components', () => ( {
	Link: ( {
		children,
		href,
	}: {
		children: React.ReactNode;
		href: string;
	} ) => <a href={ href }>{ children }</a>,
} ) );

jest.mock( 'wcpay/data/reports/resolvers', () => ( {
	getReportsFeesCSVRequestURL: jest.fn( () => '/mock-export-url' ),
	reportsFeesDownloadEndpoint: '/wc/v3/payments/reports/fees/download',
} ) );

/**
 * Internal dependencies
 */
import { FeesReport } from '../index';

const period = {
	start: '2026-04-01T00:00:00Z',
	end: '2026-04-30T23:59:59Z',
};

const baseRow = {
	transaction_id: 'txn_1',
	date: '2026-04-15T10:00:00Z',
	type: 'charge',
	transaction_currency: 'usd',
	amount: 1000,
	deposit_currency: 'usd',
	fees: 30,
	order_id: 100,
	payment_method: { type: 'card' },
};

beforeEach( () => {
	mockUseReportsFees.mockReset();
	mockUseReportsFeesSummary.mockReset();
	mockGetQuery.mockReset().mockReturnValue( {} );
	mockUpdateQueryString.mockReset();
	mockUpdateUserPreferences.mockReset();
	mockRequestReportExport.mockReset();
	mockRecordEvent.mockReset();
	mockCreateNotice.mockReset();

	( window as unknown as Record< string, unknown > ).wcpaySettings = {
		currentUserEmail: 'a@b.test',
	};
	( window as unknown as Record< string, unknown > ).wcSettings = {
		locale: { userLocale: 'en_US' },
	};

	mockUseReportsFees.mockReturnValue( {
		feesRows: [ baseRow ],
		feesError: {},
		isLoading: false,
	} );
	mockUseReportsFeesSummary.mockReturnValue( {
		feesSummary: {
			count: 1,
			sources: [ 'card' ],
			types: [ 'charge' ],
		},
		isLoading: false,
	} );
} );

describe( 'FeesReport (DataViews)', () => {
	it( 'queries the data store with date_between seeded from period when URL has no date params', () => {
		render( <FeesReport period={ period } /> );
		expect( mockUseReportsFees ).toHaveBeenCalledWith(
			expect.objectContaining( {
				date_between: [ '2026-04-01', '2026-04-30' ],
			} )
		);
	} );

	it( 'reads sort from URL into the query', () => {
		mockGetQuery.mockReturnValue( {
			orderby: 'amount',
			order: 'asc',
		} );
		render( <FeesReport period={ period } /> );
		expect( mockUseReportsFees ).toHaveBeenCalledWith(
			expect.objectContaining( { orderby: 'amount', order: 'asc' } )
		);
	} );

	it( 'renders the row data through DataViews fields', async () => {
		render( <FeesReport period={ period } /> );
		const items = await screen.findAllByText( 'txn_1' );
		expect( items.length ).toBeGreaterThan( 0 );
	} );

	it( 'renders an error placeholder with reload button when feesError is set', () => {
		mockUseReportsFees.mockReturnValue( {
			feesRows: [],
			feesError: { code: 'oops' },
			isLoading: false,
		} );
		const onReload = jest.fn();
		render( <FeesReport period={ period } onReload={ onReload } /> );
		expect(
			screen.getByText( 'Fees report unavailable' )
		).toBeInTheDocument();
		fireEvent.click( screen.getByRole( 'button', { name: /reload/i } ) );
		expect( onReload ).toHaveBeenCalled();
	} );

	it( 'fires wcpay_reports_export_click and submits the export request', () => {
		render( <FeesReport period={ period } /> );
		const exportButton = screen.getByRole( 'button', { name: /export/i } );
		fireEvent.click( exportButton );
		expect( mockRecordEvent ).toHaveBeenCalledWith(
			'wcpay_reports_export_click',
			expect.objectContaining( {
				report: 'fees',
				exported_row_count: 1,
			} )
		);
		expect( mockRequestReportExport ).toHaveBeenCalled();
	} );
} );
