/**
 * Internal dependencies
 */
import { DepositStatus } from './deposits';

export interface Account {
	default_currency: string;
	deposits_blocked: boolean;
	deposits_disabled: boolean;
	deposits_schedule: {
		/**
		 * Number of days it takes for a charge to become available for deposit.
		 */
		delay_days: number;
		/**
		 * Deposit schedule interval. 'manual' means deposits are not scheduled.
		 */
		interval: 'manual' | 'daily' | 'weekly' | 'monthly';
		/**
		 * Weekly anchor is a day of the week eg 'monday'
		 */
		weekly_anchor: string;
		/**
		 * The day of the month when available funds are paid out, specified as a number between 1–31. 29 - 31 will instead use the last day of a shorter month.
		 */
		monthly_anchor: number;
	};
}

export interface Balance {
	amount: number;
	currency: string;
	deposits_count?: number;
	source_types: Record< string, never >[];
}

export interface Deposit {
	id: string;
	type: string;
	amount: number;
	automatic: boolean;
	currency: string | null;
	bankAccount: string | null;
	created: number;
	date: number;
	fee: number;
	fee_percentage: number;
	status: DepositStatus;
}

export interface InstantBalance {
	currency: string;
	amount: number;
	fee: number;
	net: number;
	fee_percentage: number;
	transaction_ids: Array< string >;
}

export interface Overview {
	currency: string;
	lastPaid: Deposit | undefined;
	nextScheduled: Deposit | undefined;
	pending: Balance | undefined;
	available: Balance | undefined;
	instant: InstantBalance | undefined;
}

export interface OverviewsResponse {
	overviews: {
		account: Account | null;
		currencies: Overview[];
	};
	isLoading: boolean;
}
