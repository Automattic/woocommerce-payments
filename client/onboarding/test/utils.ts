/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import { fromDotNotation } from '../utils';

describe( 'Onboarding Prototype Utils', () => {
	it( 'fromDotNotation converts dot notation keys to nested objects', () => {
		const input = {
			'foo.bar': 42,
			bar: 'foo',
		};
		const expectedOutput = {
			foo: {
				bar: 42,
			},
			bar: 'foo',
		};
		const output = fromDotNotation( input );
		expect( output ).toEqual( expectedOutput );
	} );

	it( 'fromDotNotation handles empty input', () => {
		const input = {};
		const expectedOutput = {};
		const output = fromDotNotation( input );
		expect( output ).toEqual( expectedOutput );
	} );

	it( 'fromDotNotation ignores null and undefined values', () => {
		const input = {
			foo: null,
			bar: undefined,
		};
		const expectedOutput = {};
		const output = fromDotNotation( input );
		expect( output ).toEqual( expectedOutput );
	} );
} );
