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
		children?: () => any;
	} ) => JSX.Element;

	const OrderStatus: ( {
		order: { status },
		className,
		orderStatusMap,
		labelPositionToLeft,
	}: {
		order: {
			status: string;
		};
		className?: string;
		// eslint-disable-next-line @typescript-eslint/ban-types
		orderStatusMap: Object;
		labelPositionToLeft?: boolean;
	} ) => JSX.Element;
}
