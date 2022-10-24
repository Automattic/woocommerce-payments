/** @format */

/**
 * External dependencies
 */
import { Query } from '@woocommerce/navigation';
import { apiFetch } from '@wordpress/data-controls';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import {
	updateAuthorizations,
	updateAuthorization,
	updateAuthorizationsSummary,
	updateErrorForAuthorizations,
} from './actions';
import { Authorization, AuthorizationsList } from 'wcpay/types/authorizations';
import { NAMESPACE } from '../constants';
import { ApiError } from 'wcpay/types/errors';

export function* getAuthorizations( query: Query ): Generator< unknown > {
	const { paged, per_page: perPage, orderby, order } = query;
	const path = addQueryArgs( `${ NAMESPACE }/authorizations`, {
		paged,
		per_page: perPage,
		orderby,
		order,
	} );

	try {
		const result = yield apiFetch( { path } );
		yield updateAuthorizations( query, result as AuthorizationsList );
	} catch ( error ) {
		yield updateErrorForAuthorizations( query, error as ApiError );
	}
}

export function* getAuthorization(
	paymentIntentId: string
): Generator< unknown > {
	yield updateAuthorization( {
		payment_intent_id: paymentIntentId,
	} as Authorization );
}

export function* getAuthorizationsSummary(
	query: Query
): Generator< unknown > {
	yield updateAuthorizationsSummary( query, {} );
}
