/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal Dependencies
 */
import { ApiError } from '../../types/errors';
import { PaymentIntent } from '../../types/payment-intents';
import TYPES from './action-types';
import {
	UpdateErrorForPaymentIntentAction,
	UpdatePaymentIntentAction,
} from './types';
import { Charge } from 'wcpay/types/charges';
import { STORE_NAME } from 'wcpay/data/constants';

export function updatePaymentIntent(
	id: string,
	data: PaymentIntent
): UpdatePaymentIntentAction {
	return {
		type: TYPES.SET_PAYMENT_INTENT,
		id,
		data,
	};
}

export function updateErrorForPaymentIntent(
	id: string,
	error: ApiError
): UpdateErrorForPaymentIntentAction {
	return {
		type: TYPES.SET_ERROR_FOR_PAYMENT_INTENT,
		id,
		error,
	};
}

export function* refundCharge(
	charge: Charge,
	reason: string | null
): Generator {
	const paymentIntentId = charge.payment_intent;
	try {
		yield apiFetch( {
			path: `/wc/v3/payments/refund/`,
			method: 'post',
			data: {
				charge_id: charge.id,
				amount: charge.amount,
				reason: reason,
				order_id: charge?.order?.number,
			},
		} );

		yield controls.dispatch(
			STORE_NAME,
			'invalidateResolutionForStoreSelector',
			'getTimeline'
		);

		yield controls.dispatch(
			STORE_NAME,
			'invalidateResolutionForStoreSelector',
			'getPaymentIntent'
		);

		yield controls.dispatch(
			'core/notices',
			'createSuccessNotice',
			sprintf(
				// translators: %s payment intent id
				__( 'Refunded payment #%s.', 'woocommerce-payments' ),
				paymentIntentId
			)
		);
	} catch ( error ) {
		yield controls.dispatch(
			'core/notices',
			'createErrorNotice',
			sprintf(
				// translators: %s payment intent id
				__(
					'There has been an error refunding the payment #%s. Please try again later.',
					'woocommerce-payments'
				),
				paymentIntentId
			)
		);
	}
}
