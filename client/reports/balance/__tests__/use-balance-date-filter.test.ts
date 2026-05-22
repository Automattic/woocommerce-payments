/** @format */

import { act, renderHook } from '@testing-library/react-hooks';

const mockUpdateQueryString = jest.fn();
const mockGetQuery = jest.fn( () => ( {} ) );

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: () => mockGetQuery(),
	updateQueryString: ( args: Record< string, unknown >, path?: string ) =>
		mockUpdateQueryString( args, path ),
} ) );

import {
	getPeriodForDateFilter,
	useBalanceDateFilter,
} from '../use-balance-date-filter';

const now = new Date( '2026-05-21T12:00:00.000Z' );

beforeEach( () => {
	mockGetQuery.mockReturnValue( {} );
	mockUpdateQueryString.mockClear();
} );

describe( 'useBalanceDateFilter', () => {
	it( 'falls back to the last full calendar month when the URL has no Date filter', () => {
		const { result } = renderHook( () => useBalanceDateFilter( now ) );

		expect( result.current.value ).toBeUndefined();
		expect( result.current.period ).toEqual( {
			start: '2026-04-01T00:00:00.000Z',
			end: '2026-04-30T23:59:59.999Z',
		} );
	} );

	it( 'reads date_between from the URL', () => {
		mockGetQuery.mockReturnValue( {
			date_between: [ '2026-05-01', '2026-05-14' ],
		} );

		const { result } = renderHook( () => useBalanceDateFilter( now ) );

		expect( result.current.value ).toEqual( {
			operator: 'between',
			value: [ '2026-05-01', '2026-05-14' ],
		} );
		expect( result.current.period ).toEqual( {
			start: '2026-05-01T00:00:00.000Z',
			end: '2026-05-14T23:59:59.999Z',
		} );
	} );

	it( 'caps date_between from the URL at the latest complete UTC day', () => {
		mockGetQuery.mockReturnValue( {
			date_between: [ '2026-05-01', '2026-05-21' ],
		} );

		const { result } = renderHook( () => useBalanceDateFilter( now ) );

		expect( result.current.value ).toEqual( {
			operator: 'between',
			value: [ '2026-05-01', '2026-05-20' ],
		} );
		expect( result.current.period ).toEqual( {
			start: '2026-05-01T00:00:00.000Z',
			end: '2026-05-20T23:59:59.999Z',
		} );
	} );

	it( 'normalizes inverted date_between from the URL', () => {
		mockGetQuery.mockReturnValue( {
			date_between: [ '2026-05-14', '2026-05-01' ],
		} );

		const { result } = renderHook( () => useBalanceDateFilter( now ) );

		expect( result.current.value ).toEqual( {
			operator: 'between',
			value: [ '2026-05-01', '2026-05-14' ],
		} );
		expect( result.current.period ).toEqual( {
			start: '2026-05-01T00:00:00.000Z',
			end: '2026-05-14T23:59:59.999Z',
		} );
	} );

	it( 'updates the report URL when the Date filter changes', () => {
		const { result } = renderHook( () => useBalanceDateFilter( now ) );

		act( () => {
			result.current.setValue( {
				operator: 'between',
				value: [ '2026-05-01', '2026-05-14' ],
			} );
		} );

		expect( mockUpdateQueryString ).toHaveBeenCalledWith(
			{
				date_between: [ '2026-05-01', '2026-05-14' ],
				date_before: undefined,
				date_after: undefined,
			},
			'/payments/reports'
		);
	} );

	it( 'caps date_between before writing the report URL', () => {
		const { result } = renderHook( () => useBalanceDateFilter( now ) );

		act( () => {
			result.current.setValue( {
				operator: 'between',
				value: [ '2026-05-01', '2026-05-21' ],
			} );
		} );

		expect( mockUpdateQueryString ).toHaveBeenCalledWith(
			{
				date_between: [ '2026-05-01', '2026-05-20' ],
				date_before: undefined,
				date_after: undefined,
			},
			'/payments/reports'
		);
	} );

	it( 'normalizes inverted date_between before writing the report URL', () => {
		const { result } = renderHook( () => useBalanceDateFilter( now ) );

		act( () => {
			result.current.setValue( {
				operator: 'between',
				value: [ '2026-05-14', '2026-05-01' ],
			} );
		} );

		expect( mockUpdateQueryString ).toHaveBeenCalledWith(
			{
				date_between: [ '2026-05-01', '2026-05-14' ],
				date_before: undefined,
				date_after: undefined,
			},
			'/payments/reports'
		);
	} );

	it( 'keeps a same-day between selection in between mode after writing the report URL', () => {
		mockUpdateQueryString.mockImplementationOnce( ( args ) => {
			mockGetQuery.mockReturnValue( args );
		} );
		const { result } = renderHook( () => useBalanceDateFilter( now ) );

		act( () => {
			result.current.setValue( {
				operator: 'between',
				value: [ '2026-05-14', '2026-05-14' ],
			} );
		} );

		expect( mockUpdateQueryString ).toHaveBeenCalledWith(
			{
				date_between: [ '2026-05-14', '2026-05-14' ],
				date_before: undefined,
				date_after: undefined,
			},
			'/payments/reports'
		);
		expect( result.current.value ).toEqual( {
			operator: 'between',
			value: [ '2026-05-14', '2026-05-14' ],
		} );
	} );

	it( 'keeps setValue stable when using the default current time', () => {
		const { result, rerender } = renderHook( () => useBalanceDateFilter() );
		const firstSetValue = result.current.setValue;

		rerender();

		expect( result.current.setValue ).toBe( firstSetValue );
	} );

	it( 're-derives from URL changes via browser back and forward', () => {
		const { result } = renderHook( () => useBalanceDateFilter( now ) );

		expect( result.current.period.start ).toBe(
			'2026-04-01T00:00:00.000Z'
		);

		mockGetQuery.mockReturnValue( {
			date_between: [ '2026-03-01', '2026-03-31' ],
		} );
		act( () => {
			window.dispatchEvent( new PopStateEvent( 'popstate' ) );
		} );

		expect( result.current.period ).toEqual( {
			start: '2026-03-01T00:00:00.000Z',
			end: '2026-03-31T23:59:59.999Z',
		} );
	} );
} );

describe( 'getPeriodForDateFilter', () => {
	it( 'maps a same-day Date filter to a closed UTC day', () => {
		expect(
			getPeriodForDateFilter(
				{ operator: 'on', value: '2026-05-14' },
				now
			)
		).toEqual( {
			start: '2026-05-14T00:00:00.000Z',
			end: '2026-05-14T23:59:59.999Z',
		} );
	} );

	it( 'maps date_before to the selected month through the selected day', () => {
		expect(
			getPeriodForDateFilter(
				{ operator: 'before', value: '2026-03-14' },
				now
			)
		).toEqual( {
			start: '2026-03-01T00:00:00.000Z',
			end: '2026-03-14T23:59:59.999Z',
		} );
	} );

	it( 'maps date_after to the selected day through the selected month end', () => {
		expect(
			getPeriodForDateFilter(
				{ operator: 'after', value: '2026-03-14' },
				now
			)
		).toEqual( {
			start: '2026-03-14T00:00:00.000Z',
			end: '2026-03-31T23:59:59.999Z',
		} );
	} );

	it( 'caps date_after at the latest complete UTC day for the current month', () => {
		expect(
			getPeriodForDateFilter(
				{ operator: 'after', value: '2026-05-14' },
				now
			)
		).toEqual( {
			start: '2026-05-14T00:00:00.000Z',
			end: '2026-05-20T23:59:59.999Z',
		} );
	} );

	it( 'caps same-day filters at the latest complete UTC day', () => {
		expect(
			getPeriodForDateFilter(
				{ operator: 'on', value: '2026-05-21' },
				now
			)
		).toEqual( {
			start: '2026-05-20T00:00:00.000Z',
			end: '2026-05-20T23:59:59.999Z',
		} );
	} );
} );
