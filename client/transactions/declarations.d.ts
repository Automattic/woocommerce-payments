declare module '@woocommerce/explat' {
	type ExperimentProps = {
		name: string;
		defaultExperience: JSX.Element;
		treatmentExperience?: JSX.Element;
		loadingExperience?: JSX.Element;
	};

	const Experiment: ( props: ExperimentProps ) => JSX.Element;
}

declare module '@woocommerce/components' {
	type ReportFiltersProps = {
		advancedFilters?: Record< string, unknown >;
		filters?: Array< any >;
		path?: string;
		query?: Record< string, unknown >;
		showDatePicker: boolean;
		// some properties are omitted, as we are not currently using them
	};

	const ReportFilters: ( props: ReportFiltersProps ) => JSX.Element;
}

declare module '@woocommerce/navigation' {
	function getQuery(): Record< string, unknown >;
	function updateQueryString(
		query: Record< string, unknown >,
		path: string,
		currentQuery: Record< string, unknown >
	): void;
}

declare module '@wordpress/date' {
	function dateI18n(
		dateFormat: string,
		dateValue: string,
		timezone: boolean
	): string;
}
