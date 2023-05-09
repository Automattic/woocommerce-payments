/**
 * External dependencies
 */
import { StoreDescriptor } from '@wordpress/data';

declare module '@wordpress/data' {
	type ControlDescriptor = {
		type: string;
		storeKey: string | StoreDescriptor;
		actionName: string;
		args: unknown[];
	};

	interface Controls {
		dispatch: (
			storeKey: string,
			actionName: string,
			...args: unknown[]
		) => ControlDescriptor;
	}

	export const controls: Controls;
}
