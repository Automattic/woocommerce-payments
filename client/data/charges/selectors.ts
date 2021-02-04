/** @format */

/**
 * Internal dependencies
 */
import { ChargeState } from './reducer';
import { Charge } from './types';

export const getCharge = (
	state: { charges: ChargeState },
	id: string
): Charge | undefined => {
	return state.charges?.[ id ]?.data;
};

export const getChargeError = (
	state: { charges: ChargeState },
	id: string
): Error | undefined => {
	return state.charges?.[ id ]?.error;
};
