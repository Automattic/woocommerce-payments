/**
 * External dependencies
 */
import { StoreDescriptor } from '@wordpress/data/build-types/types';
import { AnyConfig } from '@wordpress/data/src/types';

declare module '@wordpress/data' {
	type ControlDescriptor = {
		type: string;
		storeKey: string | StoreDescriptor< AnyConfig >;
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
