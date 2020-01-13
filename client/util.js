
/**
 * External dependencies
 */
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import 'payments-api/payments-data-store'; // For wcpaySettings.

export const isInTestMode = ( fallback = false ) => {
	if ( 'undefined' === typeof wcpaySettings ) {
		return fallback;
	}
	return '1' === wcpaySettings.testMode || fallback;
};

export const getPaymentSettingsUrl = () => {
	return addQueryArgs(
		'admin.php',
		{
			page: 'wc-settings',
			tab: 'checkout',
			section: 'woocommerce_payments',
		}
	);
};
