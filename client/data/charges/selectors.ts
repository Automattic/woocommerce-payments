/** @format */

/**
 * Internal dependencies
 */
import { ChargeState } from './reducer';
import { WCPayCharge } from './types';

export const getCharge = (
	state: { charges: ChargeState },
	id: string
): WCPayCharge | undefined => {
	return state.charges?.data?.[ id ];
};

export const getChargeError = (
	state: { charges: ChargeState },
	id: string
): Error | undefined => {
	return state.charges?.errors?.[ id ];
};
