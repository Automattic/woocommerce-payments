/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { FeesReport } from '../';
import { useReportsFees, useReportsFeesSummary } from 'wcpay/data';
import { usePersistedColumnVisibility } from 'wcpay/hooks/use-persisted-table-column-visibility';
import { getQuery } from '@woocommerce/navigation';
import { getFeesColumns } from '../columns';
import type { ReportsFee } from 'wcpay/data/reports/hooks';
import { useReportExport } from 'wcpay/hooks/use-report-export';
import { useDispatch } from '@wordpress/data';
import { recordEvent } from 'tracks';

const tableCardMock = jest.fn( ( props: Record< string, any > ) => {
	void props;
	return <div data-testid="fees-table" />;
} );
const searchMock = jest.fn( ( props: Record< string, unknown > ) => {
	void props;
	return <div data-testid="fees-search" />;
} );
const feesFiltersMock = jest.fn( ( props: Record< string, unknown > ) => {
	void props;
	return <div data-testid="fees-filters" />;
} );
const downloadButtonMock = jest.fn( ( props: Record< string, unknown > ) => {
	void props;
	return <div data-testid="download-button" />;
} );
const requestReportExportMock = jest.fn();
const createNoticeMock = jest.fn();

jest.mock( '@woocommerce/components', () => ( {
	TableCard: ( props: Record< string, unknown > ) => tableCardMock( props ),
	Search: ( props: Record< string, unknown > ) => searchMock( props ),
	Link: ( {
		children,
		href,
	}: {
		children: React.ReactNode;
		href: string;
	} ) => <a href={ href }>{ children }</a>,
} ) );

jest.mock( 'wcpay/components/download-button', () => ( {
	__esModule: true,
	default: ( props: Record< string, unknown > ) =>
		downloadButtonMock( props ),
} ) );

jest.mock( 'wcpay/data', () => ( {
	useReportsFees: jest.fn(),
	useReportsFeesSummary: jest.fn(),
} ) );

jest.mock( 'wcpay/hooks/use-report-export', () => ( {
	useReportExport: jest.fn(),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn(),
} ) );

jest.mock( 'tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

jest.mock( 'wcpay/hooks/use-persisted-table-column-visibility', () => ( {
	usePersistedColumnVisibility: jest.fn(),
} ) );

jest.mock( '../filters', () => ( {
	FeesFilters: ( props: Record< string, unknown > ) =>
		feesFiltersMock( props ),
} ) );

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn(),
	onQueryChange: jest.fn(),
	updateQueryString: jest.fn(),
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
	applyThousandSeparator: ( value: number ) =>
		value.toLocaleString( 'en-US' ),
	formatDateValue: ( value: string, upperBound?: boolean ) => {
		if ( value === '2026-04-01' ) {
			return '2026-04-01 04:00:00';
		}

		if ( value === '2026-04-30' && upperBound ) {
			return '2026-05-01 03:59:59';
		}

		return value;
	},
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

jest.mock( 'multi-currency/interface/functions', () => ( {
	formatExplicitCurrency: ( amount: number, currency: string ) =>
		`${ currency.toUpperCase() } ${ amount }`,
} ) );

const period = {
	start: '2026-04-01T00:00:00.000Z',
	end: '2026-04-30T23:59:59.999Z',
};

const feeRow = {
	transaction_id: 'txn_123',
	date: '2026-04-10 10:00:00',
	payment_id: 'pi_123',
	payment_method: {
		type: 'card',
	},
	type: 'charge',
	transaction_currency: 'usd',
	amount: 1000,
	deposit_currency: 'usd',
	fees: 45,
	order_id: 321,
	deposit_date: '2026-04-12 00:00:00',
	deposit_id: null,
} as ReportsFee;

describe( 'FeesReport', () => {
	beforeEach( () => {
		( global as any ).wcpaySettings = {
			currentUserEmail: 'merchant@example.com',
		};
		( global as any ).wcSettings = {
			locale: {
				userLocale: 'en_US',
			},
		};
		jest.mocked( getQuery ).mockReturnValue( { tab: 'fees' } );
		( useDispatch as jest.Mock ).mockReturnValue( {
			createNotice: createNoticeMock,
		} );
		jest.mocked( useReportExport ).mockReturnValue( {
			requestReportExport: requestReportExportMock,
			isExportInProgress: false,
		} );
		jest.mocked( useReportsFees ).mockReturnValue( {
			feesRows: [ feeRow ],
			feesError: {},
			isLoading: false,
		} );
		jest.mocked( useReportsFeesSummary ).mockReturnValue( {
			feesSummary: {
				count: 1,
				total: 1000,
				fees: 45,
				currency: 'usd',
				sources: [ 'card' ],
				types: [ 'charge' ],
			},
			isLoading: false,
		} );
		jest.mocked( usePersistedColumnVisibility ).mockReturnValue( {
			onColumnsChange: jest.fn(),
			columnsToDisplay: getFeesColumns(),
		} );
		tableCardMock.mockClear();
		searchMock.mockClear();
		feesFiltersMock.mockClear();
		downloadButtonMock.mockClear();
		requestReportExportMock.mockClear();
		createNoticeMock.mockClear();
		jest.mocked( recordEvent ).mockClear();
	} );

	test( 'renders the Fees table with period defaults, filters, summary, and rows', () => {
		render( <FeesReport period={ period } /> );

		expect( useReportsFees ).toHaveBeenCalledWith(
			expect.objectContaining( {
				date_between: [ '2026-04-01', '2026-04-30' ],
			} )
		);
		expect( feesFiltersMock ).toHaveBeenCalledWith( {
			feesSummary: expect.objectContaining( {
				count: 1,
			} ),
		} );
		expect( tableCardMock ).toHaveBeenCalledWith(
			expect.objectContaining( {
				title: 'Fees',
				isLoading: false,
				rowsPerPage: 25,
				totalRows: 1,
				headers: getFeesColumns(),
				query: { tab: 'fees' },
				summary: [
					{ label: 'fee', value: '1' },
					{ label: 'gross total', value: 'USD 1000' },
					{ label: 'fees total', value: 'USD 45' },
				],
			} )
		);

		const tableProps = tableCardMock.mock.calls[ 0 ]?.[ 0 ];
		expect( tableProps ).toBeDefined();
		if ( ! tableProps ) {
			throw new Error( 'Expected TableCard props.' );
		}
		expect( tableProps.rows[ 0 ] ).toMatchObject( [
			{
				value: '2026-04-10 10:00:00',
				display: expect.any( Object ),
			},
			{
				value: 'card',
				display: 'Card Payment',
			},
			{
				value: 'Charge',
				display: expect.any( Object ),
			},
			{
				value: 321,
				display: expect.any( Object ),
			},
			{
				value: 'txn_123',
				display: expect.any( Object ),
			},
			{
				value: 'USD',
				display: expect.any( Object ),
			},
			{
				value: 1000,
				display: expect.any( Object ),
			},
			{
				value: 45,
				display: expect.any( Object ),
			},
			{
				value: '2026-04-12 00:00:00',
				display: expect.any( Object ),
			},
			{
				value: '',
				display: '\u2013',
			},
		] );
		expect( tableProps.actions[ 0 ].props ).toMatchObject( {
			allowFreeTextSearch: false,
			type: 'custom',
		} );
		expect( tableProps.actions[ 1 ].type ).toBeDefined();
		tableProps.onColumnsChange( [ 'date' ], 'amount' );
		expect( recordEvent ).toHaveBeenCalledWith(
			'wcpay_reports_view_options_opened',
			{
				report: 'fees',
			}
		);
		expect(
			screen.getByText(
				'Dates reflect when each event was created - settlement-date reporting is coming.'
			)
		).toBeInTheDocument();
	} );

	test( 'uses URL date filters instead of the default period', () => {
		jest.mocked( getQuery ).mockReturnValue( {
			tab: 'fees',
			date_after: '2026-05-01',
		} );

		render( <FeesReport period={ period } /> );

		expect( useReportsFees ).toHaveBeenCalledWith(
			expect.objectContaining( {
				date_after: '2026-05-01',
			} )
		);
		expect( useReportsFees ).toHaveBeenCalledWith(
			expect.not.objectContaining( {
				date_between: [ '2026-04-01', '2026-04-30' ],
			} )
		);
	} );

	test( 'renders the Fees error state when data loading fails', () => {
		jest.mocked( useReportsFees ).mockReturnValue( {
			feesRows: [],
			feesError: { code: 'error' },
			isLoading: false,
		} );

		render( <FeesReport period={ period } onReload={ jest.fn() } /> );

		expect(
			screen.getByRole( 'heading', { name: 'Fees report unavailable' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Reload report' } )
		).toBeInTheDocument();
		expect( tableCardMock ).not.toHaveBeenCalled();
	} );

	test( 'renders the empty state for an unfiltered period with no rows', () => {
		jest.mocked( useReportsFees ).mockReturnValue( {
			feesRows: [],
			feesError: {},
			isLoading: false,
		} );
		jest.mocked( useReportsFeesSummary ).mockReturnValue( {
			feesSummary: {
				count: 0,
				total: 0,
				fees: 0,
				currency: 'usd',
			},
			isLoading: false,
		} );

		render( <FeesReport period={ period } /> );

		expect(
			screen.getByRole( 'heading', { name: 'No fees yet' } )
		).toBeInTheDocument();
		expect(
			screen.getByText(
				'Fees will appear here once you start receiving payments.'
			)
		).toBeInTheDocument();
		expect( tableCardMock ).not.toHaveBeenCalled();
	} );

	test( 'requests a CSV export for the current Fees query', async () => {
		render( <FeesReport period={ period } /> );

		const tableProps = tableCardMock.mock.calls[ 0 ]?.[ 0 ];
		if ( ! tableProps ) {
			throw new Error( 'Expected TableCard props.' );
		}

		tableProps.actions[ 1 ].props.onClick();

		expect( recordEvent ).toHaveBeenCalledWith(
			'wcpay_reports_export_click',
			{
				report: 'fees',
				exported_row_count: 1,
			}
		);
		expect( requestReportExportMock ).toHaveBeenCalledWith( {
			exportRequestURL:
				'/wc/v3/payments/reports/fees/download?user_email=merchant%40example.com&locale=en_US&sort=date&direction=desc&date_between%5B0%5D=2026-04-01%2004%3A00%3A00&date_between%5B1%5D=2026-05-01%2003%3A59%3A59&user_timezone=-04%3A00',
			exportFileAvailabilityEndpoint:
				'/wc/v3/payments/reports/fees/download',
			userEmail: 'merchant@example.com',
		} );
		expect( createNoticeMock ).toHaveBeenCalledWith(
			'success',
			"We're processing your export. The file will download automatically and be emailed to merchant@example.com."
		);
	} );
} );
