/**
 * Internal dependencies
 */

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

export interface EligibleResult {
	result: string;
	data: Array< string >;
}

export interface EligibleData {
	business: {
		country: string;
		type: string;
		mcc: string;
		annual_revenue: string;
		go_live_timeframe: string;
	};
}

export type OnboardingSteps = 'personal' | 'business' | 'store' | 'loading';
