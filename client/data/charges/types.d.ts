/** @format */

/**
 * Internal Dependencies
 */
import { Charge } from '../../types/charges';
import { ApiError } from '../../types/errors';

export interface ChargeResponse {
	data: Charge;
	error: ApiError;
	isLoading: boolean;
}
