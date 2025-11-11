/** @format */

/**
 * Internal Dependencies
 */
import { ApiError } from '../../types/errors';
import ACTION_TYPES from './action-types';

export interface Promotion {
	identifier: string;
	name: string;
	payment_method: string;
	duration_days: number;
	status: 'offered' | 'active' | 'expired' | 'dismissed';
	activated_at?: string;
	expires_at?: string;
	days_remaining?: number;
	terms_url?: string;
}

export interface PromotionsData {
	available_promotions: Promotion[];
	active_promotions: Promotion[];
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
