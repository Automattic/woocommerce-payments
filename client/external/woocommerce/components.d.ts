declare module '@woocommerce/components' {
	import { WCAdminTableQuery } from '@woocommerce/navigation';

	type orderOptions = "asc" | "desc";

	type tableHeader = {
		defaultSort?: boolean;
		defaultOrder?: orderOptions;
		isLeftAligned?: boolean;
		isNumeric?: boolean;
		isSortable?: boolean;
		key?: string;
		label?: JSX.Element | string;
		required?: boolean;
		screenReaderLabel?: string;
	};

	type tableCell = {
		display: React.Component<any, any>;
		value: string | number | boolean;
	};

	type tableRow = tableCell[];

	type tableCardProperties = {
		title?: string;
		isLoading?: boolean;
		rowsPerPage?: number;
		totalRows?: number;
		headers?: tableHeader[];
		rows?: tableRow[];
		query?: WCAdminTableQuery | string;
		onQueryChange: ( param: string, path?: string, query?: WCAdminTableQuery | string ) => ( ( arg0: any ) => void );
	};

	const TableCard: React.FunctionComponent<tableCardProperties>;

	type linkProps = {
		href: string;
		type?: string;
	};

	const Link: React.FunctionComponent<linkProps>;
}
