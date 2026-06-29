/**
 * Internal dependencies
 */
import { isEmail } from '../email-validation';

describe( 'isEmail', () => {
	it.each( [
		[ 'user@example.com', true ],
		[ 'user+tag@sub.example.com', true ],
		[ 'a@b.co', true ],
		[ 'name@domain.travel', true ],
		[ 'USER@EXAMPLE.COM', true ],
	] )( 'accepts valid email: %s', ( email, expected ) => {
		expect( isEmail( email ) ).toBe( expected );
	} );

	it.each( [
		[ '', false ],
		[ 'notanemail', false ],
		[ '@nodomain.com', false ],
		[ 'user@', false ],
		[ 'user@nodot', false ],
		[ 'user @example.com', false ],
		[ ' ', false ],
		[ '@', false ],
		[ 'user@.com', false ],
	] )( 'rejects invalid email: %s', ( email, expected ) => {
		expect( isEmail( email ) ).toBe( expected );
	} );

	it( 'rejects internationalized emails to match server-side validation', () => {
		expect( isEmail( '用户@example.com' ) ).toBe( false );
		expect( isEmail( 'Pelstrø@example.com' ) ).toBe( false );
	} );

	it( 'rejects emails exceeding RFC 5321 max length of 254 characters', () => {
		const longEmail = 'a'.repeat( 243 ) + '@example.com'; // 255 chars
		expect( longEmail.length ).toBe( 255 );
		expect( isEmail( longEmail ) ).toBe( false );
	} );

	it( 'accepts emails at exactly 254 characters', () => {
		const maxEmail = 'a'.repeat( 242 ) + '@example.com'; // 254 chars
		expect( maxEmail.length ).toBe( 254 );
		expect( isEmail( maxEmail ) ).toBe( true );
	} );
} );
