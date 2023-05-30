/**
 * Internal dependencies
 */

export type OnboardingSteps =
	| 'mode'
	| 'personal'
	| 'business'
	| 'store'
	| 'loading';

export type OnboardingFields = {
	email?: string;
	'individual.first_name'?: string;
	'individual.last_name'?: string;
	phone?: string;
	business_name?: string;
	url?: string;
	country?: string;
	business_type?: string;
	'company.structure'?: string;
	mcc?: string;
	annual_revenue?: string;
	go_live_timeframe?: string;
};

export interface POEligibleResult {
	result: 'eligible' | 'not_eligible';
}

export interface POEligibleData {
	business: {
		country: string;
		type: string;
		mcc: string;
	};
	store: {
		annual_revenue: string;
		go_live_timeframe: string;
	};
}

export type TempData = {
	phoneCountryCode?: string;
};

export interface Country {
	key: string;
	name: string;
	types: BusinessType[];
}

export interface BusinessType {
	key: string;
	name: string;
	description: string;
	structures: BusinessStructure[];
}

export interface BusinessStructure {
	key: string;
	name: string;
}

export interface MccsDisplayTreeItem {
	id: string;
	type: string;
	title: string;
	items?: MccsDisplayTreeItem[];
	mcc?: number;
	keywords?: string[];
}
