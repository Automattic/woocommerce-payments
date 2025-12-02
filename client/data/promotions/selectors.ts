/** @format */

/**
 * Internal dependencies
 */
import { PromotionsState, Promotion, PromotionType } from './types';
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
 * Retrieves a specific promotion by id.
 *
 * @param {State}  state The full Redux state.
 * @param {string} id    The promotion unique identifier.
 *
 * @return {Promotion | undefined} The promotion or undefined.
 */
export const getPromotionById = (
	state: State,
	id: string
): Promotion | undefined => {
	return getPromotions( state ).find( ( promo ) => promo.id === id );
};

/**
 * Retrieves promotions for a specific payment method.
 *
 * @param {State}  state         The full Redux state.
 * @param {string} paymentMethod The payment method ID.
 *
 * @return {Promotion[]} Array of promotions for the payment method.
 */
export const getPromotionsByPaymentMethod = (
	state: State,
	paymentMethod: string
): Promotion[] => {
	return getPromotions( state ).filter(
		( promo ) => promo.payment_method === paymentMethod
	);
};

/**
 * Retrieves the first promotion of a specific type.
 *
 * @param {State}         state The full Redux state.
 * @param {PromotionType} type  The promotion type ('spotlight' or 'badge').
 *
 * @return {Promotion | undefined} The first promotion of the type or undefined.
 */
export const getPromotionByType = (
	state: State,
	type: PromotionType
): Promotion | undefined => {
	return getPromotions( state ).find( ( promo ) => promo.type === type );
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
