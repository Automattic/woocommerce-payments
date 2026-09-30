/** @format */

import { getPresetsForOperator, matchPreset, resolvePreset } from '../presets';

// 2026-05-18 is a Monday. May 2026 has 31 days; April 2026 has 30 days.
const now = new Date( '2026-05-18T12:00:00Z' );

describe( 'getPresetsForOperator', () => {
	it( 'returns single-date presets for on/before/after', () => {
		for ( const operator of [ 'on', 'before', 'after' ] as const ) {
			const presets = getPresetsForOperator( operator ).map(
				( p ) => p.value
			);
			expect( presets ).toEqual( [
				'today',
				'yesterday',
				'past_week',
				'past_month',
				'custom',
			] );
		}
	} );

	it( 'returns range presets for between', () => {
		const presets = getPresetsForOperator( 'between' ).map(
			( p ) => p.value
		);
		expect( presets ).toEqual( [
			'last_month',
			'month_to_date',
			'last_year',
			'year_to_date',
			'custom',
		] );
	} );
} );

describe( 'resolvePreset', () => {
	it( 'resolves today for on/before/after', () => {
		expect( resolvePreset( 'today', 'on', now ) ).toEqual( {
			operator: 'on',
			value: '2026-05-18',
		} );
		expect( resolvePreset( 'today', 'before', now ) ).toEqual( {
			operator: 'before',
			value: '2026-05-18',
		} );
		expect( resolvePreset( 'today', 'after', now ) ).toEqual( {
			operator: 'after',
			value: '2026-05-18',
		} );
	} );

	it( 'resolves yesterday', () => {
		expect( resolvePreset( 'yesterday', 'on', now ) ).toEqual( {
			operator: 'on',
			value: '2026-05-17',
		} );
	} );

	it( 'resolves past_week as 7 days back from today', () => {
		expect( resolvePreset( 'past_week', 'after', now ) ).toEqual( {
			operator: 'after',
			value: '2026-05-11',
		} );
	} );

	it( 'resolves past_month as one calendar month back', () => {
		expect( resolvePreset( 'past_month', 'after', now ) ).toEqual( {
			operator: 'after',
			value: '2026-04-18',
		} );
	} );

	it( 'resolves last_month as the previous calendar month', () => {
		expect( resolvePreset( 'last_month', 'between', now ) ).toEqual( {
			operator: 'between',
			value: [ '2026-04-01', '2026-04-30' ],
		} );
	} );

	it( 'resolves month_to_date as start-of-month to today', () => {
		expect( resolvePreset( 'month_to_date', 'between', now ) ).toEqual( {
			operator: 'between',
			value: [ '2026-05-01', '2026-05-18' ],
		} );
	} );

	it( 'resolves last_year as full prior year', () => {
		expect( resolvePreset( 'last_year', 'between', now ) ).toEqual( {
			operator: 'between',
			value: [ '2025-01-01', '2025-12-31' ],
		} );
	} );

	it( 'resolves year_to_date as Jan 1 to today', () => {
		expect( resolvePreset( 'year_to_date', 'between', now ) ).toEqual( {
			operator: 'between',
			value: [ '2026-01-01', '2026-05-18' ],
		} );
	} );

	it( 'resolves today using the browser local date, not UTC', () => {
		expect(
			resolvePreset( 'today', 'on', new Date( '2026-12-01T02:30:00Z' ) )
		).toEqual( {
			operator: 'on',
			value: '2026-11-30',
		} );
	} );

	it( 'resolves calendar ranges from the browser local date', () => {
		expect(
			resolvePreset(
				'last_month',
				'between',
				new Date( '2026-03-01T02:30:00Z' )
			)
		).toEqual( {
			operator: 'between',
			value: [ '2026-01-01', '2026-01-31' ],
		} );
	} );

	it( 'returns undefined for custom', () => {
		expect( resolvePreset( 'custom', 'between', now ) ).toBeUndefined();
		expect( resolvePreset( 'custom', 'on', now ) ).toBeUndefined();
	} );

	it( 'returns undefined for operator/preset mismatches', () => {
		expect( resolvePreset( 'last_month', 'on', now ) ).toBeUndefined();
		expect( resolvePreset( 'today', 'between', now ) ).toBeUndefined();
	} );
} );

describe( 'matchPreset', () => {
	it( 'recognises today for on', () => {
		expect(
			matchPreset( { operator: 'on', value: '2026-05-18' }, now )
		).toBe( 'today' );
	} );

	it( 'recognises yesterday for after', () => {
		expect(
			matchPreset( { operator: 'after', value: '2026-05-17' }, now )
		).toBe( 'yesterday' );
	} );

	it( 'recognises last_month for between', () => {
		expect(
			matchPreset(
				{
					operator: 'between',
					value: [ '2026-04-01', '2026-04-30' ],
				},
				now
			)
		).toBe( 'last_month' );
	} );

	it( 'returns custom when no preset matches', () => {
		expect(
			matchPreset(
				{
					operator: 'between',
					value: [ '2026-03-10', '2026-04-22' ],
				},
				now
			)
		).toBe( 'custom' );
		expect(
			matchPreset( { operator: 'before', value: '2024-12-31' }, now )
		).toBe( 'custom' );
	} );
} );
