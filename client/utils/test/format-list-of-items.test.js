/**
 * Internal dependencies
 */
import { formatListOfItems } from '../format-list-of-items';

describe( 'formatListOfItems', () => {
	it( 'handles list of one item', () => {
		expect( formatListOfItems( [ '0' ] ) ).toStrictEqual( '0' );
	} );

	it( 'handles a list of two items', () => {
		expect( formatListOfItems( [ '0', '1' ] ) ).toStrictEqual( '0 and 1' );
	} );

	it( 'handles a list of three items', () => {
		expect( formatListOfItems( [ '0', '1', '2' ] ) ).toStrictEqual(
			'0, 1, and 2'
		);
	} );

	it( 'handles a list of five items', () => {
		expect(
			formatListOfItems( [ '0', '1', '2', '3', '4' ] )
		).toStrictEqual( '0, 1, 2, 3, and 4' );
	} );
} );
