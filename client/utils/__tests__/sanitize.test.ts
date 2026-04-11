/**
 * Internal dependencies
 */
import { sanitizeHTML } from '../sanitize';

describe( 'Connect Account Page Utils', () => {
	it( 'should only return allowed tag and attributes', () => {
		const html =
			'<div><b>Visit </b><a href="https://wordpress.com" onclick="fn()">WordPress.com</a></div>';
		const expected =
			'<b>Visit </b><a href="https://wordpress.com">WordPress.com</a>';

		const sanitized = sanitizeHTML( html );

		expect( sanitized.__html ).toMatch( expected );
	} );
} );
