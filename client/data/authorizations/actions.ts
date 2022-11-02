/** @format */
/**
 * External Dependencies
 */
import { Query } from '@woocommerce/navigation';
import { apiFetch, dispatch } from '@wordpress/data-controls';
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

export function* submitCaptureAuthorization(
	paymentIntentId: string,
	orderId: number
): Generator< unknown | Authorization > {
	try {
		yield dispatch( STORE_NAME, 'startResolution', 'getAuthorization', [
			paymentIntentId,
		] );

		const result = yield apiFetch( {
			path: `/wc/v3/payments/orders/${ orderId }/capture_authorization`,
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
		yield dispatch(
			STORE_NAME,
			'invalidateResolutionForStoreSelector',
			'getAuthorizations'
		);

		yield dispatch(
			STORE_NAME,
			'invalidateResolutionForStoreSelector',
			'getAuthorizationsSummary'
		);
		// Create success notice.
		yield dispatch(
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
		yield dispatch(
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
		yield dispatch( STORE_NAME, 'finishResolution', 'getAuthorization', [
			paymentIntentId,
		] );
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
