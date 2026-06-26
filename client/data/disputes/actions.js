/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import { DISPUTES_STORE_NAME as STORE_NAME } from '../store-names';
import TYPES from './action-types';
// getPaymentIntent lives in the payment-intents store; importing its name
// registers that store so invalidating its resolution refreshes the payment
// intent shown on the Transaction Details screen after a dispute is accepted.
import { STORE_NAME as PAYMENT_INTENTS_STORE_NAME } from '../payment-intents/store';

export function updateDispute( data ) {
	return {
		type: TYPES.SET_DISPUTE,
		data,
	};
}

export function updateErrorForDispute( id, data, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_DISPUTE,
		id,
		data,
		error,
	};
}

export function updateDisputes( query, data ) {
	return {
		type: TYPES.SET_DISPUTES,
		query,
		data,
	};
}

export function updateDisputesSummary( query, data ) {
	return {
		type: TYPES.SET_DISPUTES_SUMMARY,
		query,
		data,
	};
}

export function* acceptDispute( dispute ) {
	const { id } = dispute;

	try {
		yield controls.dispatch( STORE_NAME, 'startResolution', 'getDispute', [
			id,
		] );

		const updatedDispute = yield apiFetch( {
			path: `${ NAMESPACE }/disputes/${ id }/close`,
			method: 'post',
		} );

		yield updateDispute( updatedDispute );

		// Invalidate the payment intent associated with the dispute so the
		// Transaction Details screen re-fetches it and reflects the change.
		yield controls.dispatch(
			PAYMENT_INTENTS_STORE_NAME,
			'invalidateResolutionForStoreSelector',
			'getPaymentIntent'
		);

		yield controls.dispatch( STORE_NAME, 'finishResolution', 'getDispute', [
			id,
		] );

		const message = updatedDispute.order
			? sprintf(
					/* translators: #%s is an order number, e.g. 15 */
					__(
						'You have accepted the dispute for order #%s.',
						'woocommerce-payments'
					),
					updatedDispute.order.number
			  )
			: __( 'You have accepted the dispute.', 'woocommerce-payments' );
		yield controls.dispatch(
			'core/notices',
			'createSuccessNotice',
			message
		);
	} catch ( e ) {
		const message = __(
			'There has been an error accepting the dispute. Please try again later.',
			'woocommerce-payments'
		);
		yield controls.dispatch( 'core/notices', 'createErrorNotice', message );
		yield controls.dispatch( STORE_NAME, 'finishResolution', 'getDispute', [
			id,
		] );
	}
}
