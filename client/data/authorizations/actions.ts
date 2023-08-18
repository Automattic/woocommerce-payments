/** @format */
/**
 * External Dependencies
 */
import { Query } from '@woocommerce/navigation';
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';
import { sprintf, __ } from '@wordpress/i18n';

/**
 * Internal Dependencies
 */
import TYPES from './action-types';
import {
	AuthorizationsSummary,
	Authorization,
	CaptureAuthorizationApiResponse,
} from 'wcpay/types/authorizations';
import { STORE_NAME } from '../constants';
import { ApiError } from 'wcpay/types/errors';

export function updateAuthorizations(
	query: Query,
	data: Authorization[]
): {
	type: string;
	data: Authorization[];
	query: Query;
} {
	return {
		type: TYPES.SET_AUTHORIZATIONS,
		data,
		query,
	};
}

export function updateErrorForAuthorizations(
	query: Query,
	error: ApiError
): {
	type: string;
	query: Query;
	error: ApiError;
} {
	return {
		type: TYPES.SET_ERROR_FOR_AUTHORIZATIONS,
		query,
		error,
	};
}

export function updateAuthorization(
	data: Authorization
): { type: string; data: Authorization } {
	return {
		type: TYPES.SET_AUTHORIZATION,
		data,
	};
}

export function updateAuthorizationsSummary(
	query: Query,
	data: AuthorizationsSummary
): {
	type: string;
	data: AuthorizationsSummary;
	query: Query;
} {
	return {
		type: TYPES.SET_AUTHORIZATIONS_SUMMARY,
		data,
		query,
	};
}

export function setIsRequestingAuthorization(
	data: boolean
): { type: string; data: boolean } {
	return { type: TYPES.SET_IS_REQUESTING_AUTHORIZATION, data };
}

export function* submitCaptureAuthorization(
	paymentIntentId: string,
	orderId: number,
	amountToCapture: number | null = null
): Generator< unknown | Authorization > {
	try {
		yield controls.dispatch(
			STORE_NAME,
			'startResolution',
			'getAuthorization',
			[ paymentIntentId ]
		);

		yield controls.dispatch(
			STORE_NAME,
			'setIsRequestingAuthorization',
			true
		);

		const result = yield apiFetch( {
			path: `/wc/v3/payments/orders/${ orderId }/capture_authorization`,
			method: 'post',
			data: {
				payment_intent_id: paymentIntentId,
				amount_to_capture: amountToCapture || null,
			},
		} );

		const authorization = {
			payment_intent_id: ( result as CaptureAuthorizationApiResponse ).id,
			captured:
				( result as CaptureAuthorizationApiResponse ).status ===
				'succeeded',
		};

		yield updateAuthorization( authorization as Authorization );

		// Need to invalidate the resolution so that the components will render again.
		yield controls.dispatch(
			STORE_NAME,
			'invalidateResolutionForStoreSelector',
			'getAuthorizations'
		);

		yield controls.dispatch(
			STORE_NAME,
			'invalidateResolutionForStoreSelector',
			'getAuthorizationsSummary'
		);

		yield controls.dispatch(
			STORE_NAME,
			'invalidateResolutionForStoreSelector',
			'getFraudOutcomeTransactions'
		);

		yield controls.dispatch(
			STORE_NAME,
			'invalidateResolutionForStoreSelector',
			'getFraudOutcomeTransactionsSummary'
		);

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

		// Create success notice.
		yield controls.dispatch(
			'core/notices',
			'createSuccessNotice',
			sprintf(
				// translators: %s Order id
				__(
					'Payment for order #%s captured successfully.',
					'woocommerce-payments'
				),
				orderId
			)
		);
	} catch ( error ) {
		yield controls.dispatch(
			'core/notices',
			'createErrorNotice',
			sprintf(
				// translators: %s Order id
				__(
					'There has been an error capturing the payment for order #%s. Please try again later.',
					'woocommerce-payments'
				),
				orderId
			)
		);
	} finally {
		yield controls.dispatch(
			STORE_NAME,
			'finishResolution',
			'getAuthorization',
			[ paymentIntentId ]
		);
		yield controls.dispatch(
			STORE_NAME,
			'setIsRequestingAuthorization',
			false
		);
	}
}

export function* submitCancelAuthorization(
	paymentIntentId: string,
	orderId: number
): Generator< unknown | Authorization > {
	try {
		yield controls.dispatch(
			STORE_NAME,
			'startResolution',
			'getAuthorization',
			[ paymentIntentId ]
		);

		yield controls.dispatch(
			STORE_NAME,
			'setIsRequestingAuthorization',
			true
		);

		const result = yield apiFetch( {
			path: `/wc/v3/payments/orders/${ orderId }/cancel_authorization`,
			method: 'post',
			data: {
				payment_intent_id: paymentIntentId,
			},
		} );

		const authorization = {
			payment_intent_id: ( result as CaptureAuthorizationApiResponse ).id,
			captured:
				( result as CaptureAuthorizationApiResponse ).status ===
				'succeeded',
		};

		yield updateAuthorization( authorization as Authorization );

		// Need to invalidate the resolution so that the components will render again.
		yield controls.dispatch(
			STORE_NAME,
			'invalidateResolutionForStoreSelector',
			'getAuthorizations'
		);

		yield controls.dispatch(
			STORE_NAME,
			'invalidateResolutionForStoreSelector',
			'getAuthorizationsSummary'
		);

		yield controls.dispatch(
			STORE_NAME,
			'invalidateResolutionForStoreSelector',
			'getFraudOutcomeTransactions'
		);

		yield controls.dispatch(
			STORE_NAME,
			'invalidateResolutionForStoreSelector',
			'getFraudOutcomeTransactionsSummary'
		);

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

		// Create success notice.
		yield controls.dispatch(
			'core/notices',
			'createSuccessNotice',
			sprintf(
				// translators: %s Order id
				__(
					'Payment for order #%s canceled successfully.',
					'woocommerce-payments'
				),
				orderId
			)
		);
	} catch ( error ) {
		yield controls.dispatch(
			'core/notices',
			'createErrorNotice',
			sprintf(
				// translators: %s Order id
				__(
					'There has been an error canceling the payment for order #%s. Please try again later.',
					'woocommerce-payments'
				),
				orderId
			)
		);
	} finally {
		yield controls.dispatch(
			STORE_NAME,
			'finishResolution',
			'getAuthorization',
			[ paymentIntentId ]
		);

		yield controls.dispatch(
			STORE_NAME,
			'setIsRequestingAuthorization',
			false
		);
	}
}

export function updateErrorForAuthorizationsSummary(
	query: Query,
	error: Error
): {
	type: string;
	query: Query;
	error: Error;
} {
	return {
		type: TYPES.SET_ERROR_FOR_AUTHORIZATIONS_SUMMARY,
		query,
		error,
	};
}
