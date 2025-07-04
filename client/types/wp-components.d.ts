/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

import type { ComponentType } from 'react';

// On this module, merge these with whatever @wordpress/components already exports
declare module '@wordpress/components' {
	/**
	 * Any component that lives on wp.components but isn’t in the package’s types.
	 * You can declare as many as you need here.
	 */
	export const Line: ComponentType< React.PropsWithChildren< any > >;
	export const ProgressBar: ComponentType< React.PropsWithChildren< any > >;
	export const GradientPicker: ComponentType< React.PropsWithChildren<
		any
	> >;
	// …etc…
}

// This ensures the file is treated as a module
export {};
