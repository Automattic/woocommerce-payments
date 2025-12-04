/** @format */

/**
 * Internal dependencies
 */
import { PmPromotionsState, PmPromotionsData } from './types';
import { ApiError } from '../../types/errors';

// Type for the full Redux state with pmPromotions slice.
interface State {
	pmPromotions: PmPromotionsState;
}

/**
 * Retrieves the PM promotions array from the state.
 *
 * @param {State} state The full Redux state.
 *
 * @return {PmPromotionsData} Array of promotions, or empty array if not loaded.
 */
export const getPmPromotions = ( state: State ): PmPromotionsData => {
	return state.pmPromotions?.pmPromotions ?? ( [] as PmPromotionsData );
};

/**
 * Retrieves any error that occurred while fetching PM promotions.
 *
 * @param {State} state The full Redux state.
 *
 * @return {ApiError | undefined} The error or undefined.
 */
export const getPmPromotionsError = ( state: State ): ApiError | undefined => {
	return state.pmPromotions?.pmPromotionsError;
};
