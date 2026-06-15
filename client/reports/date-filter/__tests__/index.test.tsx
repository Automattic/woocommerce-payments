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
} );
