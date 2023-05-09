declare module '@wordpress/data' {
	interface Controls {
		dispatch: (
			storeKey: string,
			actionName: string,
			...args: unknown[]
		) => unknown;
	}

	export const controls: Controls;

	export function useDispatch( storeKey: string ): Controls;

	export function useSelect< T >(
		mapSelect: ( select: ( storeKey: string ) => unknown ) => T,
		deps?: ReadonlyArray< unknown >
	): T;
}
