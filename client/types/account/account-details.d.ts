/**
 * Current design only uses `green`, `yellow`, `red` but add all front-end assisted options.
 */
export type StatusBackgroundColor =
	| 'green'
	| 'yellow'
	| 'red'
	| 'blue'
	| 'gray';

/**
 * Current design only uses `yellow`, `red` but add all front-end assisted options.
 */
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
		/**
		 * Current design only use `published` `caution` `error`, but it can support any icon in https://wordpress.github.io/gutenberg/?path=/docs/icons-icon--docs
		 */
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
		/**
		 * Current design only use `caution` but it can support any icon in https://wordpress.github.io/gutenberg/?path=/docs/icons-icon--docs.
		 */
		icon?: IconName;
	} | null;
}

export type AccountDetailsType = AccountDetailsData | null;
