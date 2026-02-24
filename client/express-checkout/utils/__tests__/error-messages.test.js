/**
 * Internal dependencies
 */
import { getErrorMessageFromNotice } from '..';

describe( 'getErrorMessageFromNotice', () => {
	test( 'strips formatting', () => {
		const notice = '<p><b>Error:</b> Payment failed.</p>';
		expect( getErrorMessageFromNotice( notice ) ).toBe(
			'Error: Payment failed.'
		);
	} );

	test( 'strips scripts', () => {
		const notice =
			'<p><b>Error:</b> Payment failed.<script>alert("hello")</script></p>';
		expect( getErrorMessageFromNotice( notice ) ).toBe(
			'Error: Payment failed.alert("hello")'
		);
	} );

	test( 'returns empty string for undefined', () => {
		expect( getErrorMessageFromNotice( undefined ) ).toBe( '' );
	} );

	test( 'returns empty string for empty string', () => {
		expect( getErrorMessageFromNotice( '' ) ).toBe( '' );
	} );
} );
