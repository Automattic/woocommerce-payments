/** @format */

/**
 * Internal Dependencies
 */
import { WCPayCharge } from './types';

export function updateCharge( id: string, data: WCPayCharge ) {
	return {
		type: 'SET_CHARGE' as const,
		id,
		data,
	};
}

export function updateErrorForCharge( id: string, error: Error ) {
	return {
		type: 'SET_ERROR_FOR_CHARGE' as const,
		id,
		error,
	};
}

export type ChargeAction = ReturnType<
	typeof updateCharge | typeof updateErrorForCharge
>;
