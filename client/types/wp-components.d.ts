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
	export const Line: ComponentType< any >;
	export const ProgressBar: ComponentType< any >;
	export const GradientPicker: ComponentType< any >;
	// …etc…
}

// This ensures the file is treated as a module
export {};
