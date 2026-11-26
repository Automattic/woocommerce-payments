/** @format */

/**
 * External dependencies
 */
import { setSettings } from '@wordpress/date';
// WordPress core's wp.date ships timezone data; this import loads it for the
// copy Jest uses.
import moment from 'moment-timezone';

/**
 * Internal dependencies
 */
import { formatDateValue } from '..';

// The site settings WordPress prints for wp.date, in English.
const setSiteTimezone = ( timezone ) =>
	setSettings( {
		l10n: {
			locale: 'en',
			months: moment.months(),
			monthsShort: moment.monthsShort(),
			weekdays: moment.weekdays(),
			weekdaysShort: moment.weekdaysShort(),
			meridiem: { am: 'am', pm: 'pm', AM: 'AM', PM: 'PM' },
			relative: {},
		},
		formats: { time: 'g:i a', date: 'F j, Y', datetime: 'F j, Y g:i a' },
		timezone,
	} );

describe( 'formatDateValue', () => {
	afterEach( () => {
		setSiteTimezone( { offset: '0', string: '' } );
	} );

	// Jest runs the browser in America/New_York (tests/js/jest-global-setup.js),
	// so every case below has a site timezone that differs from the browser's.

	it( 'returns the start and end of the day in the site timezone, as UTC', () => {
		setSiteTimezone( { offset: 2, string: 'Europe/Vilnius' } );

		expect( formatDateValue( '2026-01-15' ) ).toBe( '2026-01-14 22:00:00' );
		expect( formatDateValue( '2026-01-15', true ) ).toBe(
			'2026-01-15 21:59:59'
		);
	} );

	it( 'applies the offset in effect on the filtered day, not today', () => {
		setSiteTimezone( { offset: 2, string: 'Europe/Vilnius' } );

		// Summer time (UTC+3) in Vilnius, whatever the current date is.
		expect( formatDateValue( '2026-07-15', true ) ).toBe(
			'2026-07-15 20:59:59'
		);
	} );

	it( 'gives each end of a range that spans a DST change its own offset', () => {
		setSiteTimezone( { offset: -6, string: 'America/Chicago' } );

		// Chicago moves from UTC-6 to UTC-5 at 2am on 2026-03-08.
		expect( formatDateValue( '2026-03-01' ) ).toBe( '2026-03-01 06:00:00' );
		expect( formatDateValue( '2026-03-08' ) ).toBe( '2026-03-08 06:00:00' );
		expect( formatDateValue( '2026-03-08', true ) ).toBe(
			'2026-03-09 04:59:59'
		);
		expect( formatDateValue( '2026-03-31', true ) ).toBe(
			'2026-04-01 04:59:59'
		);
	} );

	it( 'starts a day at the first instant after DST skips its midnight', () => {
		// Santiago moves from UTC-4 to UTC-3 at midnight on 2026-09-06, so the
		// day starts at 1am.
		setSiteTimezone( { offset: -4, string: 'America/Santiago' } );

		expect( formatDateValue( '2026-09-05', true ) ).toBe(
			'2026-09-06 03:59:59'
		);
		expect( formatDateValue( '2026-09-06' ) ).toBe( '2026-09-06 04:00:00' );
	} );

	it( 'keeps the hour DST repeats before midnight in the day it belongs to', () => {
		// Santiago moves from UTC-3 to UTC-4 at midnight on 2026-04-05, so
		// 11pm on 2026-04-04 happens twice and both belong to that day.
		setSiteTimezone( { offset: -3, string: 'America/Santiago' } );

		expect( formatDateValue( '2026-04-04', true ) ).toBe(
			'2026-04-05 03:59:59'
		);
		expect( formatDateValue( '2026-04-05' ) ).toBe( '2026-04-05 04:00:00' );
	} );

	it( 'starts a day at the first of two midnights when DST repeats it', () => {
		// Havana moves from UTC-4 to UTC-5 at 1am on 2026-11-01, so midnight
		// happens twice.
		setSiteTimezone( { offset: -4, string: 'America/Havana' } );

		expect( formatDateValue( '2026-10-31', true ) ).toBe(
			'2026-11-01 03:59:59'
		);
		expect( formatDateValue( '2026-11-01' ) ).toBe( '2026-11-01 04:00:00' );
	} );

	it( 'uses the site UTC offset when the site has no timezone name', () => {
		setSiteTimezone( { offset: 5.5, string: '' } );

		expect( formatDateValue( '2026-01-15' ) ).toBe( '2026-01-14 18:30:00' );
		expect( formatDateValue( '2026-01-15', true ) ).toBe(
			'2026-01-15 18:29:59'
		);
	} );

	it( 'uses only the calendar date of a date-time value', () => {
		setSiteTimezone( { offset: 2, string: 'Europe/Vilnius' } );

		expect( formatDateValue( '2026-01-15 12:00:00' ) ).toBe(
			'2026-01-14 22:00:00'
		);
		expect( formatDateValue( '2026-01-15 00:00:00', true ) ).toBe(
			'2026-01-15 21:59:59'
		);
	} );

	it( 'passes a date it cannot read through unchanged', () => {
		expect( formatDateValue( '2026-02-30' ) ).toBe( '2026-02-30' );
	} );

	it( 'passes an empty value through', () => {
		expect( formatDateValue( undefined ) ).toBeUndefined();
		expect( formatDateValue( '', true ) ).toBe( '' );
	} );
} );
