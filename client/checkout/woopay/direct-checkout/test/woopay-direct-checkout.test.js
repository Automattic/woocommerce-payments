/**
 * Internal dependencies
 */
import WooPayDirectCheckout from '../woopay-direct-checkout';

describe( 'WooPay Direct Checkout', () => {
	const originalDocumentCookie = window.document.cookie;

	afterEach( () => {
		Object.defineProperty( window.document, 'cookie', {
			writable: true,
			value: originalDocumentCookie,
		} );
	} );

	test( 'should skip WooPay returns true if cookie is set', () => {
		Object.defineProperty( window.document, 'cookie', {
			writable: true,
			value: 'skip_woopay=1',
		} );

		const shouldSkipWooPay = WooPayDirectCheckout.shouldSkipWooPay();

		expect( shouldSkipWooPay ).toBe( true );
	} );

	test( 'should skip WooPay returns false if cookie is not set', () => {
		Object.defineProperty( window.document, 'cookie', {
			writable: true,
			value: 'something=else',
		} );

		const shouldSkipWooPay = WooPayDirectCheckout.shouldSkipWooPay();

		expect( shouldSkipWooPay ).toBe( false );
	} );
} );
