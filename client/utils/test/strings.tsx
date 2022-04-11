/**
 * Internal dependencies
 */
import { joinWithConjunction } from '../strings';

describe( 'Strings Utils', () => {
	test( 'joinWithConjunction handles empty list', () => {
		const expected = '';

		expect( joinWithConjunction( [] ) ).toStrictEqual( expected );
	} );

	test( 'joinWithConjunction adds conjunction', () => {
		const expected = '1 and 2';

		expect( joinWithConjunction( [ '1', '2' ] ) ).toStrictEqual( expected );
	} );

	test( 'joinWithConjunction adds conjunction and commas', () => {
		const expected = '1, 2, 3 and 4';

		expect( joinWithConjunction( [ '1', '2', '3', '4' ] ) ).toStrictEqual(
			expected
		);
	} );
} );
