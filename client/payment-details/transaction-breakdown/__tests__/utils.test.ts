/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import { formatFeeType } from '../utils';

jest.mock( '@wordpress/i18n', () => ( {
	__: jest.fn().mockImplementation( ( str ) => str ),
} ) );

describe( 'formatFeeType', () => {
	it( 'returns total transaction fee text for total type', () => {
		expect( formatFeeType( 'total' ) ).toBe( 'Total' );
	} );

	it( 'returns base fee text for base type', () => {
		expect( formatFeeType( 'base' ) ).toBe( 'Base fee' );
	} );

	it( 'returns international payment fee text for additional international type', () => {
		expect( formatFeeType( 'additional', 'international' ) ).toBe(
			'International payment fee'
		);
	} );

	it( 'returns currency conversion fee text for additional fx type', () => {
		expect( formatFeeType( 'additional', 'fx' ) ).toBe(
			'Currency conversion fee'
		);
	} );

	it( 'returns generic fee text for unknown type', () => {
		expect( formatFeeType( 'unknown' ) ).toBe( 'Fee' );
	} );
} );
