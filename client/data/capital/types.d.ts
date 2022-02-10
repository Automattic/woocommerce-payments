/** @format */

/**
 * Internal Dependencies
 */
import ACTION_TYPES from './action-types';

export interface Summary {
	details: {
		advance_amount: number;
		advance_paid_out_at: number;
		currency: string;
		current_repayment_interval: {
			due_at: number;
			paid_amount: number;
			remaining_amount: number;
		};
		fee_amount: number;
		paid_amount: number;
		remaining_amount: number;
		repayments_begin_at: number;
		withhold_rate: number;
	};
}

export interface ApiError {
	code: string;
}

export interface SummaryResponse {
	isLoading: boolean;
	summary?: Summary;
	summaryError?: ApiError;
}

export interface CapitalState {
	summary?: Summary;
	summaryError?: ApiError;
}

export interface State {
	capital?: CapitalState;
}

export interface UpdateSummaryAction {
	type: ACTION_TYPES.SET_ACTIVE_LOAN_SUMMARY;
	data: Summary;
}

export interface ErrorSummaryAction {
	type: ACTION_TYPES.SET_ERROR_FOR_ACTIVE_LOAN_SUMMARY;
	error: ApiError;
}
