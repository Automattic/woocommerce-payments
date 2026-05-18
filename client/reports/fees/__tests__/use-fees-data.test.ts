/** @format */

import type { View, Operator } from '@wordpress/dataviews';
import { renderHook } from '@testing-library/react-hooks';
import type { ReportsPeriodRange } from '../../period-selector';

const mockUseReportsFees = jest.fn();
const mockUseReportsFeesSummary = jest.fn();

jest.mock( 'wcpay/data', () => ( {
	useReportsFees: ( ...args: unknown[] ) => mockUseReportsFees( ...args ),
	useReportsFeesSummary: ( ...args: unknown[] ) =>
		mockUseReportsFeesSummary( ...args ),
} ) );

import { buildFeesQuery, useFeesData } from '../use-fees-data';

const period: ReportsPeriodRange = {
	start: '2026-04-01T00:00:00Z',
	end: '2026-04-30T23:59:59Z',
};

const baseView = ( overrides: Partial< View > = {} ): View =>
	( {
		type: 'table',
		page: 1,
		perPage: 25,
		sort: { field: 'date', direction: 'desc' },
		search: '',
		filters: [],
		fields: [],
		...overrides,
	} as View );

describe( 'buildFeesQuery', () => {
	it( 'maps pagination and sort', () => {
		expect(
			buildFeesQuery(
				baseView( {
					page: 3,
					perPage: 50,
					sort: { field: 'fees', direction: 'asc' },
				} ),
				period
			)
		).toMatchObject( {
			paged: '3',
			per_page: '50',
			orderby: 'fees',
			order: 'asc',
		} );
	} );

	it( 'omits date params when no date filter is set', () => {
		const result = buildFeesQuery( baseView(), period );
		expect( result.date_between ).toBeUndefined();
		expect( result.date_before ).toBeUndefined();
		expect( result.date_after ).toBeUndefined();
	} );

	it( 'maps a date "between" filter to date_between and omits period seeding', () => {
		const result = buildFeesQuery(
			baseView( {
				filters: [
					{
						field: 'date',
						operator: 'between' as Operator,
						value: [ '2026-03-01', '2026-03-31' ],
					},
				],
			} ),
			period
		);
		expect( result.date_between ).toEqual( [ '2026-03-01', '2026-03-31' ] );
		expect( result.date_before ).toBeUndefined();
		expect( result.date_after ).toBeUndefined();
	} );

	it( 'maps a date "before" filter to date_before', () => {
		const result = buildFeesQuery(
			baseView( {
				filters: [
					{
						field: 'date',
						operator: 'before' as Operator,
						value: '2026-03-31',
					},
				],
			} ),
			period
		);
		expect( result.date_before ).toBe( '2026-03-31' );
		expect( result.date_between ).toBeUndefined();
	} );

	it( 'maps payment_method filter (single is)', () => {
		const result = buildFeesQuery(
			baseView( {
				filters: [
					{ field: 'payment_method', operator: 'is', value: 'card' },
				],
			} ),
			period
		);
		expect( result.payment_method_type ).toBe( 'card' );
	} );

	it( 'maps type filter (isAny array) as a comma-separated string', () => {
		const result = buildFeesQuery(
			baseView( {
				filters: [
					{
						field: 'type',
						operator: 'isAny',
						value: [ 'charge', 'refund' ],
					},
				],
			} ),
			period
		);
		expect( result.type ).toBe( 'charge,refund' );
	} );

	it( 'wraps search as a single-element array', () => {
		expect(
			buildFeesQuery( baseView( { search: 'txn_abc' } ), period ).search
		).toEqual( [ 'txn_abc' ] );
	} );

	it( 'omits search when empty', () => {
		expect( buildFeesQuery( baseView(), period ).search ).toBeUndefined();
	} );

	it( 'resolves a date "is" preset filter to date_between', () => {
		const now = new Date( '2026-05-18T00:00:00Z' );
		jest.useFakeTimers().setSystemTime( now );
		const result = buildFeesQuery(
			baseView( {
				filters: [
					{
						field: 'date',
						operator: 'is',
						value: 'this_month',
					},
				],
			} ),
			period
		);
		expect( result.date_between ).toEqual( [ '2026-05-01', '2026-05-18' ] );
		jest.useRealTimers();
	} );
} );

describe( 'useFeesData', () => {
	beforeEach( () => {
		mockUseReportsFees.mockReset();
		mockUseReportsFeesSummary.mockReset();
		mockUseReportsFees.mockReturnValue( {
			feesRows: [],
			feesError: {},
			isLoading: false,
		} );
		mockUseReportsFeesSummary.mockReturnValue( {
			feesSummary: { count: 0, sources: [], types: [] },
			isLoading: false,
		} );
	} );

	it( 'derives totalPages from summary count and perPage', () => {
		mockUseReportsFeesSummary.mockReturnValue( {
			feesSummary: { count: 47, sources: [], types: [] },
			isLoading: false,
		} );

		const { result } = renderHook( () =>
			useFeesData( baseView( { perPage: 20 } ), period )
		);

		expect( result.current.totalItems ).toBe( 47 );
		expect( result.current.totalPages ).toBe( 3 );
	} );

	it( 'returns at least 1 totalPages even when the summary is empty', () => {
		const { result } = renderHook( () =>
			useFeesData( baseView(), period )
		);

		expect( result.current.totalPages ).toBe( 1 );
	} );

	it( 'maps payment-method sources to human-readable filter labels', () => {
		mockUseReportsFeesSummary.mockReturnValue( {
			feesSummary: {
				count: 0,
				sources: [ 'card', 'bank_transfer' ],
				types: [],
			},
			isLoading: false,
		} );

		const { result } = renderHook( () =>
			useFeesData( baseView(), period )
		);

		// `displayMethod` returns the localized title for known methods and
		// falls back to the raw value for unknown ones. Asserting label !=
		// value guarantees the mapping is applied (rather than passing the
		// raw API string through, as a previous iteration of the code did).
		expect( result.current.methodElements ).toHaveLength( 2 );
		const card = result.current.methodElements.find(
			( e ) => e.value === 'card'
		);
		expect( card ).toBeDefined();
		expect( card?.label ).not.toBe( 'card' );
	} );

	it( 'maps transaction types to display labels', () => {
		mockUseReportsFeesSummary.mockReturnValue( {
			feesSummary: { count: 0, sources: [], types: [ 'charge' ] },
			isLoading: false,
		} );

		const { result } = renderHook( () =>
			useFeesData( baseView(), period )
		);

		const charge = result.current.typeElements.find(
			( e ) => e.value === 'charge'
		);
		expect( charge ).toBeDefined();
		expect( charge?.label ).not.toBe( 'charge' );
	} );

	it( 'merges loading state from rows and summary hooks', () => {
		mockUseReportsFees.mockReturnValue( {
			feesRows: [],
			feesError: {},
			isLoading: false,
		} );
		mockUseReportsFeesSummary.mockReturnValue( {
			feesSummary: { count: 0, sources: [], types: [] },
			isLoading: true,
		} );

		const { result } = renderHook( () =>
			useFeesData( baseView(), period )
		);

		expect( result.current.isLoading ).toBe( true );
	} );

	it( 'passes feesError through to the caller', () => {
		mockUseReportsFees.mockReturnValue( {
			feesRows: [],
			feesError: { code: 'rest_forbidden' },
			isLoading: false,
		} );

		const { result } = renderHook( () =>
			useFeesData( baseView(), period )
		);

		expect( result.current.error ).toEqual( { code: 'rest_forbidden' } );
	} );

	it( 'returns the memoized feesQuery used for the request', () => {
		mockUseReportsFees.mockReturnValue( {
			feesRows: [],
			feesError: {},
			isLoading: false,
		} );

		const { result } = renderHook( () =>
			useFeesData( baseView( { search: 'txn_abc' } ), period )
		);

		expect( result.current.feesQuery.search ).toEqual( [ 'txn_abc' ] );
		// The same call passed to useReportsFees.
		expect( mockUseReportsFees ).toHaveBeenLastCalledWith(
			result.current.feesQuery
		);
	} );
} );
