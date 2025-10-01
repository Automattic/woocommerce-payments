export type StatusBackgroundColor =
	| 'green'
	| 'yellow'
	| 'red'
	| 'blue'
	| 'gray';

export type BannerBackgroundColor = 'yellow' | 'red' | 'green' | 'blue';

export type IconName = 'published' | 'caution' | 'error' | 'info' | 'check';
export interface AccountDetailsData {
	account_status: {
		text: string;
		background_color: StatusBackgroundColor;
	};
	payout_status: {
		text: string;
		background_color: StatusBackgroundColor;
		icon?: IconName;
		popover?: {
			text: string;
			cta_text: string;
			cta_link: string;
		} | null;
	};
	banner?: {
		text: string;
		background_color: BannerBackgroundColor;
		cta_text?: string;
		cta_link?: string;
		icon?: IconName;
	} | null;
}

export type AccountDetailsType = AccountDetailsData | null;
