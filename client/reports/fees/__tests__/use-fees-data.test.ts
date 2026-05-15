/** @format */

import type { View, Operator } from '@wordpress/dataviews';
import { viewToFeesQuery } from '../use-fees-data';
import type { ReportsPeriodRange } from '../../period-selector';

const period: ReportsPeriodRange = {
	start: '2026-04-01T00:00:00Z',
	end: '2026-04-30T23:59:59Z',
};

const baseView = ( overrides: Partial< View > = {} ): View =>
	( {
		type: 'table',
		page: 1,
		perPage: 25,
		sort: { field: 'date', direction: 'desc' },
		search: '',
		filters: [],
		fields: [],
		...overrides,
	} as View );

describe( 'viewToFeesQuery', () => {
	it( 'maps pagination and sort', () => {
		expect(
			viewToFeesQuery(
				baseView( {
					page: 3,
					perPage: 50,
					sort: { field: 'fees', direction: 'asc' },
				} ),
				period
			)
		).toMatchObject( {
			paged: '3',
			per_page: '50',
			orderby: 'fees',
			order: 'asc',
		} );
	} );

	it( 'seeds date_between from period when no date filter is set', () => {
		expect( viewToFeesQuery( baseView(), period ).date_between ).toEqual( [
			'2026-04-01',
			'2026-04-30',
		] );
	} );

	it( 'maps a date "between" filter to date_between and omits period seeding', () => {
		const result = viewToFeesQuery(
			baseView( {
				filters: [
					{
						field: 'date',
						operator: 'between' as Operator,
						value: [ '2026-03-01', '2026-03-31' ],
					},
				],
			} ),
			period
		);
		expect( result.date_between ).toEqual( [ '2026-03-01', '2026-03-31' ] );
		expect( result.date_before ).toBeUndefined();
		expect( result.date_after ).toBeUndefined();
	} );

	it( 'maps a date "before" filter to date_before', () => {
		const result = viewToFeesQuery(
			baseView( {
				filters: [
					{
						field: 'date',
						operator: 'before' as Operator,
						value: '2026-03-31',
					},
				],
			} ),
			period
		);
		expect( result.date_before ).toBe( '2026-03-31' );
		expect( result.date_between ).toBeUndefined();
	} );

	it( 'maps payment_method filter (single is)', () => {
		const result = viewToFeesQuery(
			baseView( {
				filters: [
					{ field: 'payment_method', operator: 'is', value: 'card' },
				],
			} ),
			period
		);
		expect( result.payment_method_type ).toBe( 'card' );
	} );

	it( 'maps type filter (isAny array)', () => {
		const result = viewToFeesQuery(
			baseView( {
				filters: [
					{
						field: 'type',
						operator: 'isAny',
						value: [ 'charge', 'refund' ],
					},
				],
			} ),
			period
		);
		expect( result.type ).toEqual( [ 'charge', 'refund' ] );
	} );

	it( 'wraps search as a single-element array', () => {
		expect(
			viewToFeesQuery( baseView( { search: 'txn_abc' } ), period ).search
		).toEqual( [ 'txn_abc' ] );
	} );

	it( 'omits search when empty', () => {
		expect( viewToFeesQuery( baseView(), period ).search ).toBeUndefined();
	} );

	it( 'sets match=advanced when any non-date filter is active', () => {
		expect(
			viewToFeesQuery(
				baseView( {
					filters: [
						{
							field: 'payment_method',
							operator: 'is',
							value: 'card',
						},
					],
				} ),
				period
			).match
		).toBe( 'advanced' );
	} );
} );
