export interface AccountDetailsData {
	account_status: {
		text: string;
		background_color: 'green' | 'yellow' | 'red';
	};
	payout_status: {
		text: string;
		background_color: 'green' | 'yellow' | 'red';
		icon: 'published' | 'caution' | 'error';
		popover?: {
			text: string;
			cta_text: string;
			cta_link: string;
		} | null;
	};
	banner?: {
		text: string;
		background_color: 'yellow' | 'red';
		cta_text?: string;
		cta_link?: string;
		icon: 'caution';
	} | null;
}

export type AccountDetailsType = AccountDetailsData | null;
