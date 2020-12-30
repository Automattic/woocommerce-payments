
declare module '@woocommerce/navigation' {
	type WCAdminTableQuery = {
		paged: string;
		per_page: string;
	};
	type ReactRouterHistory = any;

	const onQueryChange: ( param: string, path?: string, query?: WCAdminTableQuery | string ) => ( ( value: string ) => void );
	const getQuery: () => WCAdminTableQuery | any;
	const getHistory: () => ReactRouterHistory;
}
