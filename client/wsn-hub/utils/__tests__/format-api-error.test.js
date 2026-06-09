/** @format **/

/**
 * Internal dependencies
 */
import { formatApiError } from '../format-api-error';

describe( 'formatApiError', () => {
	describe( 'network / opaque errors', () => {
		it( 'returns the network message for a TypeError ("Failed to fetch")', () => {
			const e = new TypeError( 'Failed to fetch' );
			expect( formatApiError( e ) ).toBe(
				'Network problem — check your connection and try again.'
			);
		} );

		it( 'returns the network message for a falsy error (null)', () => {
			expect( formatApiError( null ) ).toBe(
				'Network problem — check your connection and try again.'
			);
		} );

		it( 'returns the network message for an error with no message', () => {
			expect( formatApiError( { code: 'whatever' } ) ).toBe(
				'Network problem — check your connection and try again.'
			);
		} );
	} );

	describe( '5xx and HTML-body SyntaxError', () => {
		it( 'returns the server-error message for a 500 status', () => {
			const e = {
				message: 'Internal Server Error',
				data: { status: 500 },
			};
			expect( formatApiError( e ) ).toBe(
				'Server error — please try again or contact support.'
			);
		} );

		it( 'returns the server-error message for a 503 status', () => {
			const e = { message: 'Service Unavailable', data: { status: 503 } };
			expect( formatApiError( e ) ).toBe(
				'Server error — please try again or contact support.'
			);
		} );

		it( 'returns the server-error message for an HTML-body SyntaxError', () => {
			// Real-world: response.json() throws when the body is an HTML error page.
			const e = {
				message:
					'Unexpected token \'<\', "<!DOCTYPE "... is not valid JSON',
			};
			expect( formatApiError( e ) ).toBe(
				'Server error — please try again or contact support.'
			);
		} );
	} );

	describe( 'auth errors', () => {
		it( 'returns the session-expired message for status 401', () => {
			const e = { message: 'Unauthorized', data: { status: 401 } };
			expect( formatApiError( e ) ).toBe(
				'Your session expired — please refresh the page.'
			);
		} );

		it( 'returns the session-expired message for code rest_cookie_invalid_nonce', () => {
			const e = {
				message: 'Cookie nonce is invalid',
				code: 'rest_cookie_invalid_nonce',
			};
			expect( formatApiError( e ) ).toBe(
				'Your session expired — please refresh the page.'
			);
		} );

		it( 'returns the permission message for status 403', () => {
			const e = { message: 'Forbidden', data: { status: 403 } };
			expect( formatApiError( e ) ).toBe(
				'You do not have permission to make this change.'
			);
		} );

		it( 'returns the permission message for code rest_forbidden', () => {
			const e = { message: 'Sorry', code: 'rest_forbidden' };
			expect( formatApiError( e ) ).toBe(
				'You do not have permission to make this change.'
			);
		} );
	} );

	describe( '422 validation errors with per-field params', () => {
		it( 'flattens the params map into a readable string with the top-level message', () => {
			const e = {
				message: 'Invalid parameter(s): contact_email',
				data: {
					status: 422,
					params: {
						contact_email: 'Not a valid email address.',
						hero_image_id: 'Hero image must be an attachment.',
					},
				},
			};
			expect( formatApiError( e ) ).toBe(
				'Invalid parameter(s): contact_email ' +
					'(contact_email: Not a valid email address., ' +
					'hero_image_id: Hero image must be an attachment.)'
			);
		} );

		it( 'falls back to the message when 422 has no params populated', () => {
			const e = {
				message: 'Invalid parameter(s).',
				data: { status: 422, params: {} },
			};
			// Empty params object -> no flattened field parts -> falls back to message.
			expect( formatApiError( e ) ).toBe( 'Invalid parameter(s).' );
		} );
	} );

	describe( 'fallback', () => {
		it( 'returns the raw error message when no other branch matches', () => {
			const e = { message: 'Something specific happened.' };
			expect( formatApiError( e ) ).toBe(
				'Something specific happened.'
			);
		} );
	} );
} );
