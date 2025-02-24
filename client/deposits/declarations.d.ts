/**
 * The types for the `@woocommerce/components` package are being declared here
 * because it does not have its own types (as of version 5.1.2).
 * We should remove this file once we've updated to a version of `@woocommerce/components` with type defs.
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

	type LinkParams = {
		href: string;
		children?: React.ReactNode;
		type?: string;
		target?: string;
		rel?: string;
		onClick?: ( ...props: any ) => any;
	};
	const Link: ( props: LinkParams ) => JSX.Element;

	interface PillProps {
		className: string;
	}

	const Pill: React.FC< PillProps >;

	const TourKit: ( props: any ) => JSX.Element;

	interface ListProps {
		className?: string;
		items: any[];
	}

	const List: React.FC< ListProps >;
}
