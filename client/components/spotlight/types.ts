/** @format */

/**
 * External dependencies
 */
import type { ReactNode } from 'react';

/**
 * Internal dependencies
 */
import { ChipType } from 'wcpay/components/chip';

/**
 * Props for the Spotlight component.
 */
export interface SpotlightProps {
	/**
	 * Badge text to display at the top (e.g., "Limited time offer").
	 */
	badge?: string;

	/**
	 * Badge type/color for the Chip component.
	 * Defaults to "success" if not provided or invalid.
	 */
	badgeType?: ChipType;

	/**
	 * Main heading text.
	 */
	heading: string;

	/**
	 * Description content (can be a string or React component).
	 */
	description: ReactNode;

	/**
	 * Optional footnote content shown at the bottom (can be a string or React component).
	 */
	footnote?: ReactNode;

	/**
	 * Image element or URL to display in the spotlight.
	 */
	image?: ReactNode | string;

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
	 * Callback when the spotlight becomes visible (after delay and animation starts).
	 * Useful for tracking view events.
	 */
	onView?: () => void;

	/**
	 * Whether to show the spotlight immediately without delay (for testing).
	 *
	 * @default false
	 */
	showImmediately?: boolean;
}
