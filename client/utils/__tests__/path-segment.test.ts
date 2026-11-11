/**
 * Internal dependencies
 */
import { encodePathSegment, InvalidPathSegmentError } from 'utils/path-segment';

const basePath = '/wp-json/wc/v3/payments/payment_intents/';

// Resolves the path the same way the browser's `fetch()` does, including dot segments and
// backslashes. This covers the request the browser sends, not how WordPress routes it.
const resolvePath = ( id: string ): URL =>
	new URL( `https://example.com${ basePath }${ encodePathSegment( id ) }` );

describe( 'encodePathSegment', () => {
	test.each( [
		[
			'pi_3AbC123xyz',
			'/wp-json/wc/v3/payments/payment_intents/pi_3AbC123xyz',
		],
		[ 'po_1234', '/wp-json/wc/v3/payments/payment_intents/po_1234' ],
		[ '42', '/wp-json/wc/v3/payments/payment_intents/42' ],
		[
			'id-with.dots',
			'/wp-json/wc/v3/payments/payment_intents/id-with.dots',
		],
		[ '...', '/wp-json/wc/v3/payments/payment_intents/...' ],
		[ '.hidden', '/wp-json/wc/v3/payments/payment_intents/.hidden' ],
	] )( 'keeps "%s" as a single path segment', ( id, expectedPath ) => {
		const url = resolvePath( id );

		expect( url.pathname ).toBe( expectedPath );
		expect( url.search ).toBe( '' );
		expect( url.hash ).toBe( '' );
	} );

	test.each( [
		'../../../wp/v2/users/me',
		'..%2F..%2Fwp%2Fv2%2Fusers',
		'%2e%2e',
		'.%2E',
		'..\\..\\wp\\v2\\users',
		'pi_1?context=edit',
		'pi_1#fragment',
		'pi_1/refund',
		'pi_\t1\n',
	] )(
		'stops "%s" from leaving the path segment or adding a query',
		( id ) => {
			const url = resolvePath( id );

			expect( url.pathname.startsWith( basePath ) ).toBe( true );
			expect( url.pathname.slice( basePath.length ) ).not.toContain(
				'/'
			);
			expect( url.search ).toBe( '' );
			expect( url.hash ).toBe( '' );
		}
	);

	test.each( [ '', '.', '..', undefined, null, {} ] )(
		'rejects %p',
		( id ) => {
			expect( () => encodePathSegment( id ) ).toThrow(
				InvalidPathSegmentError
			);
		}
	);

	test( 'exposes an error code for the stores', () => {
		expect( () => encodePathSegment( '..' ) ).toThrow(
			expect.objectContaining( { code: 'wcpay_invalid_path_segment' } )
		);
	} );

	test( 'accepts numeric IDs', () => {
		expect( encodePathSegment( 42 ) ).toBe( '42' );
	} );
} );
