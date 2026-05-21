/** @format */

import { renderHook, act } from '@testing-library/react-hooks';

const mockUpdateQueryString = jest.fn();
const mockGetQuery = jest.fn( () => ( {} ) );
const mockUpdateUserPreferences = jest.fn();
let mockUserPrefs: Record< string, unknown > = {};

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: () => mockGetQuery(),
	updateQueryString: ( args: Record< string, unknown >, path?: string ) =>
		mockUpdateQueryString( args, path ),
} ) );

jest.mock( '@woocommerce/data', () => ( {
	useUserPreferences: () => ( {
		...mockUserPrefs,
		updateUserPreferences: mockUpdateUserPreferences,
	} ),
} ) );

import { useFeesView } from '../use-fees-view';
import { defaultPerPage } from '../view';
import { encodeCustomDateFilterValue } from '../date-filter-values';

beforeEach( () => {
	mockUpdateQueryString.mockClear();
	mockUpdateUserPreferences.mockClear();
	mockGetQuery.mockReturnValue( {} );
	mockUserPrefs = {};
	jest.useRealTimers();
} );

describe( 'useFeesView', () => {
	it( 'returns the default view when URL and user_meta are empty', () => {
		const { result } = renderHook( () => useFeesView() );
		expect( result.current[ 0 ].sort ).toEqual( {
			field: 'date',
			direction: 'desc',
		} );
		expect( result.current[ 0 ].perPage ).toBe( defaultPerPage );
		expect( result.current[ 0 ].fields ).toContain( 'date' );
	} );

	it( 'reads sort and pagination from URL', () => {
		mockGetQuery.mockReturnValue( {
			orderby: 'amount',
			order: 'asc',
			paged: '2',
			per_page: '50',
		} );
		const { result } = renderHook( () => useFeesView() );
		expect( result.current[ 0 ].sort ).toEqual( {
			field: 'amount',
			direction: 'asc',
		} );
		expect( result.current[ 0 ].page ).toBe( 2 );
		expect( result.current[ 0 ].perPage ).toBe( 50 );
	} );

	it( 'reads payment_method_type and type from URL into filters', () => {
		mockGetQuery.mockReturnValue( {
			payment_method_type: 'card',
			type: 'charge',
		} );
		const { result } = renderHook( () => useFeesView() );
		expect( result.current[ 0 ].filters ).toEqual(
			expect.arrayContaining( [
				{ field: 'payment_method', operator: 'is', value: 'card' },
				{
					field: 'type',
					operator: 'is',
					value: 'charge',
				},
			] )
		);
	} );

	it( 'rejects multi-value Type URLs instead of truncating them', () => {
		mockGetQuery.mockReturnValue( {
			type: [ 'charge', 'refund' ],
		} );
		const { result } = renderHook( () => useFeesView() );
		expect( result.current[ 0 ].filters ).toEqual( [] );
	} );

	it( 'rejects comma-separated Type URLs instead of truncating them', () => {
		mockGetQuery.mockReturnValue( {
			type: 'charge,refund',
		} );
		const { result } = renderHook( () => useFeesView() );
		expect( result.current[ 0 ].filters ).toEqual( [] );
	} );

	it( 'reads date_preset from URL into the native Date filter', () => {
		mockGetQuery.mockReturnValue( {
			date_preset: 'month_to_date',
		} );
		const { result } = renderHook( () => useFeesView() );
		expect( result.current[ 0 ].filters ).toEqual(
			expect.arrayContaining( [
				{
					field: 'date',
					operator: 'is',
					value: 'month_to_date',
				},
			] )
		);
	} );

	it( 'reads custom date bounds from URL into the native Date filter', () => {
		mockGetQuery.mockReturnValue( {
			date_between: [ '2026-03-01', '2026-03-31' ],
		} );
		const { result } = renderHook( () => useFeesView() );
		expect( result.current[ 0 ].filters ).toEqual(
			expect.arrayContaining( [
				{
					field: 'date',
					operator: 'is',
					value: encodeCustomDateFilterValue( {
						operator: 'between',
						value: [ '2026-03-01', '2026-03-31' ],
					} ),
				},
			] )
		);
	} );

	it( 'reads fields from user_meta', () => {
		mockUserPrefs = {
			wc_payments_reports_fees_view: {
				fields: [ 'date', 'transaction_id', 'amount' ],
				perPage: 100,
			},
		};
		const { result } = renderHook( () => useFeesView() );
		expect( result.current[ 0 ].fields ).toEqual( [
			'date',
			'transaction_id',
			'amount',
		] );
		expect( result.current[ 0 ].perPage ).toBe( 100 );
	} );

	it( 'pushes sort changes to URL', () => {
		const { result } = renderHook( () => useFeesView() );
		act( () => {
			result.current[ 1 ]( {
				...result.current[ 0 ],
				sort: { field: 'fees', direction: 'asc' },
			} );
		} );
		expect( mockUpdateQueryString ).toHaveBeenCalledWith(
			expect.objectContaining( { orderby: 'fees', order: 'asc' } ),
			'/payments/reports'
		);
	} );

	it( 'debounces search changes before pushing them to the URL', () => {
		jest.useFakeTimers();
		const { result } = renderHook( () => useFeesView() );

		act( () => {
			result.current[ 1 ]( {
				...result.current[ 0 ],
				search: 'txn_1',
			} );
		} );

		expect( mockUpdateQueryString ).not.toHaveBeenCalled();

		act( () => {
			jest.advanceTimersByTime( 499 );
		} );
		expect( mockUpdateQueryString ).not.toHaveBeenCalled();

		act( () => {
			jest.advanceTimersByTime( 1 );
		} );

		expect( mockUpdateQueryString ).toHaveBeenCalledWith(
			expect.objectContaining( { search: [ 'txn_1' ] } ),
			'/payments/reports'
		);
	} );

	it( 'debounces the page reset that accompanies a search change', () => {
		jest.useFakeTimers();
		mockGetQuery.mockReturnValue( { paged: '2' } );
		const { result } = renderHook( () => useFeesView() );

		act( () => {
			result.current[ 1 ]( {
				...result.current[ 0 ],
				page: 1,
				search: 'txn_1',
			} );
		} );

		expect( mockUpdateQueryString ).not.toHaveBeenCalled();

		act( () => {
			jest.advanceTimersByTime( 500 );
		} );

		expect( mockUpdateQueryString ).toHaveBeenCalledWith(
			expect.objectContaining( {
				paged: '1',
				search: [ 'txn_1' ],
			} ),
			'/payments/reports'
		);
	} );

	it( 'keeps filter changes immediate while a search update is pending', () => {
		jest.useFakeTimers();
		const { result } = renderHook( () => useFeesView() );

		act( () => {
			result.current[ 1 ]( {
				...result.current[ 0 ],
				search: 'txn_1',
			} );
		} );

		expect( mockUpdateQueryString ).not.toHaveBeenCalled();

		act( () => {
			result.current[ 1 ]( {
				...result.current[ 0 ],
				search: 'txn_1',
				filters: [
					{
						field: 'payment_method',
						operator: 'is',
						value: 'card',
					},
				],
			} );
		} );

		expect( mockUpdateQueryString ).toHaveBeenCalledWith(
			expect.objectContaining( {
				search: [ 'txn_1' ],
				payment_method_type: 'card',
			} ),
			'/payments/reports'
		);

		act( () => {
			jest.advanceTimersByTime( 500 );
		} );
		expect( mockUpdateQueryString ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'pushes native Date filter changes to URL', () => {
		const { result } = renderHook( () => useFeesView() );
		act( () => {
			result.current[ 1 ]( {
				...result.current[ 0 ],
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
			} );
		} );
		expect( mockUpdateQueryString ).toHaveBeenCalledWith(
			expect.objectContaining( {
				date_preset: undefined,
				date_between: undefined,
				date_before: '2026-03-31',
				date_after: undefined,
			} ),
			'/payments/reports'
		);
	} );

	it( 'rejects multi-value Type filter changes before writing the URL', () => {
		const { result } = renderHook( () => useFeesView() );
		act( () => {
			result.current[ 1 ]( {
				...result.current[ 0 ],
				filters: [
					{
						field: 'type',
						operator: 'isAny',
						value: [ 'charge', 'refund' ],
					},
				],
			} );
		} );
		expect( mockUpdateQueryString ).not.toHaveBeenCalled();
	} );

	it( 're-derives the view after setView pushes URL-only changes', () => {
		mockUserPrefs = { wc_payments_reports_fees_view: null };
		const { result } = renderHook( () => useFeesView() );
		expect( result.current[ 0 ].page ).toBe( 1 );

		act( () => {
			// Simulate what `updateQueryString` does to the URL state. The
			// hook reads `getQuery()` on each memo re-derive, so the next
			// `setView` call needs to see the updated query.
			mockGetQuery.mockReturnValue( { paged: '2' } );
			result.current[ 1 ]( {
				...result.current[ 0 ],
				page: 2,
			} );
		} );

		// `setView` must bump `navTick` so the view memo re-reads `getQuery()`
		// and the new page becomes visible to consumers. Without that bump,
		// pagination, sort, filter, and search interactions silently desync
		// from the URL.
		expect( result.current[ 0 ].page ).toBe( 2 );
	} );

	it( 're-derives the view when the URL changes via browser back/forward', () => {
		const { result } = renderHook( () => useFeesView() );
		expect( result.current[ 0 ].sort?.field ).toBe( 'date' );

		mockGetQuery.mockReturnValue( {
			orderby: 'amount',
			order: 'asc',
		} );
		act( () => {
			window.dispatchEvent( new PopStateEvent( 'popstate' ) );
		} );

		expect( result.current[ 0 ].sort ).toEqual( {
			field: 'amount',
			direction: 'asc',
		} );
	} );

	it( 'persists fields and perPage changes to user_meta', () => {
		jest.useFakeTimers();
		// Mark prefs as loaded so the write-after-load guard doesn't skip.
		mockUserPrefs = { wc_payments_reports_fees_view: null };
		const { result } = renderHook( () => useFeesView() );
		act( () => {
			result.current[ 1 ]( {
				...result.current[ 0 ],
				fields: [ 'date', 'transaction_id' ],
				perPage: 100,
			} );
		} );

		// Persist writes are debounced — advance past the debounce window.
		act( () => {
			jest.advanceTimersByTime( 750 );
		} );

		expect( mockUpdateUserPreferences ).toHaveBeenCalledWith( {
			wc_payments_reports_fees_view: expect.objectContaining( {
				fields: [ 'date', 'transaction_id' ],
				perPage: 100,
			} ),
		} );
	} );

	it( 'skips updateUserPreferences when only URL-bound state changes', () => {
		mockUserPrefs = {
			wc_payments_reports_fees_view: {
				fields: [ 'date', 'amount' ],
				perPage: 25,
				layout: {},
			},
		};
		const { result } = renderHook( () => useFeesView() );
		act( () => {
			result.current[ 1 ]( {
				...result.current[ 0 ],
				fields: [ 'date', 'amount' ],
				perPage: 25,
				sort: { field: 'fees', direction: 'asc' },
				page: 3,
			} );
		} );
		expect( mockUpdateQueryString ).toHaveBeenCalled();
		expect( mockUpdateUserPreferences ).not.toHaveBeenCalled();
	} );

	it( 'skips updateUserPreferences before user_meta has loaded', () => {
		// `useUserPreferences` returns no `wc_payments_reports_fees_view` key
		// at all — i.e., the resolver has not finished yet. The hook must NOT
		// write the default shape to user_meta on the first interaction; that
		// would overwrite whatever the user previously stored.
		mockUserPrefs = {};
		const { result } = renderHook( () => useFeesView() );
		act( () => {
			result.current[ 1 ]( {
				...result.current[ 0 ],
				fields: [ 'date', 'transaction_id' ],
				perPage: 100,
			} );
		} );
		expect( mockUpdateUserPreferences ).not.toHaveBeenCalled();
	} );

	it( 'debounces persisted-shape changes into a single REST write', () => {
		jest.useFakeTimers();
		mockUserPrefs = { wc_payments_reports_fees_view: null };
		const { result } = renderHook( () => useFeesView() );

		// Three rapid view changes that mutate the persisted shape.
		act( () => {
			result.current[ 1 ]( {
				...result.current[ 0 ],
				perPage: 50,
			} );
		} );
		act( () => {
			result.current[ 1 ]( {
				...result.current[ 0 ],
				perPage: 100,
			} );
		} );
		act( () => {
			result.current[ 1 ]( {
				...result.current[ 0 ],
				perPage: 25,
			} );
		} );

		// Before the debounce fires, no REST write yet.
		expect( mockUpdateUserPreferences ).not.toHaveBeenCalled();

		// After the debounce window, exactly one write with the final state.
		act( () => {
			jest.advanceTimersByTime( 750 );
		} );

		expect( mockUpdateUserPreferences ).toHaveBeenCalledTimes( 1 );
		expect( mockUpdateUserPreferences ).toHaveBeenCalledWith( {
			wc_payments_reports_fees_view: expect.objectContaining( {
				perPage: 25,
			} ),
		} );
	} );
} );
