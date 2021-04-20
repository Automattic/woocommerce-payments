/**
 * External dependencies
 */

declare module '@woocommerce/components' {
	type LinkParams = {
		href?: string;
		type?: 'wp-admin' | 'wc-admin' | 'external'; // Default value is 'wc-admin'.
		children?: JSX.Element;
	};

	const Link: ( props: LinkParams ) => JSX.Element;
}
