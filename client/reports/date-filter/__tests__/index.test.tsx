/** @format */

/**
 * Internal dependencies
 */
import * as dateFilterExports from '../index';

describe( 'date filter module exports', () => {
	it( 'exposes shared URL utilities without the removed custom UI', () => {
		expect( dateFilterExports ).toHaveProperty(
			'parseDateFilterFromQuery'
		);
		expect( dateFilterExports ).toHaveProperty(
			'serializeDateFilterToQuery'
		);
		expect( dateFilterExports ).not.toHaveProperty( 'DateFilter' );
		expect( dateFilterExports ).not.toHaveProperty( 'default' );
	} );

	it( 'parses date filters through the barrel export', () => {
		expect(
			dateFilterExports.parseDateFilterFromQuery( {
				date_between: [ '2026-05-18', '2026-05-18' ],
			} )
		).toEqual( {
			operator: 'on',
			value: '2026-05-18',
		} );
	} );

	it( 'serializes date filters through the barrel export', () => {
		expect(
			dateFilterExports.serializeDateFilterToQuery( {
				operator: 'before',
				value: '2026-05-18',
			} )
		).toMatchObject( {
			date_before: '2026-05-18',
		} );
	} );
} );
