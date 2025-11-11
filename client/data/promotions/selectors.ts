/** @format */

/**
 * Internal dependencies
 */
import { PromotionsState, PromotionsData, Promotion } from './types';
import { ApiError } from '../../types/errors';

// Type for the full Redux state with promotions slice.
interface State {
	promotions: PromotionsState;
}

/**
 * Retrieves the promotions data from the state.
 *
 * @param {State} state The full Redux state.
 *
 * @return {PromotionsData | undefined} The promotions data or undefined.
 */
export const getPromotions = ( state: State ): PromotionsData | undefined => {
	return state.promotions?.promotions;
};

/**
 * Retrieves available promotions from the state.
 *
 * @param {State} state The full Redux state.
 *
 * @return {Promotion[]} Array of available promotions.
 */
export const getAvailablePromotions = ( state: State ): Promotion[] => {
	return state.promotions?.promotions?.available_promotions ?? [];
};

/**
 * Retrieves active promotions from the state.
 *
 * @param {State} state The full Redux state.
 *
 * @return {Promotion[]} Array of active promotions.
 */
export const getActivePromotions = ( state: State ): Promotion[] => {
	return state.promotions?.promotions?.active_promotions ?? [];
};

/**
 * Retrieves a specific promotion by identifier.
 *
 * @param {State} state The full Redux state.
 * @param {string} identifier The promotion identifier.
 *
 * @return {Promotion | undefined} The promotion or undefined.
 */
export const getPromotionByIdentifier = (
	state: State,
	identifier: string
): Promotion | undefined => {
	const allPromotions = [
		...getAvailablePromotions( state ),
		...getActivePromotions( state ),
	];
	return allPromotions.find( ( promo ) => promo.identifier === identifier );
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
 * Checks if there are any available promotions.
 *
 * @param {State} state The full Redux state.
 *
 * @return {boolean} True if there are available promotions.
 */
export const hasAvailablePromotions = ( state: State ): boolean => {
	return getAvailablePromotions( state ).length > 0;
};

/**
 * Checks if there are any active promotions.
 *
 * @param {State} state The full Redux state.
 *
 * @return {boolean} True if there are active promotions.
 */
export const hasActivePromotions = ( state: State ): boolean => {
	return getActivePromotions( state ).length > 0;
};
