/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';
import { PaymentsActivityAction, PaymentActivityState } from './types';

const receivePaymentActivity = (
	state: PaymentActivityState = {},
	{ type, data }: PaymentsActivityAction
): PaymentActivityState => {
	switch ( type ) {
		case TYPES.SET_PAYMENT_ACTIVITY_DATA:
			state = {
				...state,
				paymentActivityData: data,
			};
			break;
	}
	return state;
};

export default receivePaymentActivity;
