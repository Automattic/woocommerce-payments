/** @format */

/**
 * External dependencies
 */
import { apiFetch, dispatch } from '@wordpress/data-controls';
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
			( result as GetAuthorizationsApiResponse ).data ?? []
		);
	} catch ( error ) {
		yield dispatch(
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
	paymentIntentId: string
): Generator< unknown > {
	// TODO this is implemented in other PR: https://github.com/Automattic/woocommerce-payments/pull/5006/
	yield updateAuthorization( {
		payment_intent_id: paymentIntentId,
	} as Authorization );
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
		yield dispatch(
			'core/notices',
			'createErrorNotice',
			__(
				'Error retrieving uncaptured transactions.',
				'woocommerce-payments'
			)
		);
		yield updateErrorForAuthorizationsSummary( query, e );
	}
}
