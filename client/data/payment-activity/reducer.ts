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
