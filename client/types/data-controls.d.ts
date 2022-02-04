declare module '@wordpress/data-controls' {
	const apiFetch: ( request: Record< string, unknown > ) => unknown;
	const dispatch: (
		storeKey: string,
		actionName: string,
		...args: unknown[]
	) => unknown;
}
