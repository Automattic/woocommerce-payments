/** @format */

/**
 * Internal dependencies
 */
import { getPaymentMethodSettingsUrl } from '../index';

describe( 'Utilities', () => {
	test( 'payment method settings link matches expected', () => {
		expect( getPaymentMethodSettingsUrl( 'foo' ) ).toEqual(
			'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&method=foo'
		);
	} );
} );
