/** @format */

/**
 * Internal dependencies
 */
import { PromotionsState, Promotion } from './types';
import { ApiError } from '../../types/errors';

// Type for the full Redux state with promotions slice.
interface State {
	promotions: PromotionsState;
}

/**
 * Retrieves the promotions array from the state.
 *
 * @param {State} state The full Redux state.
 *
 * @return {Promotion[]} Array of promotions, or empty array if not loaded.
 */
export const getPromotions = ( state: State ): Promotion[] => {
	return state.promotions?.promotions ?? ( [] as Promotion[] );
};

/**
 * Retrieves a specific promotion by promo_id.
 *
 * @param {State} state The full Redux state.
 * @param {string} promoId The promotion identifier.
 *
 * @return {Promotion | undefined} The promotion or undefined.
 */
export const getPromotionById = (
	state: State,
	promoId: string
): Promotion | undefined => {
	return getPromotions( state ).find(
		( promo ) => promo.promo_id === promoId
	);
};

/**
 * Retrieves any error that occurred while fetching promotions.
 *
 * @param {State} state The full Redux state.
 *
 * @return {ApiError | undefined} The error or undefined.
 */
export const getPromotionsError = ( state: State ): ApiError | undefined => {
	return state.promotions?.promotionsError;
};

/**
 * Checks if there are any promotions available.
 *
 * @param {State} state The full Redux state.
 *
 * @return {boolean} True if there are promotions available.
 */
export const hasPromotions = ( state: State ): boolean => {
	return getPromotions( state ).length > 0;
};
