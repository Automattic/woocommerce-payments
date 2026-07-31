/**
 * Internal dependencies
 */

export type OnboardingSteps = 'business' | 'store' | 'embedded' | 'loading';

export type OnboardingFields = {
	country?: string;
	business_type?: string;
	// eslint-disable-next-line @typescript-eslint/naming-convention
	'company.structure'?: string;
	mcc?: string;
};

export type OnboardingContextValue = {
	data: OnboardingFields;
	setData: ( value: Record< string, string | undefined > ) => void;
	errors: OnboardingFields;
	setErrors: ( value: Record< string, string | undefined > ) => void;
	touched: Record< string, boolean >;
	setTouched: ( value: Record< string, boolean > ) => void;
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
	requires_structure?: boolean;
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

export interface AccountKycSession {
	clientSecret: string;
	expiresAt: number;
	accountId: string;
	isLive: boolean;
	accountCreated: boolean;
	publishableKey: string;
	locale: string;
}

export interface FinalizeOnboardingResponse {
	success: boolean;
	params: Record< string, string >;
}
