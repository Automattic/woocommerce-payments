/** @format */

/**
 * Internal dependencies
 */
import { PmPromotionsState, PmPromotion } from './types';
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
 * @return {PmPromotion[]} Array of promotions, or empty array if not loaded.
 */
export const getPmPromotions = ( state: State ): PmPromotion[] => {
	return state.pmPromotions?.pmPromotions ?? ( [] as PmPromotion[] );
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
