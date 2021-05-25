/* eslint-disable camelcase */
declare namespace AccountOverview {
	interface Account {
		default_currency: string;
		deposits_disabled: boolean;
		deposits_schedule: {
			delay_days: number;
			interval: string;
			weekly_anchor: string;
		};
	}

	interface Balance {
		amount: number;
		currency: string;
		deposits_count?: number;
		source_types: Record< string, never >[];
	}

	interface Deposit {
		id: string;
		type: string;
		amount: number;
		currency: string | null;
		bankAccount: string | null;
		created: number;
		date: number;
		status: string;
	}

	interface Overview {
		account: Account;
		balance: {
			available: Balance;
			pending: Balance;
		};
		last_deposit: Deposit | null;
		next_deposit: Deposit | null;
	}

	interface OverviewResponse {
		overview: Overview;
		isLoading: boolean;
	}
}

declare module 'data' {
	function useDepositsOverview(): AccountOverview.OverviewResponse;
}

declare module 'components/loadable';

declare module '@wordpress/components';

declare module '@wordpress/i18n';
