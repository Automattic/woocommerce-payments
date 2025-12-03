/** @format */

/**
 * Internal Dependencies
 */
import { ApiError } from '../../types/errors';
import { ChipType } from 'wcpay/components/chip';
import ACTION_TYPES from './action-types';

export type PmPromotionType = 'spotlight' | 'badge';

export interface PmPromotion {
	id: string;
	promo_id: string;
	payment_method: string;
	payment_method_title: string;
	type: PmPromotionType;
	title: string;
	badge_text?: string;
	badge_type?: ChipType;
	description: string;
	cta_label: string;
	tc_url: string;
	tc_label: string;
	footnote?: string;
	image?: string;
}

/**
 * The API returns an array of promotions directly.
 */
export type PmPromotionsData = PmPromotion[];

export interface PmPromotionsState {
	pmPromotions?: PmPromotionsData;
	pmPromotionsError?: ApiError;
}

export interface PmPromotionsResponse {
	isLoading: boolean;
	pmPromotions: PmPromotion[];
	pmPromotionsError?: ApiError;
}

export interface UpdatePmPromotionsAction {
	type: ACTION_TYPES.SET_PM_PROMOTIONS;
	data: PmPromotionsData;
}

export interface ErrorPmPromotionsAction {
	type: ACTION_TYPES.SET_ERROR_FOR_PM_PROMOTIONS;
	error: ApiError;
}

export type PmPromotionsActions =
	| UpdatePmPromotionsAction
	| ErrorPmPromotionsAction;
