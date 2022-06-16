/** @format */

/**
 * Internal dependencies
 */
import { getDocumentUrl, getPaymentMethodSettingsUrl } from '../index';

describe( 'Utilities', () => {
	test( 'payment method settings link matches expected', () => {
		expect( getPaymentMethodSettingsUrl( 'foo' ) ).toEqual(
			'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&method=foo'
		);
	} );

	test( 'document url matches the expected URL', () => {
		expect( getDocumentUrl( 'documentID' ) ).toEqual(
			'https://site.com/wp-json/wc/v3/payments/documents/documentID?_wpnonce=random_wp_rest_nonce'
		);
	} );
} );
