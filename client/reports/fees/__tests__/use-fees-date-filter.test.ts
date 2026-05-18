/** @format */

import { renderHook, act } from '@testing-library/react-hooks';

const mockGetQuery = jest.fn( () => ( {} as Record< string, unknown > ) );
const mockUpdateQueryString = jest.fn();

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: () => mockGetQuery(),
	updateQueryString: ( args: Record< string, unknown >, path?: string ) =>
		mockUpdateQueryString( args, path ),
} ) );

import { useFeesDateFilter } from '../use-fees-date-filter';

beforeEach( () => {
	mockGetQuery.mockReset().mockReturnValue( {} );
	mockUpdateQueryString.mockReset();
} );

describe( 'useFeesDateFilter', () => {
	it( 'starts with undefined when URL has no date params', () => {
		const { result } = renderHook( () => useFeesDateFilter() );
		expect( result.current[ 0 ] ).toBeUndefined();
	} );

	it( 'seeds from URL date_between', () => {
		mockGetQuery.mockReturnValue( {
			date_between: [ '2026-03-01', '2026-03-31' ],
		} );
		const { result } = renderHook( () => useFeesDateFilter() );
		expect( result.current[ 0 ] ).toEqual( {
			operator: 'between',
			value: [ '2026-03-01', '2026-03-31' ],
		} );
	} );

	it( 'collapses same-day date_between to operator "on"', () => {
		mockGetQuery.mockReturnValue( {
			date_between: [ '2026-05-18', '2026-05-18' ],
		} );
		const { result } = renderHook( () => useFeesDateFilter() );
		expect( result.current[ 0 ] ).toEqual( {
			operator: 'on',
			value: '2026-05-18',
		} );
	} );

	it( 'pushes setValue updates into the URL', () => {
		const { result } = renderHook( () => useFeesDateFilter() );
		act( () => {
			result.current[ 1 ]( {
				operator: 'after',
				value: '2026-05-01',
			} );
		} );
		expect( mockUpdateQueryString ).toHaveBeenCalledWith(
			expect.objectContaining( {
				date_after: '2026-05-01',
				date_between: undefined,
				date_before: undefined,
			} ),
			'/payments/reports'
		);
	} );

	it( 'clears all date keys when setValue is called with undefined', () => {
		mockGetQuery.mockReturnValue( {
			date_after: '2026-05-01',
		} );
		const { result } = renderHook( () => useFeesDateFilter() );
		act( () => {
			result.current[ 1 ]( undefined );
		} );
		expect( mockUpdateQueryString ).toHaveBeenCalledWith(
			{
				date_after: undefined,
				date_before: undefined,
				date_between: undefined,
			},
			'/payments/reports'
		);
	} );

	it( 're-derives from URL on popstate', () => {
		const { result } = renderHook( () => useFeesDateFilter() );
		expect( result.current[ 0 ] ).toBeUndefined();

		mockGetQuery.mockReturnValue( {
			date_before: '2026-05-18',
		} );
		act( () => {
			window.dispatchEvent( new PopStateEvent( 'popstate' ) );
		} );

		expect( result.current[ 0 ] ).toEqual( {
			operator: 'before',
			value: '2026-05-18',
		} );
	} );
} );
