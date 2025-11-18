/** @format */

/**
 * Props for the Spotlight component.
 */
export interface SpotlightProps {
	/**
	 * Badge text to display at the top (e.g., "Limited time offer").
	 */
	badge?: string;

	/**
	 * Main heading text.
	 */
	heading: string;

	/**
	 * Description text (can include HTML).
	 */
	description: string;

	/**
	 * Optional disclaimer text shown at the bottom.
	 */
	disclaimer?: string;

	/**
	 * Image element or URL to display in the spotlight.
	 */
	image?: React.ReactNode | string;

	/**
	 * Primary button label.
	 */
	primaryButtonLabel: string;

	/**
	 * Callback when the primary button is clicked.
	 */
	onPrimaryClick: () => void;

	/**
	 * Secondary button/link label (e.g., "Learn more").
	 */
	secondaryButtonLabel?: string;

	/**
	 * Callback when the secondary button is clicked.
	 */
	onSecondaryClick?: () => void;

	/**
	 * Callback when the spotlight is dismissed via the close button.
	 */
	onDismiss: () => void;

	/**
	 * Whether to show the spotlight immediately without delay (for testing).
	 *
	 * @default false
	 */
	showImmediately?: boolean;
}
