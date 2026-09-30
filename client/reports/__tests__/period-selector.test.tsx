/** @format */

/**
 * Internal dependencies
 */
import { getLastFullCalendarMonthUTC } from '../period-selector';

describe( 'Reports period selector', () => {
	afterEach( () => {
		jest.useRealTimers();
	} );

	it( 'calculates the last full calendar month in UTC', () => {
		const range = getLastFullCalendarMonthUTC(
			new Date( '2026-05-06T12:00:00Z' )
		);

		expect( range ).toEqual( {
			start: '2026-04-01T00:00:00.000Z',
			end: '2026-04-30T23:59:59.999Z',
		} );
	} );

	it( 'rolls back across the year boundary in January', () => {
		const range = getLastFullCalendarMonthUTC(
			new Date( '2026-01-15T12:00:00Z' )
		);

		expect( range ).toEqual( {
			start: '2025-12-01T00:00:00.000Z',
			end: '2025-12-31T23:59:59.999Z',
		} );
	} );

	it( 'returns 29 days for a leap-year February', () => {
		const range = getLastFullCalendarMonthUTC(
			new Date( '2024-03-10T12:00:00Z' )
		);

		expect( range ).toEqual( {
			start: '2024-02-01T00:00:00.000Z',
			end: '2024-02-29T23:59:59.999Z',
		} );
	} );

	it( 'returns 28 days for a non-leap-year February', () => {
		const range = getLastFullCalendarMonthUTC(
			new Date( '2025-03-10T12:00:00Z' )
		);

		expect( range ).toEqual( {
			start: '2025-02-01T00:00:00.000Z',
			end: '2025-02-28T23:59:59.999Z',
		} );
	} );

	it( 'uses the current date when no date is provided', () => {
		jest.useFakeTimers();
		jest.setSystemTime( new Date( '2026-05-06T12:00:00Z' ) );

		expect( getLastFullCalendarMonthUTC() ).toEqual( {
			start: '2026-04-01T00:00:00.000Z',
			end: '2026-04-30T23:59:59.999Z',
		} );
	} );
} );
