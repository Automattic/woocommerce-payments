declare type OnboardingSteps =
	| 'mode'
	| 'personal'
	| 'business'
	| 'store'
	| 'loading';

declare type OnboardingFields = {
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

declare interface EligibleResult {
	result: 'eligible' | 'not_eligible';
}

declare interface EligibleData {
	business: {
		country: string;
		type: string;
		mcc: string;
		annual_revenue: string;
		go_live_timeframe: string;
	};
}

declare type TempData = {
	phoneCountryCode?: string;
};

declare interface BusinessStructure {
	key: string;
	name: string;
}

declare interface BusinessType {
	key: string;
	name: string;
	description: string;
	structures: BusinessStructure[];
}

declare interface Country {
	key: string;
	name: string;
	types: BusinessType[];
}
