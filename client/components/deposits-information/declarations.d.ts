declare namespace AccountOverview {
	interface Account {
		default_currency: string;
		deposits_blocked: boolean;
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

	interface InstantBalance {
		currency: string;
		amount: number;
		fee: number;
		net: number;
		fee_percentage: number;
		transaction_ids: Array< string >;
	}

	interface Overview {
		available: Balance;
		currency: string;
		instant: InstantBalance;
		lastDayManualDeposit:
			| {
					currency: string;
					date: string;
			  }
			| undefined;
		lastPaid: Deposit;
		nextScheduled: Deposit;
		pending: Balance;
		standard: Balance & { transaction_ids: Array< string > };
	}

	interface OverviewsResponse {
		overviews: {
			account: Account;
			currencies: Array< Overview >;
		};
		isLoading: boolean;
	}
}

declare module '@woocommerce/components' {
	type LinkParams = {
		href: string;
		children?: React.ReactNode;
		type?: string;
	};
	const Link: ( props: LinkParams ) => JSX.Element;
}
