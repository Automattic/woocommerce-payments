/**
 * Internal dependencies
 */
export interface RegionPickerInterface {
	country: string;
	setStoreCountry: ( country: string ) => void;
}

export interface suggestedApmInterface {
	suggestedApms: Apm[];
}

export interface suggestedApmsResponseInterface {
	activePlugins: Array< string >;
	paymentGatewaySuggestions: Apm[];
}

export interface UnsupportedAccountPage {
	country: string;
	setStoreCountry: ( country: string ) => void;
	suggestedApms: Apm[];
}

export interface Apm {
	id: string;
	title: string;
	image_72x72: string;
	content: string;
	plugins: Array< string >;
	recommendation_priority: number;
}
