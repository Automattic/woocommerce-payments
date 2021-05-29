/** @format */

/**
 * Internal dependencies
 */
import { getResourceId } from 'utils/data';

/**
 * Retrieves the deposits state from the wp.data store if the state
 * has been initialized, otherwise returns an empty state.
 *
 * @param {Object} state Current wp.data state.
 *
 * @return {Object} The deposits state.
 */
const getDepositsState = ( state ) => {
	if ( ! state ) {
		return {};
	}

	return state.deposits || {};
};

export const getDeposit = ( state, id ) => {
	const depositById = getDepositsState( state ).byId || {};
	return depositById[ id ];
};

export const getDepositsOverview = ( state ) => {
	const DepositsOverview = getDepositsState( state ).overview || {};
	return DepositsOverview.data;
};

export const getDepositsOverviewError = ( state ) => {
	const DepositsOverview = getDepositsState( state ).overview || {};
	return DepositsOverview.error;
};

/**
 * Prepares and returns all deposits' overviews from the state.
 *
 * The API returns this in the form of separate arrays
 * for each balance/deposit type, each of them containing
 * a separate entry for each currency.
 *
 * This selector will return the account, as well as an
 * array with all of the neccessary details grouped by currency.
 *
 * Check the `AccountOverview.OverviewsResponse` declaration
 * for the shape of the returned object.
 *
 * @param {Object} state Current wp.data state.
 * @return {Object} A complex object, containing all neccessary overviews.
 */
export const getAllDepositsOverviews = ( state ) => {
	const DepositsOverview = getDepositsState( state ).overviews || {};

	// Return an empty skeleton if data has not been loaded yet.
	if ( ! DepositsOverview.data ) {
		return {
			account: null,
			currencies: [],
		};
	}

	const { deposit, balance, account } = DepositsOverview.data;

	const groups = {
		lastPaid: deposit.last_paid,
		nextScheduled: deposit.next_scheduled,
		pending: balance.pending,
		available: balance.available,
		instant: balance.instant,
	};

	const currencies = {};
	for ( const [ key, values ] of Object.entries( groups ) ) {
		values?.forEach( ( value ) => {
			const { currency } = value;

			if ( ! currencies[ currency ] ) {
				currencies[ currency ] = {
					currency,
					lastPaid: undefined,
					nextScheduled: undefined,
					pending: undefined,
					available: undefined,
					instant: undefined,
				};
			}

			// There will be a single deposit/balance per currency, no arrays here.
			currencies[ currency ][ key ] = value;
		} );
	}

	const currenciesArray = Object.values( currencies );
	return {
		account,

		// The default currency should appear at the top of the list.
		currencies: [
			...currenciesArray.filter(
				( currency ) => account.default_currency === currency.currency
			),
			...currenciesArray.filter(
				( currency ) => account.default_currency !== currency.currency
			),
		],
	};
};

export const getAllDepositsOverviewsError = ( state ) => {
	const DepositsOverview = getDepositsState( state ).overviews || {};
	return DepositsOverview.error;
};

/**
 * Retrieves the deposits corresponding to the provided query or a sane
 * default if they don't exist.
 *
 * @param {Object} state Current wp.data state.
 * @param {Object} query The deposits query.
 *
 * @return {Object} The list of deposits for the given query.
 */
const getDepositsForQuery = ( state, query ) => {
	const index = getResourceId( query );
	const queries = getDepositsState( state ).queries || {};
	return queries[ index ] || {};
};

export const getDeposits = ( state, query ) => {
	const ids = getDepositsForQuery( state, query ).data || [];
	return ids.map( getDeposit.bind( this, state ) );
};

export const getDepositsCount = ( state ) => {
	return getDepositsState( state ).count;
};

export const getDepositQueryError = ( state, query ) => {
	return getDepositsForQuery( state, query ).error || {};
};

/**
 * Retrieves the deposits summary corresponding to the provided query.
 *
 * @param {Object} state Current wp.data state.
 * @param {Object} query The deposits summary query.
 *
 * @return {Object} The deposits summary for the given query.
 */
const getDepositsSummaryForQuery = ( state, query ) => {
	const index = getResourceId( query );
	const summary = getDepositsState( state ).summary || {};
	return summary[ index ] || {};
};

export const getDepositsSummary = ( state, query ) => {
	return getDepositsSummaryForQuery( state, query ).data || {};
};

export const getDepositsSummaryError = ( state, query ) => {
	return getDepositsSummaryForQuery( state, query ).error || {};
};

export const getInstantDeposit = ( state ) => {
	const instantDeposit = getDepositsState( state ).instant || {};
	return instantDeposit.data;
};
