/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

declare module '@woocommerce/explat' {
	interface ExperimentProps {
		name: string;
		defaultExperience: JSX.Element;
		treatmentExperience?: JSX.Element;
		loadingExperience?: JSX.Element;
	}

	const Experiment: ( props: ExperimentProps ) => JSX.Element;
}

declare module 'interpolate-components' {
	interface InterpolateComponentsParams {
		mixedString: string;
		components: Record< string, React.ReactNode >;
	}

	const interpolateComponents: (
		props: InterpolateComponentsParams
	) => JSX.Element;

	export = interpolateComponents;
}
declare module '@woocommerce/components' {
	import type { Query } from '@woocommerce/navigation';

	interface ReportFiltersProps {
		advancedFilters?: Record< string, unknown >;
		filters?: Array< any >;
		path?: string;
		query?: Query;
		showDatePicker: boolean;
		// some properties are omitted, as we are not currently using them
	}

	const ReportFilters: ( props: ReportFiltersProps ) => JSX.Element;

	interface SearchProps {
		allowFreeTextSearch?: boolean;
		inlineTags?: boolean;
		key?: string;
		onChange?: ( args: any ) => void;
		placeholder?: string;
		selected?: { key: number | string; label: string }[];
		showClearButton?: boolean;
		type:
			| 'categories'
			| 'countries'
			| 'coupons'
			| 'customers'
			| 'downloadIps'
			| 'emails'
			| 'orders'
			| 'products'
			| 'taxes'
			| 'usernames'
			| 'variations'
			| 'custom';
		autocompleter: unknown;
	}
	const Search: ( props: SearchProps ) => JSX.Element;

	interface TableCardColumn {
		key: string;
		label: string;
		screenReaderLabel?: string;
		required?: boolean;
		isNumeric?: boolean;
		isLeftAligned?: boolean;
		defaultOrder?: 'desc' | 'asc';
		isSortable?: boolean;
		defaultSort?: boolean;
	}
	interface TableCardProps {
		className?: string;
		title?: string;
		isLoading?: boolean;
		rowsPerPage?: number;
		totalRows?: number;
		headers?: TableCardColumn[];
		rows?: {
			value?: string | number | boolean;
			display?: React.ReactNode;
		}[][];
		summary?: { label: string; value: string | number | boolean }[];
		query?: Query;
		onQueryChange?: unknown;
		actions?: React.ReactNode[];
	}
	const TableCard: ( props: TableCardProps ) => JSX.Element;
}

declare module '@woocommerce/navigation' {
	// TODO: replace the `unknown` types with actual types.
	interface Query {
		path?: unknown;
		page?: unknown;
		paged?: string;
		per_page?: string;
		orderby?: string;
		order?: unknown;
		match?: unknown;
		date_before?: unknown;
		date_after?: unknown;
		date_between?: string[];
		type_is?: unknown;
		type_is_not?: unknown;
		store_currency_is?: string;
		loan_id_is?: string;
		search?: string[];
		status_is?: string;
		status_is_not?: string;
	}

	const onQueryChange: unknown;
	const getQuery: () => Query;
	const updateQueryString: (
		query: Query,
		path?: string,
		currentQuery?: Query
	) => void;
}

declare module '@woocommerce/csv-export' {
	import type { TableCardColumn } from '@woocommerce/components';

	const downloadCSVFile: ( fileName: string, content: string ) => void;
	const generateCSVDataFromTable: (
		headers: TableCardColumn[],
		rows: {
			value?: string | number | boolean;
			display?: React.ReactNode;
		}[][]
	) => string;
	const generateCSVFileName: (
		name: string,
		params: Record< string, any >
	) => string;
}

declare module '*.svg';
