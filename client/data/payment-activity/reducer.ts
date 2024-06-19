/** @format */

/**
 * Internal dependencies
 */
import { getResourceId } from 'wcpay/utils/data';
import TYPES from './action-types';
import { PaymentActivityAction, PaymentActivityState } from './types';

const receivePaymentActivity = (
	state: PaymentActivityState = {},
	{ type, query, data }: PaymentActivityAction
): PaymentActivityState => {
	if ( ! query ) {
		return state;
	}

	/*
	Responses are stored in a key-value store where the key is a unique identifier for the query.
	This is consistent with other query-based stores (i.e. transactions, disputes, etc.)
	It allows us to temporarily cache responses to avoid re-fetching identical data.
	For example, when a user is comparing two date ranges, we can store the responses for each date range
	and switch between them without re-fetching.
	This data is not persisted between browser sessions (e.g. on page refresh).
	*/
	const index = getResourceId( query );

	switch ( type ) {
		case TYPES.SET_PAYMENT_ACTIVITY_DATA:
			state = {
				...state,
				[ index ]: data,
			};
			break;
	}
	return state;
};

export default receivePaymentActivity;
