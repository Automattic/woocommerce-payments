/** @format */

/**
 * Internal dependencies
 */
import { PromotionsState, PromotionsData, Promotion } from './types';
import { ApiError } from '../../types/errors';

/**
 * Retrieves the promotions data from the state.
 *
 * @param {PromotionsState} state The current state.
 *
 * @return {PromotionsData | undefined} The promotions data or undefined.
 */
export const getPromotions = (
	state: PromotionsState
): PromotionsData | undefined => {
	return state.promotions;
};

/**
 * Retrieves available promotions from the state.
 *
 * @param {PromotionsState} state The current state.
 *
 * @return {Promotion[]} Array of available promotions.
 */
export const getAvailablePromotions = (
	state: PromotionsState
): Promotion[] => {
	return state.promotions?.available_promotions ?? [];
};

/**
 * Retrieves active promotions from the state.
 *
 * @param {PromotionsState} state The current state.
 *
 * @return {Promotion[]} Array of active promotions.
 */
export const getActivePromotions = ( state: PromotionsState ): Promotion[] => {
	return state.promotions?.active_promotions ?? [];
};

/**
 * Retrieves a specific promotion by identifier.
 *
 * @param {PromotionsState} state The current state.
 * @param {string} identifier The promotion identifier.
 *
 * @return {Promotion | undefined} The promotion or undefined.
 */
export const getPromotionByIdentifier = (
	state: PromotionsState,
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
 * @param {PromotionsState} state The current state.
 *
 * @return {ApiError | undefined} The error or undefined.
 */
export const getPromotionsError = (
	state: PromotionsState
): ApiError | undefined => {
	return state.promotionsError;
};

/**
 * Checks if there are any available promotions.
 *
 * @param {PromotionsState} state The current state.
 *
 * @return {boolean} True if there are available promotions.
 */
export const hasAvailablePromotions = ( state: PromotionsState ): boolean => {
	return getAvailablePromotions( state ).length > 0;
};

/**
 * Checks if there are any active promotions.
 *
 * @param {PromotionsState} state The current state.
 *
 * @return {boolean} True if there are active promotions.
 */
export const hasActivePromotions = ( state: PromotionsState ): boolean => {
	return getActivePromotions( state ).length > 0;
};
