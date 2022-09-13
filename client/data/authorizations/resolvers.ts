/** @format */

/**
 * External dependencies
 */
import { Query } from '@woocommerce/navigation';
import moment from 'moment';
import { dateI18n } from '@wordpress/date';

/**
 * Internal dependencies
 */
import {
	updateAuthorizations,
	updateAuthorization,
	updateAuthorizationsSummary,
} from './actions';
import { Authorization } from 'wcpay/types/authorizations';

export function* getAuthorizations( query: Query ): Generator< unknown > {
	// TODO: fetch authorizations data from server endpoint.
	yield updateAuthorizations( query, [] );
}

// eslint-disable-next-line @typescript-eslint/no-unused-vars
export function* getAuthorization(
	paymentIntentId: string,
	isCaptured: boolean // TODO: remove when getAuthorization switches to live data.
): Generator< unknown > {
	// TODO: fetch authorization data from server endpoint.
	yield updateAuthorization( {
		payment_intent_id: paymentIntentId,
		captured: isCaptured, // TODO: remove when getAuthorization switches to live data.
		capture_by: dateI18n(
			'M j, Y / g:iA',
			moment.utc( new Date() ).add( '7', 'days' ).local().toISOString() // TODO: remove when getAuthorization switches to live data.
		),
	} as Authorization );
}

export function* getAuthorizationsSummary(
	query: Query
): Generator< unknown > {
	// TODO: fetch authorizations summary data from server endpoint.
	yield updateAuthorizationsSummary( query, {} );
}
