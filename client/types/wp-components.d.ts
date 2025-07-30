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
	 * Any component that lives on wp.components but isn't in the package's types.
	 * You can declare as many as you need here.
	 */
	export const Line: ComponentType< React.PropsWithChildren< any > >;
	export const ProgressBar: ComponentType< React.PropsWithChildren< any > >;
	export const GradientPicker: ComponentType< React.PropsWithChildren<
		any
	> >;
	export const CardNotice: ComponentType< React.PropsWithChildren< any > >;

	// Extend the Button component to include __next40pxDefaultSize property
	export namespace Button {
		interface ButtonProps {
			/**
			 * Whether to use the 40px default size for the button.
			 * This is a WordPress component prop that controls button height.
			 * Note: This prop should not be passed to the DOM in test environments.
			 */
			// eslint-disable-next-line @typescript-eslint/naming-convention
			__next40pxDefaultSize?: boolean;
		}
	}
	export namespace TextControl {
		interface TextControlProps {
			/**
			 * Whether to use the 40px default size for the textarea.
			 * This is a WordPress component prop that controls textarea height.
			 */
			// eslint-disable-next-line @typescript-eslint/naming-convention
			__nextHasNoMarginBottom?: boolean;
		}
	}
	export namespace TextareaControl {
		interface TextareaControlProps {
			/**
			 * Whether to use the 40px default size for the textarea.
			 * This is a WordPress component prop that controls textarea height.
			 */
			// eslint-disable-next-line @typescript-eslint/naming-convention
			__nextHasNoMarginBottom?: boolean;
		}
	}
	// …etc…
}

// This ensures the file is treated as a module
export {};
