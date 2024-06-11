/**
 * Internal dependencies
 */
export interface RegionPickerInterface {
	country: string;
	setStoreCountry: ( country: string ) => void;
}

export interface Apm {
	id: string;
	title: string;
	image_72x72: string;
	content: string;
	plugins: Array< string >;
}
