export interface ApiList< T > {
	object: 'list';
	data: Array< T >;
	// eslint-disable-next-line camelcase
	has_more: boolean;
	url: string;
}
