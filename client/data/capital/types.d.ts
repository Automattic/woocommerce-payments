/** @format */

/**
 * Internal Dependencies
 */
import { ApiError } from '../../types/errors';
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

export interface CapitalLoan {
	stripe_loan_id: string;
	amount: number;
	currency: string;
	fee_amount: number;
	withhold_rate: number;
	paid_out_at: string;
	first_paydown_at: string;
	fully_paid_at: string | null;
}

export interface LoansList {
	data: CapitalLoan[];
}

export interface SummaryResponse {
	isLoading: boolean;
	summary?: Summary;
	summaryError?: ApiError;
}

export interface LoansResponse {
	isLoading: boolean;
	loans: CapitalLoan[];
	loansError?: ApiError;
}

export interface CapitalState {
	summary?: Summary;
	summaryError?: ApiError;
	loans?: CapitalLoan[];
	loansError?: ApiError;
}

export interface UpdateSummaryAction {
	type: ACTION_TYPES.SET_ACTIVE_LOAN_SUMMARY;
	data: Summary;
}

export interface ErrorSummaryAction {
	type: ACTION_TYPES.SET_ERROR_FOR_ACTIVE_LOAN_SUMMARY;
	error: ApiError;
}

export interface UpdateLoansAction {
	type: ACTION_TYPES.SET_LOANS;
	data: CapitalLoan[];
}

export interface ErrorLoansAction {
	type: ACTION_TYPES.SET_ERROR_FOR_LOANS;
	error: ApiError;
}
