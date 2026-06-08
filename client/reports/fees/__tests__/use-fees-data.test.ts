/** @format */

import type { View } from '@wordpress/dataviews/wp';
import { renderHook } from '@testing-library/react-hooks';

const mockUseReportsFees = jest.fn();
const mockUseReportsFeesSummary = jest.fn();

jest.mock( 'wcpay/data', () => ( {
	useReportsFees: ( ...args: unknown[] ) => mockUseReportsFees( ...args ),
	useReportsFeesSummary: ( ...args: unknown[] ) =>
		mockUseReportsFeesSummary( ...args ),
} ) );

import { buildFeesQuery, useFeesData } from '../use-fees-data';
import { encodeCustomDateFilterValue } from '../date-filter-values';

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
				} )
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
				} )
			);
			expect( result.orderby ).toBe( backendField );
		}
	);

	it( 'passes column ids that already match backend fields through unchanged', () => {
		const result = buildFeesQuery(
			baseView( { sort: { field: 'amount', direction: 'desc' } } )
		);
		expect( result.orderby ).toBe( 'amount' );
	} );

	it( 'defaults orderby to "date" when no sort is set', () => {
		const result = buildFeesQuery( baseView( { sort: undefined } ) );
		expect( result.orderby ).toBe( 'date' );
	} );

	it( 'omits date params when no date filter is set', () => {
		const result = buildFeesQuery( baseView() );
		expect( result.date_between ).toBeUndefined();
		expect( result.date_before ).toBeUndefined();
		expect( result.date_after ).toBeUndefined();
	} );

	it( 'maps a date "between" filter to date_between', () => {
		const result = buildFeesQuery(
			baseView( {
				filters: [
					{
						field: 'date',
						operator: 'is',
						value: encodeCustomDateFilterValue( {
							operator: 'between',
							value: [ '2026-03-01', '2026-03-31' ],
						} ),
					},
				],
			} )
		);
		expect( result.date_between ).toEqual( [ '2026-03-01', '2026-03-31' ] );
		expect( result.date_before ).toBeUndefined();
		expect( result.date_after ).toBeUndefined();
	} );

	it( 'maps a date "on" filter to a same-day date_between', () => {
		const result = buildFeesQuery(
			baseView( {
				filters: [
					{
						field: 'date',
						operator: 'is',
						value: encodeCustomDateFilterValue( {
							operator: 'on',
							value: '2026-05-18',
						} ),
					},
				],
			} )
		);
		expect( result.date_between ).toEqual( [ '2026-05-18', '2026-05-18' ] );
		expect( result.date_before ).toBeUndefined();
		expect( result.date_after ).toBeUndefined();
	} );

	it( 'maps a date "before" filter to date_before', () => {
		const result = buildFeesQuery(
			baseView( {
				filters: [
					{
						field: 'date',
						operator: 'is',
						value: encodeCustomDateFilterValue( {
							operator: 'before',
							value: '2026-03-31',
						} ),
					},
				],
			} )
		);
		expect( result.date_before ).toBe( '2026-03-31' );
		expect( result.date_between ).toBeUndefined();
	} );

	it( 'maps a date "after" filter to date_after', () => {
		const result = buildFeesQuery(
			baseView( {
				filters: [
					{
						field: 'date',
						operator: 'is',
						value: encodeCustomDateFilterValue( {
							operator: 'after',
							value: '2026-01-01',
						} ),
					},
				],
			} )
		);
		expect( result.date_after ).toBe( '2026-01-01' );
		expect( result.date_between ).toBeUndefined();
	} );

	it( 'maps payment_method filter (single is)', () => {
		const result = buildFeesQuery(
			baseView( {
				filters: [
					{ field: 'payment_method', operator: 'is', value: 'card' },
				],
			} )
		);
		expect( result.payment_method_type ).toBe( 'card' );
	} );

	it( 'maps type filter as a single value', () => {
		const result = buildFeesQuery(
			baseView( {
				filters: [
					{
						field: 'type',
						operator: 'is',
						value: 'charge',
					},
				],
			} )
		);
		expect( result.type ).toBe( 'charge' );
	} );

	it( 'omits multi-value type filters instead of truncating them', () => {
		const result = buildFeesQuery(
			baseView( {
				filters: [
					{
						field: 'type',
						operator: 'isAny',
						value: [ 'charge', 'refund' ],
					},
				],
			} )
		);
		expect( result.type ).toBeUndefined();
	} );

	it( 'omits comma-separated type filter values instead of truncating them', () => {
		const result = buildFeesQuery(
			baseView( {
				filters: [
					{
						field: 'type',
						operator: 'is',
						value: 'charge,refund',
					},
				],
			} )
		);
		expect( result.type ).toBeUndefined();
	} );

	it( 'wraps search as a single-element array', () => {
		expect(
			buildFeesQuery( baseView( { search: 'txn_abc' } ) ).search
		).toEqual( [ 'txn_abc' ] );
	} );

	it( 'omits search when empty', () => {
		expect( buildFeesQuery( baseView() ).search ).toBeUndefined();
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
			useFeesData( baseView( { perPage: 20 } ) )
		);

		expect( result.current.totalItems ).toBe( 47 );
		expect( result.current.totalPages ).toBe( 3 );
	} );

	it( 'returns at least 1 totalPages even when the summary is empty', () => {
		const { result } = renderHook( () => useFeesData( baseView() ) );

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

		const { result } = renderHook( () => useFeesData( baseView() ) );

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

		const { result } = renderHook( () => useFeesData( baseView() ) );

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

		const { result } = renderHook( () => useFeesData( baseView() ) );

		expect( result.current.isLoading ).toBe( true );
	} );

	it( 'passes feesError through to the caller', () => {
		mockUseReportsFees.mockReturnValue( {
			feesRows: [],
			feesError: { code: 'rest_forbidden' },
			isLoading: false,
		} );

		const { result } = renderHook( () => useFeesData( baseView() ) );

		expect( result.current.error ).toEqual( { code: 'rest_forbidden' } );
	} );

	it( 'passes feesSummaryError through to the caller', () => {
		mockUseReportsFeesSummary.mockReturnValue( {
			feesSummary: {},
			feesSummaryError: { code: 'summary_unavailable' },
			isLoading: false,
		} );

		const { result } = renderHook( () => useFeesData( baseView() ) );

		expect( result.current.error ).toEqual( {
			code: 'summary_unavailable',
		} );
	} );

	it( 'merges row and summary errors for callers', () => {
		mockUseReportsFees.mockReturnValue( {
			feesRows: [],
			feesError: { rows: 'rest_forbidden' },
			isLoading: false,
		} );
		mockUseReportsFeesSummary.mockReturnValue( {
			feesSummary: {},
			feesSummaryError: { summary: 'summary_unavailable' },
			isLoading: false,
		} );

		const { result } = renderHook( () => useFeesData( baseView() ) );

		expect( result.current.error ).toEqual( {
			rows: 'rest_forbidden',
			summary: 'summary_unavailable',
		} );
	} );

	it( 'passes the built query to the rows hook', () => {
		mockUseReportsFees.mockReturnValue( {
			feesRows: [],
			feesError: {},
			isLoading: false,
		} );

		const { result } = renderHook( () =>
			useFeesData( baseView( { search: 'txn_abc' } ) )
		);

		expect( result.current.rows ).toEqual( [] );
		expect( mockUseReportsFees ).toHaveBeenLastCalledWith(
			expect.objectContaining( { search: [ 'txn_abc' ] } )
		);
	} );

	// The summary endpoint returns aggregate values (count, total, fees,
	// sources, types) that do not change with pagination or sort. Sharing
	// pagination/sort params with the rows query would invalidate the
	// summary cache on every page/sort change for no reason.
	it( 'strips pagination and sort params from the summary query', () => {
		renderHook( () =>
			useFeesData(
				baseView( {
					page: 3,
					perPage: 50,
					sort: { field: 'amount', direction: 'asc' },
					search: 'txn_abc',
				} )
			)
		);

		const summaryQuery = mockUseReportsFeesSummary.mock.calls[ 0 ][ 0 ];
		expect( summaryQuery.paged ).toBeUndefined();
		expect( summaryQuery.per_page ).toBeUndefined();
		expect( summaryQuery.orderby ).toBeUndefined();
		expect( summaryQuery.order ).toBeUndefined();
		// Filter / search params still need to flow through so the summary
		// reflects the currently-applied filter set.
		expect( summaryQuery.search ).toEqual( [ 'txn_abc' ] );
	} );

	it( 'keeps filter params on the summary query', () => {
		renderHook( () =>
			useFeesData(
				baseView( {
					filters: [
						{
							field: 'payment_method',
							operator: 'is',
							value: 'card',
						},
					],
				} )
			)
		);

		expect( mockUseReportsFeesSummary ).toHaveBeenLastCalledWith(
			expect.objectContaining( { payment_method_type: 'card' } )
		);
	} );

	it( 'keeps the summary query stable across pagination and sort changes', () => {
		const { rerender } = renderHook(
			( props: { view: View } ) => useFeesData( props.view ),
			{
				initialProps: {
					view: baseView( {
						page: 1,
						sort: { field: 'date', direction: 'desc' },
					} ),
				},
			}
		);

		const firstSummaryQuery =
			mockUseReportsFeesSummary.mock.calls[ 0 ][ 0 ];

		rerender( {
			view: baseView( {
				page: 5,
				sort: { field: 'amount', direction: 'asc' },
			} ),
		} );

		const lastSummaryQuery =
			mockUseReportsFeesSummary.mock.calls[
				mockUseReportsFeesSummary.mock.calls.length - 1
			][ 0 ];
		expect( lastSummaryQuery ).toEqual( firstSummaryQuery );
	} );
} );
