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
import { apiFetch } from '@wordpress/data-controls';
import { dispatch } from '@wordpress/data';
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
): unknown {
	try {
		console.log( `Capturing authorization: ${ id }` );

		yield dispatch( STORE_NAME ).startResolution( 'getAuthorization', [
			id,
		] );

		let authorization = yield apiFetch( {
			path: `/wc/v3/payments/orders/${ orderId }/capture_authorization`,
			method: 'post',
			data: {
				payment_intent_id: paymentIntentId,
			},
		} );

		// TODO replace mocked implementation when server is ready
		const randomAmount = () => {
			return 1400 + Math.floor( Math.random() * 5000 );
		};

		const randomDate = dateI18n(
			'M j, Y / g:iA',
			moment.utc( new Date() ).local().toISOString()
		);

		const randomCaptureDate = dateI18n(
			'M j, Y / g:iA',
			moment.utc( new Date() ).add( '7', 'days' ).local().toISOString()
		);

		const randomRisk = (): RiskLevel => {
			const risks: Array< RiskLevel > = [ 'elevated', 'normal', 'high' ];

			return risks[ Math.floor( Math.random() * risks.length ) ];
		};

		authorization = {
			authorization_id: id,
			authorized_on: randomDate,
			capture_by: randomCaptureDate,
			order: {
				number: 242,
				customer_url: 'https://doggo.com',
				url: 'https://doggo.com',
			},
			risk_level: randomRisk(),
			amount: randomAmount(),
			customer_email: 'good_boy@doge.com',
			customer_country: 'Kingdom of Dogs',
			customer_name: 'Good boy',
			payment_intent_id: 'pi_3LaKsRQsDOQXPzI10DbyuBgI',
		};
		yield updateAuthorization( [ authorization ] );

		// Need to invalidate the resolution so that the components will render again.
		yield dispatch( STORE_NAME ).invalidateResolutionForStoreSelector(
			'getAuthorizations'
		);
		yield dispatch( STORE_NAME ).invalidateResolutionForStoreSelector(
			'getAuthorizationsSummary'
		);

		yield dispatch( 'core/notices' ).createSuccessNotice(
			__( 'You have captured the payment.', 'woocommerce-payments' )
		);
	} catch ( error ) {
		const message = __(
			'There has been an error capturing the payment. Please try again later.',
			'woocommerce-payments'
		);
		yield dispatch( 'core/notices' ).createErrorNotice( message );
	} finally {
		yield dispatch( STORE_NAME ).finishResolution( 'getAuthorization', [
			id,
		] );
	}
}
