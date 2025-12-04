/** @format */

/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';
import { PmPromotionsResponse, PmPromotionActions } from './types';

/**
 * Hook to retrieve PM promotions data.
 *
 * @return {PmPromotionsResponse} The promotions data, error, and loading state.
 */
export const usePmPromotions = (): PmPromotionsResponse =>
	useSelect( ( select ) => {
		const { getPmPromotions, getPmPromotionsError, isResolving } = select(
			STORE_NAME
		);

		return {
			pmPromotions: getPmPromotions(),
			pmPromotionsError: getPmPromotionsError(),
			isLoading: isResolving( 'getPmPromotions' ),
		};
	} );

/**
 * Hook to get PM promotion actions (activate and dismiss).
 *
 * @return {PmPromotionActions} Object with activatePmPromotion and dismissPmPromotion functions.
 */
export const usePmPromotionActions = (): PmPromotionActions => {
	const { activatePmPromotion, dismissPmPromotion } = useDispatch(
		STORE_NAME
	);

	return {
		activatePmPromotion,
		dismissPmPromotion,
	};
};
