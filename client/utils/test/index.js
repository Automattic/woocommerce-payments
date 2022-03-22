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
		global.wpApiSettings = {
			root: 'https://root-site/wp-json/',
			nonce: 'randomNonce',
		};

		expect( getDocumentUrl( 'documentID' ) ).toEqual(
			'https://root-site/wp-json/wc/v3/payments/documents/documentID?_wpnonce=randomNonce'
		);
	} );
} );
