/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
declare module '@woocommerce/components' {
	const SummaryListPlaceholder: ( props: {
		numberOfItems: number;
	} ) => JSX.Element;

	const SummaryList: ( props: {
		label: string;
		children: any; // TODO: figure out this and 2 lines below.
	} ) => JSX.Element;

	const OrderStatus: ( props: {
		className?: string;
		// eslint-disable-next-line @typescript-eslint/ban-types
		orderStatusMap: object;
		// eslint-disable-next-line @typescript-eslint/ban-types
		order: object;
	} ) => JSX.Element;
}
