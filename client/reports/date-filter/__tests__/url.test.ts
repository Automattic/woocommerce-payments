/** @format */

import { parseDateFilterFromQuery, serializeDateFilterToQuery } from '../url';

describe( 'parseDateFilterFromQuery', () => {
	it( 'parses a two-element date_between as between', () => {
		expect(
			parseDateFilterFromQuery( {
				date_between: [ '2026-03-01', '2026-03-31' ],
			} )
		).toEqual( {
			operator: 'between',
			value: [ '2026-03-01', '2026-03-31' ],
		} );
	} );

	it( 'collapses a same-day date_between into on', () => {
		expect(
			parseDateFilterFromQuery( {
				date_between: [ '2026-05-18', '2026-05-18' ],
			} )
		).toEqual( {
			operator: 'on',
			value: '2026-05-18',
		} );
	} );

	it( 'parses date_before as before', () => {
		expect(
			parseDateFilterFromQuery( { date_before: '2026-05-18' } )
		).toEqual( { operator: 'before', value: '2026-05-18' } );
	} );

	it( 'parses date_after as after', () => {
		expect(
			parseDateFilterFromQuery( { date_after: '2026-05-01' } )
		).toEqual( { operator: 'after', value: '2026-05-01' } );
	} );

	it( 'ignores malformed dates', () => {
		expect(
			parseDateFilterFromQuery( { date_before: 'not-a-date' } )
		).toBeUndefined();
		expect(
			parseDateFilterFromQuery( {
				date_between: [ '2026-05-01', 'broken' ],
			} )
		).toBeUndefined();
		expect(
			parseDateFilterFromQuery( {
				date_between: [ '2026-05-01' ],
			} )
		).toBeUndefined();
	} );

	it( 'returns undefined for an empty query', () => {
		expect( parseDateFilterFromQuery( {} ) ).toBeUndefined();
	} );
} );

describe( 'serializeDateFilterToQuery', () => {
	it( 'maps on to a same-day date_between', () => {
		expect(
			serializeDateFilterToQuery( {
				operator: 'on',
				value: '2026-05-18',
			} )
		).toEqual( {
			date_between: [ '2026-05-18', '2026-05-18' ],
			date_before: undefined,
			date_after: undefined,
		} );
	} );

	it( 'maps between to date_between', () => {
		expect(
			serializeDateFilterToQuery( {
				operator: 'between',
				value: [ '2026-05-01', '2026-05-18' ],
			} )
		).toEqual( {
			date_between: [ '2026-05-01', '2026-05-18' ],
			date_before: undefined,
			date_after: undefined,
		} );
	} );

	it( 'maps before/after to their respective keys', () => {
		expect(
			serializeDateFilterToQuery( {
				operator: 'before',
				value: '2026-05-18',
			} )
		).toEqual( {
			date_between: undefined,
			date_before: '2026-05-18',
			date_after: undefined,
		} );
		expect(
			serializeDateFilterToQuery( {
				operator: 'after',
				value: '2026-05-01',
			} )
		).toEqual( {
			date_between: undefined,
			date_before: undefined,
			date_after: '2026-05-01',
		} );
	} );

	it( 'clears all keys when value is undefined', () => {
		expect( serializeDateFilterToQuery( undefined ) ).toEqual( {
			date_between: undefined,
			date_before: undefined,
			date_after: undefined,
		} );
	} );
} );
