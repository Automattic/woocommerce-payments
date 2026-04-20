// eslint-disable-next-line wpcalypso/import-docblock
import { TableCardColumn } from '@woocommerce/components';

export interface DepositsTableHeader extends TableCardColumn {
	key:
		| 'details'
		| 'date'
		| 'type'
		| 'amount'
		| 'status'
		| 'bankAccount'
		| 'bankReferenceId';
	cellClassName?: string;
}

export interface CachedDeposits {
	deposits: CachedDeposit[];
	isLoading: boolean;
	depositsCount: number;
	depositsError?: string;
}

export interface CachedDeposit {
	id: string;
	date: string;
	type: 'deposit' | 'withdrawal';
	amount: number;
	currency: string;
	fee_percentage: number;
	fee: number;
	status: DepositStatus;
	bankAccount: string;
	automatic: boolean;
	bank_reference_key: string;
	failure_code: PayoutFailureCode;
	failure_message: string;
}

export interface DepositsSummaryCache {
	depositsSummary: DepositsSummary;
	isLoading: boolean;
}

export interface DepositsSummary {
	store_currencies: string[];
	count: number;
	total: number;
	currency: string;
}

export type DepositStatus =
	| 'paid'
	| 'pending'
	| 'in_transit'
	| 'canceled'
	| 'failed';

export type PayoutFailureCode =
	| 'insufficient_funds'
	| 'bank_account_restricted'
	| 'debit_not_authorized'
	| 'invalid_card'
	| 'declined'
	| 'invalid_transaction'
	| 'refer_to_card_issuer'
	| 'unsupported_card'
	| 'lost_or_stolen_card'
	| 'invalid_issuer'
	| 'expired_card'
	| 'could_not_process'
	| 'invalid_account_number'
	| 'incorrect_account_holder_name'
	| 'account_closed'
	| 'no_account'
	| 'exceeds_amount_limit'
	| 'account_frozen'
	| 'issuer_unavailable'
	| 'invalid_currency'
	| 'incorrect_account_type'
	| 'incorrect_account_holder_details'
	| 'bank_ownership_changed'
	| 'exceeds_count_limit'
	| 'incorrect_account_holder_address'
	| 'incorrect_account_holder_tax_id'
	| 'invalid_account_number_length';
