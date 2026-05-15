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

const period = {
	start: '2026-04-01T00:00:00Z',
	end: '2026-04-30T23:59:59Z',
};

beforeEach( () => {
	mockUpdateQueryString.mockClear();
	mockUpdateUserPreferences.mockClear();
	mockGetQuery.mockReturnValue( {} );
	mockUserPrefs = {};
} );

describe( 'useFeesView', () => {
	it( 'returns the default view when URL and user_meta are empty', () => {
		const { result } = renderHook( () => useFeesView( period ) );
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
		const { result } = renderHook( () => useFeesView( period ) );
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
			type: [ 'charge', 'refund' ],
		} );
		const { result } = renderHook( () => useFeesView( period ) );
		expect( result.current[ 0 ].filters ).toEqual(
			expect.arrayContaining( [
				{ field: 'payment_method', operator: 'is', value: 'card' },
				{
					field: 'type',
					operator: 'isAny',
					value: [ 'charge', 'refund' ],
				},
			] )
		);
	} );

	it( 'reads date_between into a date filter', () => {
		mockGetQuery.mockReturnValue( {
			date_between: [ '2026-03-01', '2026-03-31' ],
		} );
		const { result } = renderHook( () => useFeesView( period ) );
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

	it( 'reads fields from user_meta', () => {
		mockUserPrefs = {
			wc_payments_reports_fees_view: {
				fields: [ 'date', 'transaction_id', 'amount' ],
				perPage: 100,
			},
		};
		const { result } = renderHook( () => useFeesView( period ) );
		expect( result.current[ 0 ].fields ).toEqual( [
			'date',
			'transaction_id',
			'amount',
		] );
		expect( result.current[ 0 ].perPage ).toBe( 100 );
	} );

	it( 'pushes sort changes to URL', () => {
		const { result } = renderHook( () => useFeesView( period ) );
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

	it( 'persists fields and perPage changes to user_meta', () => {
		const { result } = renderHook( () => useFeesView( period ) );
		act( () => {
			result.current[ 1 ]( {
				...result.current[ 0 ],
				fields: [ 'date', 'transaction_id' ],
				perPage: 100,
			} );
		} );
		expect( mockUpdateUserPreferences ).toHaveBeenCalledWith( {
			wc_payments_reports_fees_view: expect.objectContaining( {
				fields: [ 'date', 'transaction_id' ],
				perPage: 100,
			} ),
		} );
	} );
} );
