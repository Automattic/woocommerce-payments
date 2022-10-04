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

export interface OnboardingProps {
	country: string;
	type: string;
	structure?: string;
}
