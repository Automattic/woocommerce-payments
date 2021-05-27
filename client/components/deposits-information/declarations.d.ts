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
		automatic: boolean;
		currency: string | null;
		bankAccount: string | null;
		created: number;
		date: number;
		fee: number;
		fee_percentage: number;
		status: string;
	}

	interface Overview {
		currency: string;
		lastPaid: Deposit;
		nextScheduled: Deposit;
		pending: Balance;
		available: Balance;
	}

	interface OverviewsResponse {
		overviews: {
			account: Account;
			currencies: Array< Overview >;
		};
		isLoading: boolean;
	}
}

declare module 'data' {
	function useAllDeposistsOverviews(): AccountOverview.OverviewsResponse;
}

declare module 'gridicons';
declare module '@wordpress/i18n';
declare module '@wordpress/components';
declare module '@wordpress/i18n';
declare module 'components/loadable';
declare module 'components/details-link';
declare module 'deposits/overview';
declare module 'utils/currency';
