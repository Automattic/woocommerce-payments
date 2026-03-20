/**
 * Internal dependencies
 */
import { isVersionGreaterOrEqual } from '../version';

describe( 'isVersionGreaterOrEqual', () => {
	it( 'treats pre-release as equal to same numeric version', () => {
		expect( isVersionGreaterOrEqual( '9.4.5-beta', '9.4.5' ) ).toBe( true );
		expect( isVersionGreaterOrEqual( '9.4.5', '9.4.5-beta' ) ).toBe( true );
	} );

	it( 'returns true when version is greater in major/minor/patch', () => {
		expect( isVersionGreaterOrEqual( '10.0.0', '9.9.9' ) ).toBe( true );
		expect( isVersionGreaterOrEqual( '9.5.0', '9.4.9' ) ).toBe( true );
		expect( isVersionGreaterOrEqual( '9.4.6', '9.4.5' ) ).toBe( true );
	} );

	it( 'returns true when versions are equal', () => {
		expect( isVersionGreaterOrEqual( '9.4.5', '9.4.5' ) ).toBe( true );
	} );

	it( 'returns false when version is smaller', () => {
		expect( isVersionGreaterOrEqual( '9.4.4', '9.4.5' ) ).toBe( false );
		expect( isVersionGreaterOrEqual( '9.3.9', '9.4.0' ) ).toBe( false );
	} );

	it( 'handles versions with missing patch/minor', () => {
		expect( isVersionGreaterOrEqual( '9.4', '9.4.0' ) ).toBe( true );
		expect( isVersionGreaterOrEqual( '9', '9.0.0' ) ).toBe( true );
	} );
} );
