/** @format */

export interface Country {
	country_code: string;
	types: BusinessType[];
}

export interface BusinessType {
	type: string;
	structures: BusinessStructure[];
}

export interface BusinessStructure {
	key: string;
	label: string;
}
