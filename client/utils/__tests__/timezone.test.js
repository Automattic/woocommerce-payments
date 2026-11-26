/** @format */

import { formatDateValue } from '..';

describe( 'site calendar day filter bounds in UTC', () => {
	const originalSettings = window.wcSettings;

	afterEach( () => {
		window.wcSettings = originalSettings;
	} );

	it.each( [
		[ 'UTC', '2026-07-15', '2026-07-15 00:00:00', '2026-07-15 23:59:59' ],
		[
			'Europe/Vilnius',
			'2026-03-29',
			'2026-03-28 22:00:00',
			'2026-03-29 20:59:59',
		],
		// Havana skips midnight and starts this day at 01:00.
		[
			'America/Havana',
			'2026-03-08',
			'2026-03-08 05:00:00',
			'2026-03-09 03:59:59',
		],
		// Midnight occurs twice on this date.
		[
			'Atlantic/Azores',
			'2026-10-25',
			'2026-10-25 00:00:00',
			'2026-10-26 00:59:59',
		],
		// Santiago repeats the last hour of this day.
		[
			'America/Santiago',
			'2026-04-04',
			'2026-04-04 03:00:00',
			'2026-04-05 03:59:59',
		],
		[
			'+05:45',
			'2026-01-15 12:00:00',
			'2026-01-14 18:15:00',
			'2026-01-15 18:14:59',
		],
	] )( '%s on %s', ( timeZone, day, start, end ) => {
		window.wcSettings = { admin: { timeZone } };
		expect( formatDateValue( day ) ).toBe( start );
		expect( formatDateValue( day, true ) ).toBe( end );
	} );

	it( 'preserves an invalid filter from a bookmarked URL', () => {
		expect( formatDateValue( '2026-02-30' ) ).toBe( 'Invalid date' );
	} );
} );
