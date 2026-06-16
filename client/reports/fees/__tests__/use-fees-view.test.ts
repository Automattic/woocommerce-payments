/** @format */

import { renderHook, act } from '@testing-library/react-hooks';
import { recordEvent } from 'tracks';
import type { Filter, View, ViewTable } from '@wordpress/dataviews/wp';

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

jest.mock( 'tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

const mockRecordEvent = recordEvent as jest.MockedFunction<
	typeof recordEvent
>;

import { useFeesView } from '../use-fees-view';
import { defaultPerPage } from '../view';

const renderUseFeesView = () => renderHook( () => useFeesView() );

const updateFeesView = (
	result: ReturnType< typeof renderUseFeesView >[ 'result' ],
	nextView: Partial< View >
) => {
	act( () => {
		result.current[ 1 ]( {
			...result.current[ 0 ],
			...nextView,
		} );
	} );
};

const buildFilter = (
	field: string,
	value: unknown,
	operator = 'is'
): Filter =>
	( {
		field,
		operator,
		value,
	} as Filter );

const buildPaymentMethodFilter = ( value = 'card' ): Filter =>
	buildFilter( 'payment_method', value );

const buildTypeFilter = ( value: unknown, operator = 'is' ): Filter =>
	buildFilter( 'type', value, operator );

const buildDateFilter = (
	operator: Filter[ 'operator' ],
	value: unknown
): Filter => buildFilter( 'date', value, operator );

const expectRecordedTracksEvent = (
	eventName: string,
	properties: unknown
) => {
	expect( mockRecordEvent ).toHaveBeenCalledWith( eventName, properties );
};

const expectNoRecordedTracksEvent = ( eventName: string ) => {
	expect( mockRecordEvent ).not.toHaveBeenCalledWith(
		eventName,
		expect.anything()
	);
};

const countRecordedTracksEvents = ( eventName: string ) =>
	mockRecordEvent.mock.calls.filter(
		( [ recordedEventName ] ) => recordedEventName === eventName
	).length;

beforeEach( () => {
	mockUpdateQueryString.mockClear();
	mockUpdateUserPreferences.mockClear();
	mockRecordEvent.mockClear();
	mockGetQuery.mockReturnValue( {} );
	mockUserPrefs = {};
	jest.useRealTimers();
} );

describe( 'useFeesView', () => {
	it( 'returns the default view when URL and user_meta are empty', () => {
		const { result } = renderUseFeesView();
		expect( result.current[ 0 ].sort ).toEqual( {
			field: 'date',
			direction: 'desc',
		} );
		expect( result.current[ 0 ].perPage ).toBe( defaultPerPage );
		expect( result.current[ 0 ].titleField ).toBe( 'date' );
		expect( result.current[ 0 ].fields ).not.toContain( 'date' );
	} );

	it( 'keeps formatted currency columns start-aligned by default', () => {
		const { result } = renderUseFeesView();
		const view = result.current[ 0 ] as ViewTable;

		expect( view.layout?.styles ).toEqual(
			expect.objectContaining( {
				amount: expect.objectContaining( { align: 'start' } ),
				fees: expect.objectContaining( { align: 'start' } ),
			} )
		);
	} );

	it( 'reads sort and pagination from URL', () => {
		mockGetQuery.mockReturnValue( {
			orderby: 'amount',
			order: 'asc',
			paged: '2',
			per_page: '50',
		} );
		const { result } = renderUseFeesView();
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
		const { result } = renderUseFeesView();
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
		const { result } = renderUseFeesView();
		expect( result.current[ 0 ].filters ).toEqual( [] );
	} );

	it( 'rejects comma-separated Type URLs instead of truncating them', () => {
		mockGetQuery.mockReturnValue( {
			type: 'charge,refund',
		} );
		const { result } = renderUseFeesView();
		expect( result.current[ 0 ].filters ).toEqual( [] );
	} );

	it( 'ignores legacy date_preset URLs', () => {
		mockGetQuery.mockReturnValue( {
			date_preset: 'month_to_date',
		} );
		const { result } = renderUseFeesView();
		expect( result.current[ 0 ].filters ).toEqual( [] );
	} );

	it( 'reads custom date bounds from URL into the native Date filter', () => {
		mockGetQuery.mockReturnValue( {
			date_between: [ '2026-03-01', '2026-03-31' ],
		} );
		const { result } = renderUseFeesView();
		expect( result.current[ 0 ].filters ).toEqual(
			expect.arrayContaining( [
				{
					field: 'date',
					operator: 'between',
					value: [ '2026-03-01', '2026-03-31' ],
				},
			] )
		);
	} );

	it( 'collapses same-day date bounds from URL into the native Date filter', () => {
		mockGetQuery.mockReturnValue( {
			date_between: [ '2026-03-15', '2026-03-15' ],
		} );
		const { result } = renderUseFeesView();
		expect( result.current[ 0 ].filters ).toEqual(
			expect.arrayContaining( [
				{
					field: 'date',
					operator: 'on',
					value: '2026-03-15',
				},
			] )
		);
	} );

	it( 'ignores legacy datetime date bounds from URL in the native Date filter', () => {
		mockGetQuery.mockReturnValue( {
			date_between: [ '2026-03-01 00:00:00', '2026-03-31 23:59:59' ],
		} );
		const { result } = renderUseFeesView();
		expect( result.current[ 0 ].filters ).toEqual( [] );
	} );

	it( 'reads fields from user_meta without duplicating the primary date column', () => {
		mockUserPrefs = {
			wc_payments_reports_fees_view: {
				fields: [ 'date', 'transaction_id', 'amount' ],
				perPage: 100,
			},
		};
		const { result } = renderUseFeesView();
		expect( result.current[ 0 ].titleField ).toBe( 'date' );
		expect( result.current[ 0 ].fields ).toEqual( [
			'transaction_id',
			'amount',
		] );
		expect( result.current[ 0 ].perPage ).toBe( 100 );
	} );

	it( 'preserves persisted table layout while forcing formatted currency columns to start-align', () => {
		mockUserPrefs = {
			wc_payments_reports_fees_view: {
				fields: [ 'date', 'transaction_id', 'amount', 'fees' ],
				perPage: 25,
				layout: {
					density: 'balanced',
					styles: {
						amount: { align: 'end', width: '120px' },
						fees: { align: 'end', minWidth: '12ch' },
						type: { align: 'center' },
					},
				},
			},
		};
		const { result } = renderUseFeesView();

		expect( result.current[ 0 ].layout ).toEqual( {
			density: 'balanced',
			styles: {
				amount: { align: 'start', width: '120px' },
				fees: { align: 'start', minWidth: '12ch' },
				type: { align: 'center' },
			},
		} );
	} );

	it( 'pushes sort changes to URL', () => {
		const { result } = renderUseFeesView();
		updateFeesView( result, {
			sort: { field: 'fees', direction: 'asc' },
		} );

		expect( mockUpdateQueryString ).toHaveBeenCalledWith(
			expect.objectContaining( { orderby: 'fees', order: 'asc' } ),
			'/payments/reports'
		);
	} );

	it( 'debounces search changes before pushing them to the URL', () => {
		jest.useFakeTimers();
		const { result } = renderUseFeesView();

		updateFeesView( result, {
			search: 'txn_1',
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
		const { result } = renderUseFeesView();

		updateFeesView( result, {
			page: 1,
			search: 'txn_1',
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
		const { result } = renderUseFeesView();

		updateFeesView( result, {
			search: 'txn_1',
		} );

		expect( mockUpdateQueryString ).not.toHaveBeenCalled();

		updateFeesView( result, {
			search: 'txn_1',
			filters: [ buildPaymentMethodFilter() ],
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
		const { result } = renderUseFeesView();
		updateFeesView( result, {
			filters: [ buildDateFilter( 'before', '2026-03-31' ) ],
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

	it.each( [
		{
			name: 'additions',
			query: {},
			nextValue: 'card',
			hadPreviousValue: false,
		},
		{
			name: 'changes with previous value context',
			query: { payment_method_type: 'card' },
			nextValue: 'link',
			hadPreviousValue: true,
		},
	] )(
		'records non-date filter $name',
		( { query, nextValue, hadPreviousValue } ) => {
			mockGetQuery.mockReturnValue( query );
			const { result } = renderUseFeesView();

			updateFeesView( result, {
				filters: [ buildPaymentMethodFilter( nextValue ) ],
			} );

			expectRecordedTracksEvent( 'wcpay_reports_fees_filter_change', {
				filter_field: 'payment_method_type',
				had_previous_value: hadPreviousValue,
			} );
		}
	);

	it( 'does not record date filters as generic filter changes', () => {
		const { result } = renderUseFeesView();

		updateFeesView( result, {
			filters: [ buildDateFilter( 'before', '2026-03-31' ) ],
		} );

		expectNoRecordedTracksEvent( 'wcpay_reports_fees_filter_change' );
		expectRecordedTracksEvent( 'wcpay_reports_fees_date_filter_change', {
			preset: 'custom',
			range_days: null,
			is_initial_apply: true,
		} );
	} );

	it( 'records date filter changes from an existing URL filter as non-initial applies', () => {
		mockGetQuery.mockReturnValue( {
			date_before: '2026-03-31',
		} );
		const { result } = renderUseFeesView();

		updateFeesView( result, {
			filters: [ buildDateFilter( 'after', '2026-04-01' ) ],
		} );

		expectRecordedTracksEvent( 'wcpay_reports_fees_date_filter_change', {
			preset: 'custom',
			range_days: null,
			is_initial_apply: false,
		} );
		expect(
			countRecordedTracksEvents( 'wcpay_reports_fees_date_filter_change' )
		).toBe( 1 );
	} );

	it( 'records a date filter reset when an applied date filter is removed', () => {
		mockGetQuery.mockReturnValue( {
			date_before: '2026-03-31',
		} );
		const { result } = renderUseFeesView();

		updateFeesView( result, { filters: [] } );

		expectRecordedTracksEvent( 'wcpay_reports_fees_date_filter_change', {
			preset: 'reset',
			range_days: null,
			is_initial_apply: false,
		} );
		expect(
			countRecordedTracksEvents( 'wcpay_reports_fees_date_filter_change' )
		).toBe( 1 );
	} );

	it( 'does not record a date filter reset when a value-less staged date filter is removed', () => {
		const { result } = renderUseFeesView();

		updateFeesView( result, {
			filters: [ buildDateFilter( 'between', undefined ) ],
		} );
		mockRecordEvent.mockClear();

		updateFeesView( result, { filters: [] } );

		expectNoRecordedTracksEvent( 'wcpay_reports_fees_date_filter_change' );
	} );

	it( 'does not record duplicate filter changes for equivalent structured values', () => {
		const { result } = renderUseFeesView();
		const typeFilter = buildTypeFilter( [ 'charge', 'refund' ], 'isAny' );

		updateFeesView( result, {
			filters: [ typeFilter ],
		} );
		mockRecordEvent.mockClear();

		updateFeesView( result, {
			filters: [ typeFilter ],
		} );

		expectNoRecordedTracksEvent( 'wcpay_reports_fees_filter_change' );
	} );

	it( 'does not record comma-separated Type filter changes', () => {
		const { result } = renderUseFeesView();

		updateFeesView( result, {
			filters: [ buildTypeFilter( 'charge,refund' ) ],
		} );

		expectNoRecordedTracksEvent( 'wcpay_reports_fees_filter_change' );
	} );

	it( 'debounces search tracking and records only the final search length', () => {
		jest.useFakeTimers();
		const firstSearchTerm = 'txn_secret';
		const finalSearchTerm = 'txn_secret_123';
		const { result } = renderUseFeesView();

		updateFeesView( result, {
			search: firstSearchTerm,
		} );

		expectNoRecordedTracksEvent( 'wcpay_reports_fees_search' );

		act( () => {
			jest.advanceTimersByTime( 250 );
		} );

		updateFeesView( result, {
			search: finalSearchTerm,
		} );

		act( () => {
			jest.advanceTimersByTime( 499 );
		} );

		expectNoRecordedTracksEvent( 'wcpay_reports_fees_search' );

		act( () => {
			jest.advanceTimersByTime( 1 );
		} );

		expectRecordedTracksEvent( 'wcpay_reports_fees_search', {
			search_length: finalSearchTerm.length,
		} );
		expect( countRecordedTracksEvents( 'wcpay_reports_fees_search' ) ).toBe(
			1
		);
		expect( JSON.stringify( mockRecordEvent.mock.calls ) ).not.toContain(
			firstSearchTerm
		);
		expect( JSON.stringify( mockRecordEvent.mock.calls ) ).not.toContain(
			finalSearchTerm
		);
	} );

	it( 'does not record search when clearing the search term', () => {
		mockGetQuery.mockReturnValue( {
			search: [ 'txn_1' ],
		} );
		const { result } = renderUseFeesView();

		updateFeesView( result, {
			search: '',
		} );

		expectNoRecordedTracksEvent( 'wcpay_reports_fees_search' );
	} );

	it( 'rejects multi-value Type filter changes before writing the URL', () => {
		const { result } = renderUseFeesView();
		updateFeesView( result, {
			filters: [ buildTypeFilter( [ 'charge', 'refund' ], 'isAny' ) ],
		} );

		expect( mockUpdateQueryString ).not.toHaveBeenCalled();
		expectNoRecordedTracksEvent( 'wcpay_reports_fees_filter_change' );
	} );

	it( 're-derives the view after setView pushes URL-only changes', () => {
		mockUserPrefs = { wc_payments_reports_fees_view: null };
		const { result } = renderUseFeesView();
		expect( result.current[ 0 ].page ).toBe( 1 );

		// Simulate what `updateQueryString` does to the URL state. The hook
		// reads `getQuery()` on each memo re-derive, so the next `setView` call
		// needs to see the updated query.
		mockGetQuery.mockReturnValue( { paged: '2' } );
		updateFeesView( result, {
			page: 2,
		} );

		// `setView` must bump `navTick` so the view memo re-reads `getQuery()`
		// and the new page becomes visible to consumers. Without that bump,
		// pagination, sort, filter, and search interactions silently desync
		// from the URL.
		expect( result.current[ 0 ].page ).toBe( 2 );
	} );

	it( 're-derives the view when the URL changes via browser back/forward', () => {
		const { result } = renderUseFeesView();
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
		const { result } = renderUseFeesView();

		updateFeesView( result, {
			fields: [ 'date', 'transaction_id' ],
			perPage: 100,
		} );

		// Persist writes are debounced — advance past the debounce window.
		act( () => {
			jest.advanceTimersByTime( 750 );
		} );

		expect( mockUpdateUserPreferences ).toHaveBeenCalledWith( {
			wc_payments_reports_fees_view: expect.objectContaining( {
				fields: [ 'transaction_id' ],
				perPage: 100,
			} ),
		} );
	} );

	it( 'skips updateUserPreferences when only URL-bound state changes', () => {
		mockUserPrefs = {
			wc_payments_reports_fees_view: {
				fields: [ 'amount' ],
				perPage: 25,
				layout: {},
			},
		};
		const { result } = renderUseFeesView();

		updateFeesView( result, {
			fields: [ 'amount' ],
			perPage: 25,
			sort: { field: 'fees', direction: 'asc' },
			page: 3,
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
		const { result } = renderUseFeesView();

		updateFeesView( result, {
			fields: [ 'date', 'transaction_id' ],
			perPage: 100,
		} );

		expect( mockUpdateUserPreferences ).not.toHaveBeenCalled();
	} );

	it( 'debounces persisted-shape changes into a single REST write', () => {
		jest.useFakeTimers();
		mockUserPrefs = { wc_payments_reports_fees_view: null };
		const { result } = renderUseFeesView();

		// Three rapid view changes that mutate the persisted shape.
		updateFeesView( result, {
			perPage: 50,
		} );
		updateFeesView( result, {
			perPage: 100,
		} );
		updateFeesView( result, {
			perPage: 25,
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
