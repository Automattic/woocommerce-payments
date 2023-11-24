/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { ApiError } from '../../types/errors';
import { PaymentIntent } from '../../types/payment-intents';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import { updatePaymentIntent, updateErrorForPaymentIntent } from './actions';

export function* getPaymentIntent( id: string ): Generator< unknown > {
	try {
		const result = yield apiFetch( {
			path: `${ NAMESPACE }/payment_intents/${ id }`,
		} );
		yield updatePaymentIntent( id, result as PaymentIntent );
	} catch ( e ) {
		yield controls.dispatch(
			'core/notices',
			'createErrorNotice',
			__( 'Error retrieving transaction.', 'woocommerce-payments' )
		);
		yield updateErrorForPaymentIntent( id, e as ApiError );
	}
}
