/**
 * External dependencies
 */
import { jest } from '@jest/globals';

/**
 * Internal dependencies
 */
import { formatFeeType, formatFeeRate } from '../utils';
import { formatCurrency } from 'multi-currency/interface/functions';

jest.mock( '@wordpress/i18n', () => ( {
	__: jest.fn().mockImplementation( ( str ) => str ),
} ) );

jest.mock( 'multi-currency/interface/functions', () => ( {
	formatCurrency: jest.fn(),
} ) );

describe( 'formatFeeType', () => {
	it( 'returns total transaction fee text for total type', () => {
		expect( formatFeeType( 'total' ) ).toBe( 'Total transaction fee' );
	} );

	it( 'returns base fee text for base type', () => {
		expect( formatFeeType( 'base' ) ).toBe( 'Base fee' );
	} );

	it( 'returns international card fee text for additional international type', () => {
		expect( formatFeeType( 'additional', 'international' ) ).toBe(
			'International card fee'
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

describe( 'formatFeeRate', () => {
	beforeEach( () => {
		( formatCurrency as jest.Mock ).mockReset();
	} );

	it( 'formats percentage only', () => {
		expect( formatFeeRate( 0.029, 0, 'USD', 'USD' ) ).toBe( '2.9%' );
	} );

	it( 'formats percentage only using all decimals', () => {
		expect( formatFeeRate( 0.0295, 0, 'USD', 'USD' ) ).toBe( '2.95%' );
	} );

	it( 'formats fixed amount only', () => {
		( formatCurrency as jest.Mock ).mockReturnValue( '$0.30' );
		expect( formatFeeRate( 0, 30, 'USD', 'USD' ) ).toBe( '$0.30' );
	} );

	it( 'combines percentage and fixed amount', () => {
		( formatCurrency as jest.Mock ).mockReturnValue( '$0.30' );
		expect( formatFeeRate( 0.029, 30, 'USD', 'USD' ) ).toBe(
			'2.9% + $0.30'
		);
	} );

	it( 'returns 0% when both percentage and fixed are 0', () => {
		expect( formatFeeRate( 0, 0, 'USD', 'USD' ) ).toBe( '0%' );
	} );
} );
