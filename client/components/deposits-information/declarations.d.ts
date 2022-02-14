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

	interface InstantBalance {
		currency: string;
		amount: number;
		fee: number;
		net: number;
		fee_percentage: number;
		transaction_ids: Array< string >;
	}

	interface Overview {
		currency: string;
		lastPaid: Deposit;
		nextScheduled: Deposit;
		pending: Balance;
		available: Balance;
		instant: InstantBalance;
	}

	interface OverviewsResponse {
		overviews: {
			account: Account;
			currencies: Array< Overview >;
		};
		isLoading: boolean;
	}
}

declare module 'gridicons' {
	type GridiconParams = {
		icon: string;
		size?: number;
		className?: string;
	};
	const Gridicon: ( props: GridiconParams ) => JSX.Element;

	export = Gridicon;
}

declare module '@woocommerce/components' {
	type LinkParams = {
		href: string;
		children: React.ReactNode;
	};
	const Link: ( props: LinkParams ) => JSX.Element;
}
