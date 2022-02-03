/** @format */

/**
 * Internal dependencies
 */
import { getCookieValue, getPaymentMethodSettingsUrl } from '../index';

describe( 'Utilities', () => {
	test( 'payment method settings link matches expected', () => {
		expect( getPaymentMethodSettingsUrl( 'foo' ) ).toEqual(
			'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&method=foo'
		);
	} );

	describe( 'getCookieValue', () => {
		beforeAll( () => {
			Object.defineProperty( document, 'cookie', {
				writable: true,
				value: 'some_cookie=a_value',
			} );
		} );

		it( 'returns the value of an existing cookie', () => {
			const val = getCookieValue( 'some_cookie' );
			expect( val ).toEqual( 'a_value' );
		} );

		it( 'returns empty string for a non-existant cookie', () => {
			const val = getCookieValue( 'ghost_cookie' );
			expect( val ).toEqual( '' );
		} );
	} );
} );
