/** @format */

/**
 * Internal Dependencies
 */
import { ApiError } from '../../types/errors';
import ACTION_TYPES from './action-types';

export interface PromotionVariation {
	id: string;
	type: string;
	badge?: string;
	badge_type?: string;
	heading: string;
	description: string;
	cta_label: string;
	cta_url: string;
	tc_url?: string;
}

export interface Promotion {
	promo_id: string;
	discount_rate: string;
	duration_days: number;
	variations: PromotionVariation[];
}

export interface PromotionsData {
	available_promotions: Promotion[];
	active_promotions: string[];
}

export interface PromotionsState {
	promotions?: PromotionsData;
	promotionsError?: ApiError;
}

export interface PromotionsResponse {
	isLoading: boolean;
	promotions?: PromotionsData;
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
