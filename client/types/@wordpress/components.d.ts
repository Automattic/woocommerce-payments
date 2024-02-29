/**
 * External dependencies
 */
import '@wordpress/components';

/**
 * External dependencies declarations
 */

// Can be removed after updating the package to a newer version.
declare module '@wordpress/components' {
	namespace DropdownMenu {
		interface BaseProps {
			/**
			 * Text to display on the nested `Button` component in the `renderToggle`
			 * implementation of the `Dropdown` component used internally.
			 */
			text?: string | JSX.Element;
		}
	}
}
