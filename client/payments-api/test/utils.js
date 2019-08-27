/** @format */

/**
 * Internal dependencies
 */
import {
	getResourceName,
	getResourcePrefix,
	isResourcePrefix,
	getResourceIdentifier,
} from '../utils';

describe( 'Payments API utility functions', () => {
	describe( 'getResourceName()', () => {
		it( 'Resource name is serialized correctly', () => {
			const prefix = 'test-prefix';
			const identifier = {
				page: 1,
				per_page: 25,
			};
			const expected = `${ prefix }:{"page":${ identifier.page },"per_page":${ identifier.per_page }}`;

			const resourceName = getResourceName( prefix, identifier );
			expect( resourceName ).toBe( expected );
		} );
	} );

	describe( 'getResourcePrefix()', () => {
		it( 'Correct resource prefix is returned', () => {
			const expected = 'test-prefix';
			const resourceName = `${ expected }:{"page":1,"per_page":25}`;

			const resourcePrefix = getResourcePrefix( resourceName );

			expect( resourcePrefix ).toBe( expected );
		} );
	} );

	describe( 'isResourcePrefix()', () => {
		it( 'Valid prefix returns true', () => {
			const prefix = 'test-prefix';
			const resourceName = `${ prefix }:{"page":1,"per_page":25}`;

			const isPrefix = isResourcePrefix( resourceName, prefix );

			expect( isPrefix ).toBe( true );
		} );
		it( 'Invalid prefix returns false', () => {
			const resourceName = 'test-prefix:{"page":1,"per_page":25}';

			const isPrefix = isResourcePrefix( resourceName, 'not-the-right-prefix' );

			expect( isPrefix ).toBe( false );
		} );
	} );

	describe( 'getResourceIdentifier()', () => {
		it( 'Correct resource identifier is returned', () => {
			const expected = {
				page: 1,
				per_page: 25,
			};
			const resourceName = `test-prefix:{"page":${ expected.page },"per_page":${ expected.per_page }}`;

			const resourceIdentifier = getResourceIdentifier( resourceName );

			expect( resourceIdentifier ).toStrictEqual( expected );
		} );
	} );
} );
