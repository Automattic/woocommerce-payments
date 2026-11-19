/**
 * Internal dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';
import {
	shouldSkipWooPay,
	isShortcodeCheckout,
	isSupportedThemeEntrypoint,
	validateEmail,
} from 'wcpay/checkout/woopay/utils';

jest.mock( 'wcpay/utils/checkout', () => ( {
	getConfig: jest.fn(),
} ) );

describe( 'WooPay Utils', () => {
	const originalDocumentCookie = window.document.cookie;

	it.each( [
		[ 'user@example.com', true ],
		[ 'user+tag@sub.example.co.uk', true ],
		[ 'first.last@my-store.example.com', true ],
		[ 'user@example.co.', true ],
		[ 'user@example.c', false ],
		[ 'user@example.com-', false ],
		[ 'user@example', false ],
		[ '.user@example.com', false ],
		[ 'user.@example.com', false ],
		[ 'user..name@example.com', false ],
		[ 'user@-example.com', false ],
		[ 'user@example-.com', false ],
		[ 'user@example..com', false ],
		[ '用户@example.com', false ],
		[ '!@' + '0.0.'.repeat( 14 ) + '!', false ],
	] )( 'validates WooPay email %s', ( email, expected ) => {
		expect( validateEmail( email ) ).toBe( expected );
	} );

	it( 'does not stall on a malformed email', () => {
		const email = '!@' + '0.0.'.repeat( 14 ) + '!';
		const start = performance.now();

		validateEmail( email );
		const elapsed = performance.now() - start;

		expect( elapsed ).toBeLessThan( 100 );
	} );

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

		const shouldSkip = shouldSkipWooPay();

		expect( shouldSkip ).toBe( true );
	} );

	test( 'should skip WooPay returns false if cookie is not set', () => {
		Object.defineProperty( window.document, 'cookie', {
			writable: true,
			value: 'something=else',
		} );

		const shouldSkip = shouldSkipWooPay();

		expect( shouldSkip ).toBe( false );
	} );

	test( 'should not skip WooPay if skip_woopay cookie is set to 10', () => {
		Object.defineProperty( window.document, 'cookie', {
			writable: true,
			value: 'skip_woopay=10',
		} );

		const shouldSkip = shouldSkipWooPay();

		expect( shouldSkip ).toBe( false );
	} );

	test( 'should not skip WooPay if skip_woopay cookie is called something else', () => {
		Object.defineProperty( window.document, 'cookie', {
			writable: true,
			value: 'sskip_woopay=1',
		} );

		const shouldSkip = shouldSkipWooPay();

		expect( shouldSkip ).toBe( false );
	} );

	describe( 'isShortcodeCheckout', () => {
		test( 'returns true when server flag is set', () => {
			getConfig.mockReturnValue( true );
			expect( isShortcodeCheckout() ).toBe( true );
		} );

		test( 'returns false when server flag is not set', () => {
			getConfig.mockReturnValue( false );
			expect( isShortcodeCheckout() ).toBe( false );
		} );

		test( 'returns false when server flag is undefined', () => {
			getConfig.mockReturnValue( undefined );
			expect( isShortcodeCheckout() ).toBe( false );
		} );

		afterEach( () => {
			getConfig.mockReset();
		} );
	} );

	describe( 'isSupportedThemeEntrypoint', () => {
		test.each( [
			'woopay_shortcode_checkout',
			'woopay_blocks_checkout',
			'blocks_checkout',
			'bnpl_product_page',
			'bnpl_classic_cart',
			'bnpl_cart_block',
		] )( 'returns true for %s', ( type ) => {
			expect( isSupportedThemeEntrypoint( type ) ).toBe( true );
		} );

		test( 'returns false for unknown type', () => {
			expect( isSupportedThemeEntrypoint( 'unknown_type' ) ).toBe(
				false
			);
		} );

		test( 'returns false for undefined', () => {
			expect( isSupportedThemeEntrypoint( undefined ) ).toBe( false );
		} );
	} );
} );
