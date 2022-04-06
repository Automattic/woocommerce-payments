export interface Country {
	key: string;
	name: string;
	types: BusinessType[];
}

export interface BusinessType {
	key: 'individual' | 'company' | 'non_profit' | 'government_entity';
	name: string;
	description: string;
	structures: BusinessStructure[];
}

export interface BusinessStructure {
	key: string;
	name: string;
}

export interface OnboardingState {
	country?: string;
	type?: BusinessType[ 'key' ];
	structure?: string;
}
