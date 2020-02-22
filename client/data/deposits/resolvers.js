/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import {
	updateDeposit,
	updateDeposits,
	updateErrorForDepositQuery,
} from './actions';

const convertStripePayoutToDeposit = ( stripePayout ) => ( {
	id: stripePayout.id,
	date: +new Date( stripePayout.arrival_date * 1000 ),
	type: stripePayout.amount > 0 ? 'deposit' : 'withdrawal',
	amount: stripePayout.amount,
	status: stripePayout.status,
	bankAccount: stripePayout.destination.bank_name && (
		`${ stripePayout.destination.bank_name } ` +
		`•••• ${ stripePayout.destination.last4 } ` +
		`(${ stripePayout.destination.currency.toUpperCase() })`
	),
} );

/**
 * Retrieve a single deposit from the deposits API.
 *
 * @param {string} id Identifier for specified deposit to retrieve.
 */
export function* getDeposit( id ) {
	const path = addQueryArgs( `${ NAMESPACE }/deposits/${ id }` );

	try {
		let result = yield apiFetch( { path } );

		// If using Stripe API objects directly, map to deposits.
		// TODO Remove this mapping when these deposits are coming from the server.
		if ( result.object === 'payout' ) {
			result = convertStripePayoutToDeposit( result );
		}

		yield updateDeposit( result );
	} catch ( e ) {}
}

/**
 * Retrieves a series of deposits from the deposits list API.
 *
 * @param {string} query Data on which to parameterize the selection.
 */
export function* getDeposits( query ) {
	const path = addQueryArgs(
		`${ NAMESPACE }/deposits`,
		{
			page: query.paged,
			pagesize: query.perPage,
		}
	);

	try {
		const results = yield apiFetch( { path } ) || {};

		// If using Stripe API objects directly, map to deposits.
		// TODO Remove this mapping when these deposits are coming from the server.
		if ( results.data.length && results.data[ 0 ].object === 'payout' ) {
			results.data = results.data.map( convertStripePayoutToDeposit );
		}

		yield updateDeposits( query, results.data );

		// TODO Finish resolution on getDeposit selector for each result.
	} catch ( e ) {
		yield updateErrorForDepositQuery( query, null, e );
	}
}
