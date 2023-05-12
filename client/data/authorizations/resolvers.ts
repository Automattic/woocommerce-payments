/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';
import { Query } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import {
	updateAuthorizations,
	updateAuthorization,
	updateAuthorizationsSummary,
	updateErrorForAuthorizations,
	updateErrorForAuthorizationsSummary,
} from './actions';
import {
	Authorization,
	GetAuthorizationApiResponse,
	GetAuthorizationsApiResponse,
} from 'wcpay/types/authorizations';
import { NAMESPACE } from '../constants';
import { ApiError } from 'wcpay/types/errors';

export function* getAuthorizations( query: Query ): Generator< unknown > {
	let {
		paged = 1,
		per_page: perPage = 25,
		orderby = 'created',
		order = 'asc',
	} = query;

	if ( orderby === 'capture_by' ) {
		// The API does not expect 'capture_by' to be a valid sorting field, since it is a derived field, calculated from the 'created' field.
		orderby = 'created';
	}

	const path = addQueryArgs( `${ NAMESPACE }/authorizations`, {
		page: paged,
		pagesize: perPage,
		sort: orderby,
		direction: order,
	} );

	try {
		const result = yield apiFetch( { path } );
		yield updateAuthorizations(
			query,
			( result as GetAuthorizationsApiResponse ).data ?? []
		);
	} catch ( error ) {
		yield controls.dispatch(
			'core/notices',
			'createErrorNotice',
			__(
				'Error retrieving uncaptured transactions.',
				'woocommerce-payments'
			)
		);
		yield updateErrorForAuthorizations( query, error as ApiError );
	}
}

export function* getAuthorization(
	requestedPaymentIntentId: string
): Generator< unknown > {
	try {
		if ( requestedPaymentIntentId ) {
			const result = yield apiFetch( {
				path: `${ NAMESPACE }/authorizations/${ requestedPaymentIntentId }`,
			} );

			const {
				is_captured: isCaptured,
				payment_intent_id: paymentIntentId,
				created,
			} = result as GetAuthorizationApiResponse;

			yield updateAuthorization( {
				payment_intent_id: paymentIntentId,
				captured: isCaptured,
				created,
			} as Authorization );
		}
	} catch ( e ) {
		// We might get an error when there is no authorization because the payment was not set to be captured later.
		// We don't want to display an error to the merchant in that case.
		if ( ( e as ApiError ).code !== 'authorization_missing' ) {
			yield controls.dispatch(
				'core/notices',
				'createErrorNotice',
				__( 'Error retrieving authorization.', 'woocommerce-payments' )
			);
		}
	}
}

export function* getAuthorizationsSummary( query: Query ): any {
	const path = addQueryArgs( `${ NAMESPACE }/authorizations/summary`, {
		pagesize: query.per_page,
		sort: query.orderby,
		direction: query.order,
		page: query.paged,
	} );

	try {
		const results = yield apiFetch( { path } );
		yield updateAuthorizationsSummary( query, results ?? [] );
	} catch ( e ) {
		yield controls.dispatch(
			'core/notices',
			'createErrorNotice',
			__(
				'Error retrieving uncaptured transactions.',
				'woocommerce-payments'
			)
		);
		yield updateErrorForAuthorizationsSummary( query, e as Error );
	}
}
