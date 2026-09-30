/** @format */

/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';

interface EarlyFraudWarningDismissal {
	dismissed: boolean;
}

// Kept out of the slice's index, which registers the store: the order screen
// needs this call but not the store.
export const setEarlyFraudWarningDismissed = (
	orderId: number,
	dismissed: boolean
): Promise< EarlyFraudWarningDismissal > =>
	apiFetch( {
		path: `${ NAMESPACE }/early_fraud_warnings/${ orderId }/dismiss`,
		method: 'POST',
		data: { dismissed },
	} );
