/** @format */

/**
 * Internal Dependencies
 */
import { ApiError } from '../../types/errors';
import ACTION_TYPES from './action-types';

export type PromotionType = 'spotlight' | 'badge';

export interface Promotion {
	id: string;
	promo_id: string;
	payment_method: string;
	payment_method_title: string;
	type: PromotionType;
	title: string;
	badge?: string;
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
export type PromotionsData = Promotion[];

export interface PromotionsState {
	promotions?: PromotionsData;
	promotionsError?: ApiError;
}

export interface PromotionsResponse {
	isLoading: boolean;
	promotions: Promotion[];
	promotionsError?: ApiError;
}

export interface UpdatePromotionsAction {
	type: ACTION_TYPES.SET_PROMOTIONS;
	data: PromotionsData;
}

export interface ErrorPromotionsAction {
	type: ACTION_TYPES.SET_ERROR_FOR_PROMOTIONS;
	error: ApiError;
}

export type PromotionsActions = UpdatePromotionsAction | ErrorPromotionsAction;
