/* eslint-disable no-console */
/** @format */

/**
 * Internal Dependencies
 */
import { Query } from '@woocommerce/navigation';
import TYPES from './action-types';
import {
	AuthorizationsSummary,
	Authorization,
	RiskLevel,
} from 'wcpay/types/authorizations';
import { apiFetch, dispatch } from '@wordpress/data-controls';
import { STORE_NAME } from '../constants';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import { __ } from '@wordpress/i18n';

export function updateAuthorizations(
	query: Query,
	data: Array< Authorization >
): {
	type: string;
	data: Array< Authorization >;
	query: Query;
} {
	return {
		type: TYPES.SET_AUTHORIZATIONS,
		data,
		query,
	};
}

export function updateAuthorization(
	data: any[]
): { type: string; data: Array< Authorization > } {
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
	id: string,
	orderId: number,
	paymentIntentId: string
): Generator< unknown > {
	try {
		yield dispatch( STORE_NAME, 'startResolution', 'getAuthorization', [
			id,
		] );

		const authorization = yield apiFetch( {
			path: `/wc/v3/payments/orders/${ orderId }/capture_authorization`,
			method: 'post',
			data: {
				payment_intent_id: paymentIntentId,
			},
		} );

		yield updateAuthorization( [ authorization ] );

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
			__( 'You have captured the payment.', 'woocommerce-payments' )
		);
	} catch ( error ) {
		const message = __(
			'There has been an error capturing the payment. Please try again later.',
			'woocommerce-payments'
		);
		yield dispatch( 'core/notices', 'createErrorNotice', message );
	} finally {
		yield dispatch( STORE_NAME, 'finishResolution', 'getAuthorization', [
			id,
		] );
	}
}
