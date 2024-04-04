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

	test( 'should not skip WooPay if skip_woopay cookie is set to 10', () => {
		Object.defineProperty( window.document, 'cookie', {
			writable: true,
			value: 'skip_woopay=10',
		} );

		const shouldSkipWooPay = WooPayDirectCheckout.shouldSkipWooPay();

		expect( shouldSkipWooPay ).toBe( false );
	} );

	test( 'should not skip WooPay if skip_woopay cookie is called something else', () => {
		Object.defineProperty( window.document, 'cookie', {
			writable: true,
			value: 'sskip_woopay=1',
		} );

		const shouldSkipWooPay = WooPayDirectCheckout.shouldSkipWooPay();

		expect( shouldSkipWooPay ).toBe( false );
	} );
} );
