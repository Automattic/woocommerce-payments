
/**
 * External dependencies
 */

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
