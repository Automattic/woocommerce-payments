/** @format */

/**
 * Internal dependencies
 */
import { ApiError } from '../../types/errors';
import { ACTION_TYPES } from './action-types';

export type DisputeReadinessSignalStatus = 'complete' | 'incomplete';

export interface DisputeReadinessSignal {
	id: string;
	status: DisputeReadinessSignalStatus;
	label: string;
	actionLabel?: string;
	actionUrl?: string;
}

export interface DisputeReadinessDismissal {
	isDismissed: boolean;
	isStoredDismissal: boolean;
	reappearReason?: string | null;
	dismissedAt?: string | null;
	scoreAtDismissal?: number;
	totalAtDismissal?: number;
	incompleteSignalIds?: string[];
}

export interface DisputeReadinessOverview {
	enabled: boolean;
	hidden?: boolean;
	score: number;
	total: number;
	state: DisputeReadinessSignalStatus;
	isDismissed: boolean;
	completeSignalIds: string[];
	incompleteSignalIds: string[];
	signals: DisputeReadinessSignal[];
	dismissal?: DisputeReadinessDismissal;
}

export interface DisputeReadinessData {
	overview?: DisputeReadinessOverview;
}

export interface DisputeReadinessState {
	disputeReadiness?: DisputeReadinessData;
	disputeReadinessError?: ApiError;
}

export interface DisputeReadinessResponse {
	disputeReadiness?: DisputeReadinessData;
	disputeReadinessError?: ApiError;
	isLoading: boolean;
}

export interface DisputeReadinessActions {
	dismissDisputeReadinessCard: () => void;
}

export interface UpdateDisputeReadinessAction {
	type: ACTION_TYPES.SET_DISPUTE_READINESS;
	data: DisputeReadinessData;
}

export interface ErrorDisputeReadinessAction {
	type: ACTION_TYPES.SET_ERROR_FOR_DISPUTE_READINESS;
	error: ApiError;
}

export type DisputeReadinessAction =
	| UpdateDisputeReadinessAction
	| ErrorDisputeReadinessAction;
