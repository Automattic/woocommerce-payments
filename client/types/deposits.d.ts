// eslint-disable-next-line wpcalypso/import-docblock
import { TableCardColumn } from '@woocommerce/components';

export interface DepositsTableHeader extends TableCardColumn {
	key: 'details' | 'date' | 'type' | 'amount' | 'status' | 'bankAccount';
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
	status:
		| 'paid'
		| 'pending'
		| 'in_transit'
		| 'canceled'
		| 'failed'
		| 'estimated';
	bankAccount: string;
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

export interface Deposit {
	automatic: boolean;
	date: number;
	status: string; // TODO: list statuses
	amount: number;
	currency: string;
	bankAccount: string;
	fee: number;
	fee_percentage: number;
}
