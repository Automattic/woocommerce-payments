/**
 * Internal dependencies
 */
import { mapToList } from '../keymap';

describe( 'Onboarding Requirements keymap', () => {
	test( 'mapToList handles empty keys and map', () => {
		const expected = '';

		expect( mapToList( [], {} ) ).toStrictEqual( expected );
	} );

	test( 'mapToList handles duplicated and missing values', () => {
		const keys = [ 'key1', 'key3', 'key4', 'key5' ];
		const map = { key1: 'a', key2: 'b', key3: 'a', key5: 'c' };
		const expected = 'a and c';

		expect( mapToList( keys, map ) ).toStrictEqual( expected );
	} );
} );
