
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import 'payments-api/payments-data-store';

export const isInTestMode = () => {
	return wcpaySettings.testMode;
};
