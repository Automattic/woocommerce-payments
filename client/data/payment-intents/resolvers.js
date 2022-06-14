/** @format */

/**
 * External dependencies
 */
import { apiFetch, dispatch } from '@wordpress/data-controls';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import { updatePaymentIntent, updateErrorForPaymentIntent } from './actions';

export function* getPaymentIntent( id ) {
	try {
		const results = yield apiFetch( {
			path: `${ NAMESPACE }/payment_intents/${ id }`,
		} );
		yield updatePaymentIntent( id, results );
	} catch ( e ) {
		yield dispatch(
			'core/notices',
			'createErrorNotice',
			__( 'Error retrieving transaction.', 'woocommerce-payments' )
		);
		yield updateErrorForPaymentIntent( id, null, e );
	}
}
