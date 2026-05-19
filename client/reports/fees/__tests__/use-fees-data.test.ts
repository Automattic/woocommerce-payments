/** @format */

import type { View } from '@wordpress/dataviews';
import { renderHook } from '@testing-library/react-hooks';
import type { ReportsPeriodRange } from '../../period-selector';
import type { DateFilterValue } from 'wcpay/reports/date-filter';

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
				undefined,
				period
			)
		).toMatchObject( {
			paged: '3',
			per_page: '50',
			orderby: 'fees',
			order: 'asc',
		} );
	} );

	// The backend validates `sort` against `Transaction::$fields`. A handful of
	// DataViews column ids don't match those backend column names (e.g.
	// `payment_method` is the UI label for the SQL column `source`). Sending
	// the raw column id returns a 500 from the upstream platform.
	it.each( [
		[ 'payment_method', 'source' ],
		[ 'transaction_currency', 'customer_currency' ],
		[ 'deposit_date', 'available_on' ],
	] )(
		'maps DataViews column id "%s" to backend sort field "%s"',
		( columnId, backendField ) => {
			const result = buildFeesQuery(
				baseView( {
					sort: { field: columnId, direction: 'asc' },
				} ),
				undefined,
				period
			);
			expect( result.orderby ).toBe( backendField );
		}
	);

	it( 'passes column ids that already match backend fields through unchanged', () => {
		const result = buildFeesQuery(
			baseView( { sort: { field: 'amount', direction: 'desc' } } ),
			undefined,
			period
		);
		expect( result.orderby ).toBe( 'amount' );
	} );

	it( 'defaults orderby to "date" when no sort is set', () => {
		const result = buildFeesQuery(
			baseView( { sort: undefined } ),
			undefined,
			period
		);
		expect( result.orderby ).toBe( 'date' );
	} );

	it( 'omits date params when no date filter is set', () => {
		const result = buildFeesQuery( baseView(), undefined, period );
		expect( result.date_between ).toBeUndefined();
		expect( result.date_before ).toBeUndefined();
		expect( result.date_after ).toBeUndefined();
	} );

	it( 'maps a date "between" filter to date_between', () => {
		const dateFilter: DateFilterValue = {
			operator: 'between',
			value: [ '2026-03-01', '2026-03-31' ],
		};
		const result = buildFeesQuery( baseView(), dateFilter, period );
		expect( result.date_between ).toEqual( [ '2026-03-01', '2026-03-31' ] );
		expect( result.date_before ).toBeUndefined();
		expect( result.date_after ).toBeUndefined();
	} );

	it( 'maps a date "on" filter to a same-day date_between', () => {
		const dateFilter: DateFilterValue = {
			operator: 'on',
			value: '2026-05-18',
		};
		const result = buildFeesQuery( baseView(), dateFilter, period );
		expect( result.date_between ).toEqual( [ '2026-05-18', '2026-05-18' ] );
		expect( result.date_before ).toBeUndefined();
		expect( result.date_after ).toBeUndefined();
	} );

	it( 'maps a date "before" filter to date_before', () => {
		const dateFilter: DateFilterValue = {
			operator: 'before',
			value: '2026-03-31',
		};
		const result = buildFeesQuery( baseView(), dateFilter, period );
		expect( result.date_before ).toBe( '2026-03-31' );
		expect( result.date_between ).toBeUndefined();
	} );

	it( 'maps a date "after" filter to date_after', () => {
		const dateFilter: DateFilterValue = {
			operator: 'after',
			value: '2026-01-01',
		};
		const result = buildFeesQuery( baseView(), dateFilter, period );
		expect( result.date_after ).toBe( '2026-01-01' );
		expect( result.date_between ).toBeUndefined();
	} );

	it( 'maps payment_method filter (single is)', () => {
		const result = buildFeesQuery(
			baseView( {
				filters: [
					{ field: 'payment_method', operator: 'is', value: 'card' },
				],
			} ),
			undefined,
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
			undefined,
			period
		);
		expect( result.type ).toBe( 'charge,refund' );
	} );

	it( 'wraps search as a single-element array', () => {
		expect(
			buildFeesQuery(
				baseView( { search: 'txn_abc' } ),
				undefined,
				period
			).search
		).toEqual( [ 'txn_abc' ] );
	} );

	it( 'omits search when empty', () => {
		expect(
			buildFeesQuery( baseView(), undefined, period ).search
		).toBeUndefined();
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
			useFeesData( baseView( { perPage: 20 } ), undefined, period )
		);

		expect( result.current.totalItems ).toBe( 47 );
		expect( result.current.totalPages ).toBe( 3 );
	} );

	it( 'returns at least 1 totalPages even when the summary is empty', () => {
		const { result } = renderHook( () =>
			useFeesData( baseView(), undefined, period )
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
			useFeesData( baseView(), undefined, period )
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
			useFeesData( baseView(), undefined, period )
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
			useFeesData( baseView(), undefined, period )
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
			useFeesData( baseView(), undefined, period )
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
			useFeesData( baseView( { search: 'txn_abc' } ), undefined, period )
		);

		expect( result.current.feesQuery.search ).toEqual( [ 'txn_abc' ] );
		// The same call passed to useReportsFees.
		expect( mockUseReportsFees ).toHaveBeenLastCalledWith(
			result.current.feesQuery
		);
	} );
} );
