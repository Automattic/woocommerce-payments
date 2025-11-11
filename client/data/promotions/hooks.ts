/** @format */

/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';
import { PromotionsResponse } from './types';

/**
 * Hook to retrieve promotions data.
 *
 * @return {PromotionsResponse} The promotions data, error, and loading state.
 */
export const usePromotions = (): PromotionsResponse =>
	useSelect( ( select ) => {
		const { getPromotions, getPromotionsError, isResolving } = select(
			STORE_NAME
		);

		return {
			promotions: getPromotions(),
			promotionsError: getPromotionsError(),
			isLoading: isResolving( 'getPromotions' ),
		};
	} );

/**
 * Hook to get promotion actions (activate and dismiss).
 *
 * @return {Object} Object with activatePromotion and dismissPromotion functions.
 */
export const usePromotionActions = () => {
	const { activatePromotion, dismissPromotion } = useDispatch( STORE_NAME );

	return {
		activatePromotion,
		dismissPromotion,
	};
};
