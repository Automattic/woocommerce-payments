/** @format */

/**
 * External dependencies
 */
import { Query } from '@woocommerce/navigation';
import { apiFetch, dispatch } from '@wordpress/data-controls';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import {
	updateAuthorizations,
	updateAuthorization,
	updateAuthorizationsSummary,
	updateErrorForAuthorizations,
} from './actions';
import {
	Authorization,
	GetAuthorizationApiResponse,
	GetAuthorizationsApiResponse,
} from 'wcpay/types/authorizations';
import { NAMESPACE } from '../constants';
import { ApiError } from 'wcpay/types/errors';

export function* getAuthorizations( query: Query ): Generator< unknown > {
	const {
		paged = 1,
		per_page: perPage = 25,
		orderby = 'created',
		order = 'desc',
	} = query;

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
			( result as GetAuthorizationsApiResponse ).data
		);
	} catch ( error ) {
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
			} = result as GetAuthorizationApiResponse;

			yield updateAuthorization( {
				payment_intent_id: paymentIntentId,
				captured: isCaptured,
			} as Authorization );
		}
	} catch ( e ) {
		// We might get an error when there is no authorization because the payment was not set to be captured later.
		// We don't want to display an error to the merchant in that case.
		if ( ( e as ApiError ).code !== 'authorization_missing' ) {
			yield dispatch(
				'core/notices',
				'createErrorNotice',
				__( 'Error retrieving authorization.', 'woocommerce-payments' )
			);
		}
	}
}

export function* getAuthorizationsSummary(
	query: Query
): Generator< unknown > {
	// TODO this is implemented in other PR: https://github.com/Automattic/woocommerce-payments/pull/4982
	yield updateAuthorizationsSummary( query, {} );
}
