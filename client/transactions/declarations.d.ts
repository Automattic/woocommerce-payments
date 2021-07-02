/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

declare module '@woocommerce/explat' {
	type ExperimentProps = {
		name: string;
		defaultExperience: JSX.Element;
		treatmentExperience?: JSX.Element;
		loadingExperience?: JSX.Element;
	};

	const Experiment: ( props: ExperimentProps ) => JSX.Element;
}

declare module 'interpolate-components' {
	type interpolateComponentsProps = {
		mixedString: string;
		components: Record< string, React.ReactNode >;
	};

	const interpolateComponents: (
		props: interpolateComponentsProps
	) => JSX.Element;

	export = interpolateComponents;
}
declare module '@woocommerce/components' {
	import type { query } from '@woocommerce/navigation';

	type ReportFiltersProps = {
		advancedFilters?: Record< string, unknown >;
		filters?: Array< any >;
		path?: string;
		query?: Record< string, unknown >;
		showDatePicker: boolean;
		// some properties are omitted, as we are not currently using them
	};

	const ReportFilters: ( props: ReportFiltersProps ) => JSX.Element;

	type searchProps = {
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
	};
	const Search: ( props: searchProps ) => JSX.Element;

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
	type tableCardProps = {
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
		query?: query;
		onQueryChange?: unknown;
		actions?: React.ReactNode[];
	};
	const TableCard: ( props: tableCardProps ) => JSX.Element;
}

declare module '@woocommerce/navigation' {
	// TODO: replace the `unknown` types with actual types.
	type query = {
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
		search?: string[];
	};

	const onQueryChange: unknown;
	const getQuery: () => query;
	function updateQueryString(
		q: query,
		path?: string,
		currentQuery?: query
	): void;
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

declare module '@wordpress/date' {
	function dateI18n(
		dateFormat: string,
		dateValue: string,
		timezone?: boolean
	): string;
}
