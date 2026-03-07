/**
 * Internal dependencies
 */
import {
	shouldSkipWooPay,
	isShortcodeCheckout,
	isSupportedThemeEntrypoint,
} from 'wcpay/checkout/woopay/utils';

describe( 'WooPay Utils', () => {
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
		test( 'returns true when billing fields are present', () => {
			document.body.innerHTML =
				'<div class="woocommerce-billing-fields"></div>';
			expect( isShortcodeCheckout() ).toBe( true );
		} );

		test( 'returns false when billing fields are absent', () => {
			document.body.innerHTML = '<div></div>';
			expect( isShortcodeCheckout() ).toBe( false );
		} );

		afterEach( () => {
			document.body.innerHTML = '';
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
