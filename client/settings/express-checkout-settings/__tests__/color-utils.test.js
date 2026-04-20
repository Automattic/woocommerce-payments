/** @format */

/**
 * Internal dependencies
 */
import {
	darkenColor,
	lightenColor,
	isDarkColor,
	getCardBorderColor,
} from '../color-utils';

describe( 'darkenColor', () => {
	it( 'darkens white by 50% to #808080', () => {
		expect( darkenColor( '#ffffff', 50 ) ).toBe( '#808080' );
	} );

	it( 'returns black when darkened by 100%', () => {
		expect( darkenColor( '#ff8800', 100 ) ).toBe( '#000000' );
	} );

	it( 'returns the same color when darkened by 0%', () => {
		expect( darkenColor( '#abcdef', 0 ) ).toBe( '#abcdef' );
	} );

	it( 'clamps negative percentage to 0', () => {
		expect( darkenColor( '#abcdef', -10 ) ).toBe( '#abcdef' );
	} );

	it( 'handles hex without hash prefix', () => {
		expect( darkenColor( 'ffffff', 50 ) ).toBe( '#808080' );
	} );
} );

describe( 'lightenColor', () => {
	it( 'lightens black by 50% to #808080', () => {
		expect( lightenColor( '#000000', 50 ) ).toBe( '#808080' );
	} );

	it( 'returns white when lightened by 100%', () => {
		expect( lightenColor( '#003366', 100 ) ).toBe( '#ffffff' );
	} );

	it( 'returns the same color when lightened by 0%', () => {
		expect( lightenColor( '#abcdef', 0 ) ).toBe( '#abcdef' );
	} );

	it( 'clamps percentage above 100 to 100', () => {
		expect( lightenColor( '#000000', 200 ) ).toBe( '#ffffff' );
	} );
} );

describe( 'isDarkColor', () => {
	it( 'returns true for black', () => {
		expect( isDarkColor( '#000000' ) ).toBe( true );
	} );

	it( 'returns false for white', () => {
		expect( isDarkColor( '#ffffff' ) ).toBe( false );
	} );

	it( 'returns true for dark blue', () => {
		expect( isDarkColor( '#003366' ) ).toBe( true );
	} );

	it( 'returns false for light yellow', () => {
		expect( isDarkColor( '#ffff00' ) ).toBe( false );
	} );

	it( 'handles boundary at luminance 128', () => {
		// YIQ luminance for #808080: (128*299 + 128*587 + 128*114)/1000 = 128
		// < 128 is false at exactly 128, so #808080 is not dark
		expect( isDarkColor( '#808080' ) ).toBe( false );
	} );
} );

describe( 'getCardBorderColor', () => {
	it( 'returns undefined for undefined input', () => {
		expect( getCardBorderColor( undefined ) ).toBeUndefined();
	} );

	it( 'returns undefined for null input', () => {
		expect( getCardBorderColor( null ) ).toBeUndefined();
	} );

	it( 'returns undefined for empty string', () => {
		expect( getCardBorderColor( '' ) ).toBeUndefined();
	} );

	it( 'normalizes 3-digit hex shorthand and applies border', () => {
		// #fff → #ffffff → darkenColor('#ffffff', 6) = #f0f0f0
		expect( getCardBorderColor( '#fff' ) ).toBe( '#f0f0f0' );
	} );

	it( 'normalizes 3-digit dark hex shorthand', () => {
		// #000 → #000000 → lightenColor('#000000', 6) = #0f0f0f
		expect( getCardBorderColor( '#000' ) ).toBe( '#0f0f0f' );
	} );

	it( 'returns undefined for invalid hex', () => {
		expect( getCardBorderColor( '#gggggg' ) ).toBeUndefined();
	} );

	it( 'darkens a light background by 6%', () => {
		const result = getCardBorderColor( '#ffffff' );
		// darkenColor('#ffffff', 6) = #f0f0f0
		expect( result ).toBe( '#f0f0f0' );
	} );

	it( 'lightens a dark background by 6%', () => {
		const result = getCardBorderColor( '#000000' );
		// lightenColor('#000000', 6) = #0f0f0f
		expect( result ).toBe( '#0f0f0f' );
	} );

	it( 'accepts uppercase hex', () => {
		expect( getCardBorderColor( '#FFFFFF' ) ).toBe( '#f0f0f0' );
	} );
} );
