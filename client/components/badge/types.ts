/** @format */

/**
 * Props for the Badge component.
 */
export interface BadgeProps {
	/**
	 * The variant/type of badge to display.
	 * Determines the visual styling (color scheme).
	 *
	 * @default 'info'
	 */
	variant?: 'info' | 'success' | 'warning' | 'error';

	/**
	 * The text content to display in the badge.
	 */
	children: string;

	/**
	 * Additional CSS class name(s) to apply to the badge.
	 */
	className?: string;
}
