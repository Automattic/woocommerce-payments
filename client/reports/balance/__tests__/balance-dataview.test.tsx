/** @format */

/**
 * Internal dependencies
 */
import { balanceDateFilterOperators } from '../balance-dataview';

describe( 'Balance date filter operators', () => {
	it( 'leads with Between so a newly added filter defaults to it', () => {
		// DataViews uses operators[0] as the default operator for a newly added
		// (or re-added) filter; `between` must lead so Balance avoids the
		// confusing single-date "Past week / Past month" anchors (WOOPMNT-6243).
		expect( balanceDateFilterOperators[ 0 ] ).toBe( 'between' );
		expect( balanceDateFilterOperators ).toEqual( [
			'between',
			'before',
			'after',
			'on',
		] );
	} );
} );
